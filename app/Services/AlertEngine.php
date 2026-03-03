<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
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
     * Construct the alert engine.
     *
     * @param EmailNotifier $emailNotifier
     * @param SlackNotifier $slackNotifier
     */
    public function __construct(
        private readonly EmailNotifier $emailNotifier,
        private readonly SlackNotifier $slackNotifier
    ) {}

    /**
     * Flush pending alerts.
     *
     * @return void
     */
    public function flushPendingAlerts(): void {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Alert> $alerts */
        $alerts = Alert::where('enabled', true)->get();
        $intervalMinutes = $this->getIntervalMinutes();

        foreach ($alerts as $alert) {
            $pending = $this->getPending($alert);
            if (empty($pending)) {
                continue;
            }
            $lastSentAt = $this->getLastSentAt($alert);
            if ($intervalMinutes > 0 && $lastSentAt !== null && now()->lt($lastSentAt->copy()->addMinutes($intervalMinutes))) {
                continue;
            }
            $this->sendBatchedAlert($alert, $pending);
            $this->clearPending($alert);
            $this->setLastSentAt($alert);
        }
    }

    public function evaluateAfterEvent(int $serviceId, string $eventType, ?int $jobId = null): void {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Alert> $alerts */
        $alerts = Alert::where('enabled', true)
            ->where(function ($q) use ($serviceId) {
                $q->whereNull('service_id')->orWhere('service_id', $serviceId);
            })
            ->get();

        foreach ($alerts as $alert) {
            if (! $this->shouldEvaluate($alert, $serviceId, $eventType, $jobId)) {
                continue;
            }
            if ($this->evaluateRule($alert, $serviceId, $jobId)) {
                $this->triggerAlert($alert, $serviceId, $jobId);
            }
        }
    }

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
     * Get the interval minutes for the alert engine.
     *
     * @return int
     */
    private function getIntervalMinutes(): int {
        $fromSetting = Setting::get('alerts.email_interval_minutes');
        if ($fromSetting !== null && is_numeric($fromSetting)) {
            return (int) $fromSetting;
        }
        return (int) config('horizonhub.alert_email_interval_minutes', 5);
    }

    /**
     * Should evaluate the alert.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string $eventType
     * @param int|null $jobId
     * @return bool
     */
    private function shouldEvaluate(Alert $alert, int $serviceId, string $eventType, ?int $jobId): bool {
        if ($alert->rule_type === 'job_specific_failure' && $eventType !== 'JobFailed') {
            return false;
        }
        if ($alert->rule_type === 'job_type_failure' && $eventType !== 'JobFailed') {
            return false;
        }
        if ($alert->rule_type === 'failure_count' && $eventType !== 'JobFailed') {
            return false;
        }
        if (in_array($alert->rule_type, ['queue_blocked', 'worker_offline'], true)) {
            return false;
        }
        return true;
    }

    /**
     * Evaluate the rule.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param int|null $jobId
     * @return bool
     */
    private function evaluateRule(Alert $alert, int $serviceId, ?int $jobId): bool {
        return match ($alert->rule_type) {
            'job_specific_failure' => true,
            'job_type_failure' => $this->evaluateJobTypeFailure($alert, $serviceId),
            'failure_count' => $this->evaluateFailureCount($alert, $serviceId),
            'avg_execution_time' => $this->evaluateAvgExecutionTime($alert, $serviceId),
            'queue_blocked' => $this->evaluateQueueBlocked($alert, $serviceId),
            'worker_offline' => $this->evaluateWorkerOffline($alert, $serviceId),
            default => false,
        };
    }

    /**
     * Evaluate the job type failure.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function evaluateJobTypeFailure(Alert $alert, int $serviceId): bool {
        if (! $alert->job_type) {
            return false;
        }
        $recent = HorizonFailedJob::where('service_id', $serviceId)
            ->where('failed_at', '>=', now()->subMinutes(15))
            ->get()
            ->filter(fn ($j) => str_contains((string) ($j->payload['displayName'] ?? $j->payload['job'] ?? ''), $alert->job_type))
            ->isNotEmpty();
        return $recent;
    }

    /**
     * Evaluate the failure count.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function evaluateFailureCount(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? array();
        $count = (int) ($threshold['count'] ?? 5);
        $minutes = (int) ($threshold['minutes'] ?? 15);
        $actual = HorizonFailedJob::where('service_id', $serviceId)
            ->when($alert->queue, fn ($q) => $q->where('queue', $alert->queue))
            ->where('failed_at', '>=', now()->subMinutes($minutes))
            ->count();
        return $actual >= $count;
    }

    /**
     * Evaluate the average execution time.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function evaluateAvgExecutionTime(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? array();
        $maxSeconds = (float) ($threshold['seconds'] ?? 60);
        $minutes = (int) ($threshold['minutes'] ?? 15);
        $jobs = HorizonJob::where('service_id', $serviceId)
            ->where('status', 'processed')
            ->whereNotNull('processed_at')
            ->where('processed_at', '>=', now()->subMinutes($minutes))
            ->get();
        if ($jobs->isEmpty()) {
            return false;
        }
        $jobsWithRuntime = $jobs->filter(fn ($j) => $j->getRuntimeSeconds() !== null);
        $avg = $jobsWithRuntime->isEmpty() ? 0.0 : $jobsWithRuntime->avg(fn ($j) => $j->getRuntimeSeconds());
        return $avg >= $maxSeconds;
    }

    /**
     * Evaluate the queue blocked.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function evaluateQueueBlocked(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? array();
        $minutes = (int) ($threshold['minutes'] ?? 30);
        $lastProcessed = HorizonJob::where('service_id', $serviceId)
            ->when($alert->queue, fn ($q) => $q->where('queue', $alert->queue))
            ->where('status', 'processed')
            ->max('processed_at');
        if (! $lastProcessed) {
            return false;
        }
        return Carbon::parse($lastProcessed)->addMinutes($minutes)->isPast();
    }

    /**
     * Evaluate the worker offline.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function evaluateWorkerOffline(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? array();
        $minutes = (int) ($threshold['minutes'] ?? 5);
        $service = Service::find($serviceId);
        if (! $service || ! $service->last_seen_at) {
            return false;
        }
        return $service->last_seen_at->addMinutes($minutes)->isPast();
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
        $jobIds = array_values(array_filter(array_column($events, 'job_id')));

        $newLog = AlertLog::create(array(
            'alert_id' => $alert->id,
            'job_id' => isset($jobIds[0]) ? $jobIds[0] : null,
            'service_id' => $serviceId,
            'trigger_count' => count($events),
            'job_ids' => ! empty($jobIds) ? $jobIds : null,
            'status' => 'sent',
            'failure_message' => null,
            'sent_at' => now(),
        ));

        $providers = $alert->notificationProviders;

        if ($providers instanceof Collection && $providers->isNotEmpty()) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, NotificationProvider> $providers */
            foreach ($providers as $provider) {
                try {
                    if ($provider->type === NotificationProvider::TYPE_SLACK) {
                        $webhookUrl = $provider->getWebhookUrl();
                        if ($webhookUrl !== '') {
                            $this->slackNotifier->sendBatched($alert, $events, array('webhook_url' => $webhookUrl));
                        }
                    }
                    if ($provider->type === NotificationProvider::TYPE_EMAIL) {
                        $to = $provider->getToEmails();
                        if (! empty($to)) {
                            $this->emailNotifier->sendBatched($alert, $events, array('to' => $to));
                        }
                    }
                } catch (\Throwable $e) {
                    $newLog->update(array('status' => 'failed', 'failure_message' => $e->getMessage()));
                }
            }
            return;
        }

        $channels = $alert->notification_channels ?? array();
        foreach ($channels as $channel => $config) {
            try {
                if ($channel === 'email') {
                    $to = $config['to'] ?? Setting::get('integrations.email.default_to', array());
                    $to = is_array($to) ? $to : array();
                    if (! empty($to)) {
                        $config['to'] = $to;
                        $this->emailNotifier->sendBatched($alert, $events, $config);
                    }
                }
                if ($channel === 'slack') {
                    $webhookUrl = $config['webhook_url'] ?? Setting::get('integrations.slack.webhook_url', '');
                    if ((string) $webhookUrl !== '') {
                        $config['webhook_url'] = $webhookUrl;
                        $this->slackNotifier->sendBatched($alert, $events, $config);
                    }
                }
            } catch (\Throwable $e) {
                $newLog->update(array('status' => 'failed', 'failure_message' => $e->getMessage()));
            }
        }
    }

    /**
     * Rebuild the events from the log.
     *
     * @param AlertLog $log
     * @return array<int, array{service_id: int, job_id: int|null, triggered_at: string}>
     */
    private function rebuildEventsFromLog(AlertLog $log): array {
        $events = array();
        $serviceId = (int) $log->service_id;
        $jobIds = is_array($log->job_ids) ? $log->job_ids : array();
        foreach ($jobIds as $jobId) {
            $events[] = array(
                'service_id' => $serviceId,
                'job_id' => (int) $jobId,
                'triggered_at' => now()->toIso8601String(),
            );
        }
        $expectedCount = (int) ($log->trigger_count ?? count($events));
        $missing = $expectedCount - count($events);
        for ($i = 0; $i < $missing; $i++) {
            $events[] = array(
                'service_id' => $serviceId,
                'job_id' => null,
                'triggered_at' => now()->toIso8601String(),
            );
        }
        return $events;
    }

    /**
     * Get the pending alerts.
     *
     * @param Alert $alert
     * @return array<int, array{service_id: int, job_id: int|null, triggered_at: string}>
     */
    private function getPending(Alert $alert): array {
        $key = self::PENDING_CACHE_PREFIX . $alert->id;
        $raw = Cache::get($key);
        if (! is_array($raw)) {
            return array();
        }
        return $raw;
    }

    /**
     * Set the pending alerts.
     *
     * @param Alert $alert
     * @param array<int, array{service_id: int, job_id: int|null, triggered_at: string}> $pending
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
        $this->setPending($alert, array());
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
        Cache::put(self::SENT_AT_CACHE_PREFIX . $alert->id, now(), now()->addMinutes(self::PENDING_TTL_MINUTES));
    }

    /**
     * Trigger the alert.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param int|null $jobId
     * @return void
     */
    private function triggerAlert(Alert $alert, int $serviceId, ?int $jobId): void {
        $event = array(
            'service_id' => $serviceId,
            'job_id' => $jobId,
            'triggered_at' => now()->toIso8601String(),
        );
        $pending = $this->getPending($alert);
        $pending[] = $event;
        $this->setPending($alert, $pending);

        $intervalMinutes = $this->getIntervalMinutes();
        $lastSentAt = $this->getLastSentAt($alert);
        $shouldSendNow = $intervalMinutes === 0
            || $lastSentAt === null
            || now()->gte($lastSentAt->copy()->addMinutes($intervalMinutes));

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
     * @param array<int, array{service_id: int, job_id: int|null, triggered_at: string}> $events
     */
    private function sendBatchedAlert(Alert $alert, array $events): void {
        $first = $events[0];
        $serviceId = (int) $first['service_id'];
        $jobId = $first['job_id'] ?? null;
        $jobIds = array_values(array_filter(array_column($events, 'job_id')));

        $log = AlertLog::create([
            'alert_id' => $alert->id,
            'job_id' => $jobId,
            'service_id' => $serviceId,
            'trigger_count' => count($events),
            'job_ids' => $jobIds ?: null,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $providers = $alert->notificationProviders;

        if ($providers->isNotEmpty()) {
            foreach ($providers as $provider) {
                try {
                    if ($provider->type === NotificationProvider::TYPE_SLACK) {
                        $webhookUrl = $provider->getWebhookUrl();
                        if ($webhookUrl !== '') {
                            $this->slackNotifier->sendBatched($alert, $events, array('webhook_url' => $webhookUrl));
                        }
                    }
                    if ($provider->type === NotificationProvider::TYPE_EMAIL) {
                        $to = $provider->getToEmails();
                        if (! empty($to)) {
                            $this->emailNotifier->sendBatched($alert, $events, array('to' => $to));
                        } else {
                            Log::warning('Horizon Hub: email provider has no recipients, skip', ['alert_id' => $alert->id, 'provider_id' => $provider->id]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Horizon Hub alert notification failed', ['alert_id' => $alert->id, 'provider_id' => $provider->id, 'error' => $e->getMessage()]);
                    $log->update(['status' => 'failed', 'failure_message' => $e->getMessage()]);
                }
            }
        } else {
            $channels = $alert->notification_channels ?? array();
            foreach ($channels as $channel => $config) {
                try {
                    if ($channel === 'email') {
                        $to = $config['to'] ?? Setting::get('integrations.email.default_to', array());
                        $to = is_array($to) ? $to : array();
                        if (! empty($to)) {
                            $merged = array_merge($config, array('to' => $to));
                            $this->emailNotifier->sendBatched($alert, $events, $merged);
                        }
                    }
                    if ($channel === 'slack') {
                        $webhookUrl = $config['webhook_url'] ?? Setting::get('integrations.slack.webhook_url', '');
                        if ((string) $webhookUrl !== '') {
                            $merged = array_merge($config, array('webhook_url' => $webhookUrl));
                            $this->slackNotifier->sendBatched($alert, $events, $merged);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Horizon Hub alert notification failed', ['alert_id' => $alert->id, 'channel' => $channel, 'error' => $e->getMessage()]);
                    $log->update(['status' => 'failed', 'failure_message' => $e->getMessage()]);
                }
            }
        }
    }
}
