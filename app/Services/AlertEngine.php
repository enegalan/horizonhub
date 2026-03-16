<?php

namespace App\Services;

use App\Contracts\EmailAlertNotifier;
use App\Contracts\SlackAlertNotifier;
use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Setting;
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
            $pending = $this->getPending($alert);
            if (empty($pending)) {
                continue;
            }
            $intervalMinutes = $this->getIntervalMinutes($alert);
            $lastSentAt = $this->getLastSentAt($alert);
            if ($intervalMinutes > 0 && $lastSentAt !== null && \now()->lt($lastSentAt->copy()->addMinutes($intervalMinutes))) {
                continue;
            }
            $this->sendBatchedAlert($alert, $pending);
            $this->clearPending($alert);
            $this->setLastSentAt($alert);
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
            if (! $this->shouldEvaluate($alert, $eventType)) {
                continue;
            }
            if ($this->evaluateRule($alert, $serviceId, $jobUuid)) {
                $this->triggerAlert($alert, $serviceId, $jobUuid);
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
            $serviceIds = $alert->service_id ? [$alert->service_id] : $services->pluck('id')->all();
            foreach ($serviceIds as $serviceId) {
                if ($this->evaluateRule($alert, $serviceId, null)) {
                    $this->triggerAlert($alert, $serviceId, null);
                    break;
                }
            }
        }
    }

    /**
     * Get the interval minutes for the given alert.
     *
     * @param Alert $alert
     * @return int
     */
    private function getIntervalMinutes(Alert $alert): int {
        if ($alert->email_interval_minutes !== null) {
            return (int) $alert->email_interval_minutes;
        }
        throw new \RuntimeException('Alert email interval minutes is not set for alert ' . $alert->id);
    }

    /**
     * Should evaluate the alert.
     *
     * @param Alert $alert
     * @param string $eventType
     * @return bool
     */
    private function shouldEvaluate(Alert $alert, string $eventType): bool {
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
    private function evaluateRule(Alert $alert, int $serviceId, ?string $jobUuid): bool {
        return $this->ruleEvaluator->evaluate($alert, $serviceId, $jobUuid);
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
        $events = $this->rebuildEventsFromLog($log);
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

        $this->sendAlertForLog($alert, $events, $newLog);
    }

    /**
     * Rebuild the events from the log.
     *
     * @param AlertLog $log
     * @return array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}>
     */
    private function rebuildEventsFromLog(AlertLog $log): array {
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
    private function getPending(Alert $alert): array {
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
    private function setPending(Alert $alert, array $pending): void {
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
    private function clearPending(Alert $alert): void {
        $this->setPending($alert, []);
    }

    /**
     * Get the last sent at.
     *
     * @param Alert $alert
     * @return Carbon|null
     */
    private function getLastSentAt(Alert $alert): ?Carbon {
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
    private function setLastSentAt(Alert $alert): void {
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
    private function triggerAlert(Alert $alert, int $serviceId, ?string $jobUuid): void {
        $event = [
            'service_id' => $serviceId,
            'job_uuid' => $jobUuid,
            'triggered_at' => \now()->toIso8601String(),
        ];
        $pending = $this->getPending($alert);
        $pending[] = $event;
        $this->setPending($alert, $pending);

        $intervalMinutes = $this->getIntervalMinutes($alert);
        $lastSentAt = $this->getLastSentAt($alert);
        $shouldSendNow = $intervalMinutes === 0
            || $lastSentAt === null
            || \now()->gte($lastSentAt->copy()->addMinutes($intervalMinutes));

        if (! $shouldSendNow) {
            return;
        }

        $this->sendBatchedAlert($alert, $pending);
        $this->clearPending($alert);
        $this->setLastSentAt($alert);
    }

    /**
     * Send the batched alert.
     *
     * @param Alert $alert
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events
     */
    private function sendBatchedAlert(Alert $alert, array $events): void {
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

        $this->sendAlertForLog($alert, $events, $log);
    }

    /**
     * Send alert notifications for the given log using providers or channels.
     *
     * @param Alert $alert
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events
     * @param AlertLog $log
     * @return void
     */
    private function sendAlertForLog(Alert $alert, array $events, AlertLog $log): void {
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
