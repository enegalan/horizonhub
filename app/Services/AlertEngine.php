<?php

namespace App\Services;

use App\Contracts\EmailAlertNotifier;
use App\Contracts\SlackAlertNotifier;
use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AlertEngine {

    /**
     * The cache prefix for pending alerts.
     *
     * @var string
     */
    private const PENDING_CACHE_PREFIX = 'horizonhub_alert_pending_';

    /**
     * The cache prefix for sent alerts.
     *
     * @var string
     */
    private const SENT_AT_CACHE_PREFIX = 'horizonhub_alert_sent_at_';

    /**
     * The cache TTL for pending alerts.
     *
     * @var int
     */
    private const PENDING_TTL_MINUTES = 60;

    /**
     * Default interval (minutes) when alert has none set.
     *
     * @var int
     */
    private const DEFAULT_INTERVAL_MINUTES = 5;

    /**
     * The rule evaluator.
     *
     * @var AlertRuleEvaluator
     */
    private AlertRuleEvaluator $ruleEvaluator;

    /**
     * The email notifier.
     *
     * @var EmailAlertNotifier
     */
    private EmailAlertNotifier $emailNotifier;

    /**
     * The slack notifier.
     *
     * @var SlackAlertNotifier
     */
    private SlackAlertNotifier $slackNotifier;

    /**
     * Construct the alert engine.
     *
     * @param EmailAlertNotifier $emailNotifier
     * @param SlackAlertNotifier $slackNotifier
     * @param AlertRuleEvaluator $ruleEvaluator
     */
    public function __construct(
        EmailAlertNotifier $emailNotifier,
        SlackAlertNotifier $slackNotifier,
        AlertRuleEvaluator $ruleEvaluator
    ) {
        $this->emailNotifier = $emailNotifier;
        $this->slackNotifier = $slackNotifier;
        $this->ruleEvaluator = $ruleEvaluator;
    }

    /**
     * Flush pending alerts.
     *
     * @return void
     */
    public function flushPendingAlerts(): void {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Alert> $alerts */
        $alerts = Alert::where('enabled', true)->get();
        foreach ($alerts as $alert) {
            try {
                $pending = $this->private__getPending($alert);
                if (empty($pending)) {
                    continue;
                }
                $intervalMinutes = $this->private__getIntervalMinutes($alert);
                $lastSentAt = $this->private__getLastSentAt($alert);
                if ($intervalMinutes > 0 && $lastSentAt !== null && \now()->lt($lastSentAt->copy()->addMinutes($intervalMinutes))) {
                    continue;
                }
                $this->private__sendBatchedAlert($alert, $pending);
                $this->private__clearPending($alert);
                $this->private__setLastSentAt($alert);
            } catch (\Throwable $e) {
                Log::error('Horizon Hub: flush pending alert failed', ['alert_id' => $alert->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Evaluate the alert after an event.
     *
     * @param int $serviceId
     * @param string $eventType
     * @param string|null $jobUuid
     * @return void
     */
    public function evaluateAfterEvent(int $serviceId, string $eventType, ?string $jobUuid = null): void {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Alert> $alerts */
        $alerts = Alert::where('enabled', true)
            ->where(function ($q) use ($serviceId) {
                $q->whereNull('service_id')->orWhere('service_id', $serviceId);
            })
            ->get();

        foreach ($alerts as $alert) {
            if (! $this->private__shouldEvaluate($alert, $eventType)) {
                continue;
            }
            if ($this->private__evaluateRule($alert, $serviceId, $jobUuid)) {
                $this->private__triggerAlert($alert, $serviceId, $jobUuid);
            }
        }
    }

    /**
     * Evaluate the scheduled alerts.
     *
     * @return void
     */
    public function evaluateScheduled(): void {
        $this->flushPendingAlerts();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Alert> $alerts */
        $alerts = Alert::where('enabled', true)->get();
        /** @var \Illuminate\Database\Eloquent\Collection<int, Service> $services */
        $services = Service::all();

        foreach ($alerts as $alert) {
            try {
                $serviceIds = $alert->service_id ? [$alert->service_id] : $services->pluck('id')->all();
                if (empty($serviceIds)) {
                    Log::warning('Horizon Hub: no services to evaluate alert (add at least one service)', ['alert_id' => $alert->id]);
                    continue;
                }
                foreach ($serviceIds as $serviceId) {
                    if ($this->private__evaluateRule($alert, $serviceId, null)) {
                        $this->private__triggerAlert($alert, $serviceId, null);
                        break;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Horizon Hub: evaluate scheduled alert failed', ['alert_id' => $alert->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Retry the alert log.
     *
     * @param AlertLog $log
     * @return void
     */
    public function retryAlertLog(AlertLog $log): void {
        $alert = $log->alert;
        if (! $alert) {
            return;
        }
        if ($log->status !== 'failed') {
            return;
        }
        $events = $this->private__rebuildEventsFromLog($log);
        if (empty($events)) {
            return;
        }
        $serviceId = (int) $log->service_id;
        $jobUuids = \array_values(\array_filter(\array_column($events, 'job_uuid')));

        $newLog = AlertLog::create([
            'alert_id' => $alert->id,
            'service_id' => $serviceId,
            'trigger_count' => count($events),
            'job_uuids' => ! empty($jobUuids) ? $jobUuids : null,
            'status' => 'sent',
            'failure_message' => null,
            'sent_at' => \now(),
        ]);

        $this->private__sendAlertForLog($alert, $events, $newLog);
    }

    /**
     * Get the interval minutes for the given alert.
     *
     * @param Alert $alert
     * @return int
     */
    private function private__getIntervalMinutes(Alert $alert): int {
        if ($alert->email_interval_minutes !== null) {
            return (int) $alert->email_interval_minutes;
        }
        Log::warning('Horizon Hub: alert has no email_interval_minutes, using default', ['alert_id' => $alert->id]);
        return self::DEFAULT_INTERVAL_MINUTES;
    }

    /**
     * Should evaluate the alert.
     *
     * @param Alert $alert
     * @param string $eventType
     * @return bool
     */
    private function private__shouldEvaluate(Alert $alert, string $eventType): bool {
        if ($alert->rule_type === 'job_specific_failure' && $eventType !== 'JobFailed') {
            return false;
        }
        if ($alert->rule_type === 'job_type_failure' && $eventType !== 'JobFailed') {
            return false;
        }
        if ($alert->rule_type === 'failure_count' && $eventType !== 'JobFailed') {
            return false;
        }
        if (\in_array($alert->rule_type, ['queue_blocked', 'worker_offline', 'supervisor_offline'], true)) {
            return false;
        }
        return true;
    }

    /**
     * Evaluate the rule.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @return bool
     */
    private function private__evaluateRule(Alert $alert, int $serviceId, ?string $jobUuid): bool {
        return $this->ruleEvaluator->evaluate($alert, $serviceId, $jobUuid);
    }

    /**
     * Rebuild the events from the log.
     *
     * @param AlertLog $log
     * @return array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}>
     */
    private function private__rebuildEventsFromLog(AlertLog $log): array {
        $events = [];
        $serviceId = (int) $log->service_id;
        $jobUuids = \is_array($log->job_uuids) ? $log->job_uuids : [];
        foreach ($jobUuids as $jobUuid) {
            $events[] = [
                'service_id' => $serviceId,
                'job_uuid' => $jobUuid,
                'triggered_at' => \now()->toIso8601String(),
            ];
        }
        $expectedCount = (int) ($log->trigger_count ?? \count($events));
        $missing = $expectedCount - \count($events);
        for ($i = 0; $i < $missing; $i++) {
            $events[] = [
                'service_id' => $serviceId,
                'job_uuid' => null,
                'triggered_at' => \now()->toIso8601String(),
            ];
        }
        return $events;
    }

    /**
     * Get the pending alerts.
     *
     * @param Alert $alert
     * @return array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}>
     */
    private function private__getPending(Alert $alert): array {
        $key = self::PENDING_CACHE_PREFIX . $alert->id;
        $raw = Cache::get($key);
        if (! \is_array($raw)) {
            return [];
        }
        return $raw;
    }

    /**
     * Set the pending alerts.
     *
     * @param Alert $alert
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $pending
     * @return void
     */
    private function private__setPending(Alert $alert, array $pending): void {
        $key = self::PENDING_CACHE_PREFIX . $alert->id;
        if (empty($pending)) {
            Cache::forget($key);
            return;
        }
        Cache::put($key, $pending, now()->addMinutes(self::PENDING_TTL_MINUTES));
    }

    /**
     * Clear the pending alerts.
     *
     * @param Alert $alert
     * @return void
     */
    private function private__clearPending(Alert $alert): void {
        $this->private__setPending($alert, []);
    }

    /**
     * Get the last sent at.
     *
     * @param Alert $alert
     * @return Carbon|null
     */
    private function private__getLastSentAt(Alert $alert): ?Carbon {
        $key = self::SENT_AT_CACHE_PREFIX . $alert->id;
        $value = Cache::get($key);
        if ($value === null) {
            return null;
        }
        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }

    /**
     * Set the last sent at.
     *
     * @param Alert $alert
     * @return void
     */
    private function private__setLastSentAt(Alert $alert): void {
        Cache::put(self::SENT_AT_CACHE_PREFIX . $alert->id, \now(), \now()->addMinutes(self::PENDING_TTL_MINUTES));
    }

    /**
     * Trigger the alert.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @return void
     */
    private function private__triggerAlert(Alert $alert, int $serviceId, ?string $jobUuid): void {
        $event = [
            'service_id' => $serviceId,
            'job_uuid' => $jobUuid,
            'triggered_at' => \now()->toIso8601String(),
        ];
        $pending = $this->private__getPending($alert);
        $pending[] = $event;
        $this->private__setPending($alert, $pending);

        $intervalMinutes = $this->private__getIntervalMinutes($alert);
        $lastSentAt = $this->private__getLastSentAt($alert);
        $shouldSendNow = $intervalMinutes === 0
            || $lastSentAt === null
            || \now()->gte($lastSentAt->copy()->addMinutes($intervalMinutes));

        if (! $shouldSendNow) {
            return;
        }

        $this->private__sendBatchedAlert($alert, $pending);
        $this->private__clearPending($alert);
        $this->private__setLastSentAt($alert);
    }

    /**
     * Send the batched alert.
     *
     * @param Alert $alert
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events
     */
    private function private__sendBatchedAlert(Alert $alert, array $events): void {
        $first = $events[0];
        $serviceId = (int) $first['service_id'];
        $jobUuids = \array_values(\array_filter(\array_column($events, 'job_uuid')));

        $log = AlertLog::create([
            'alert_id' => $alert->id,
            'service_id' => $serviceId,
            'trigger_count' => count($events),
            'job_uuids' => $jobUuids ?: null,
            'status' => 'sent',
            'sent_at' => \now(),
        ]);

        $this->private__sendAlertForLog($alert, $events, $log);
    }

    /**
     * Send alert notifications for the given log using providers or channels.
     *
     * @param Alert $alert
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events
     * @param AlertLog $log
     * @return void
     */
    private function private__sendAlertForLog(Alert $alert, array $events, AlertLog $log): void {
        $providers = $alert->notificationProviders;

        if ($providers instanceof Collection && $providers->isNotEmpty()) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, NotificationProvider> $providers */
            foreach ($providers as $provider) {
                try {
                    if ($provider->type === NotificationProvider::TYPE_SLACK) {
                        $webhookUrl = $provider->getWebhookUrl();
                        if (! empty($webhookUrl)) {
                            $this->slackNotifier->sendBatched($alert, $events, ['webhook_url' => $webhookUrl]);
                        }
                    }
                    if ($provider->type === NotificationProvider::TYPE_EMAIL) {
                        $to = $provider->getToEmails();
                        if (! empty($to)) {
                            $this->emailNotifier->sendBatched($alert, $events, ['to' => $to]);
                        } else {
                            Log::warning('Horizon Hub: email provider has no recipients, skip', ['alert_id' => $alert->id, 'provider_id' => $provider->id]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Horizon Hub alert notification failed', ['alert_id' => $alert->id, 'provider_id' => $provider->id, 'error' => $e->getMessage()]);
                    $log->update(['status' => 'failed', 'failure_message' => $e->getMessage()]);
                }
            }

            return;
        }

        $channels = $alert->notification_channels ?? [];
        foreach ($channels as $channel => $config) {
            try {
                if ($channel === 'email') {
                    $to = isset($config['to']) ? $config['to'] : Setting::get('integrations.email.default_to', []);
                    $to = \is_array($to) ? $to : [];
                    if (! empty($to)) {
                        $config['to'] = $to;
                        $this->emailNotifier->sendBatched($alert, $events, $config);
                    }
                }
                if ($channel === 'slack') {
                    $webhookUrl = isset($config['webhook_url']) ? $config['webhook_url'] : Setting::get('integrations.slack.webhook_url', '');
                    if (! empty($webhookUrl)) {
                        $config['webhook_url'] = $webhookUrl;
                        $this->slackNotifier->sendBatched($alert, $events, $config);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Horizon Hub alert notification failed', ['alert_id' => $alert->id, 'channel' => $channel, 'error' => $e->getMessage()]);
                $log->update(['status' => 'failed', 'failure_message' => $e->getMessage()]);
            }
        }
    }
}
