<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\Service;
use App\Services\Alerts\AlertBatchStore;
use App\Services\Alerts\AlertNotificationDispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class AlertEngine
{
    /**
     * The rule evaluator.
     */
    private AlertRuleEvaluator $ruleEvaluator;

    /**
     * The batch store.
     */
    private AlertBatchStore $batchStore;

    /**
     * The notification dispatcher.
     */
    private AlertNotificationDispatcher $notificationDispatcher;

    /**
     * Construct the alert engine.
     */
    public function __construct(
        AlertRuleEvaluator $ruleEvaluator,
        AlertBatchStore $batchStore,
        AlertNotificationDispatcher $notificationDispatcher
    ) {
        $this->ruleEvaluator = $ruleEvaluator;
        $this->batchStore = $batchStore;
        $this->notificationDispatcher = $notificationDispatcher;
    }

    /**
     * Flush pending alerts.
     */
    public function flushPendingAlerts(): void
    {
        /** @var Collection<int, Alert> $alerts */
        $alerts = Alert::where('enabled', true)
            ->with('notificationProviders')
            ->get();
        foreach ($alerts as $alert) {
            try {
                $pending = $this->batchStore->getPending($alert);
                if (empty($pending)) {
                    continue;
                }
                if (! $this->batchStore->shouldSendNow($alert)) {
                    continue;
                }
                $this->private__sendBatchedAlert($alert, $pending);
                $this->batchStore->clearPending($alert);
                $this->batchStore->setLastSentAt($alert);
            } catch (\Throwable $e) {
                Log::error('Horizon Hub: flush pending alert failed', ['alert_id' => $alert->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Evaluate the alert after an event.
     */
    public function evaluateAfterEvent(int $serviceId, string $eventType, ?string $jobUuid = null): void
    {
        /** @var Collection<int, Alert> $alerts */
        $alerts = Alert::where('enabled', true)
            ->where(function ($q) use ($serviceId) {
                $q->whereNull('service_id')->orWhere('service_id', $serviceId);
            })->with('notificationProviders')
            ->get();

        foreach ($alerts as $alert) {
            if (! $this->private__shouldEvaluate($alert, $eventType)) {
                continue;
            }
            $result = $this->private__evaluateRuleWithJobs($alert, $serviceId, $jobUuid);
            if ($result['triggered']) {
                $this->private__triggerAlert($alert, $serviceId, $jobUuid, $result['job_uuids']);
            }
        }
    }

    /**
     * Evaluate the scheduled alerts.
     */
    public function evaluateScheduled(): void
    {
        $this->flushPendingAlerts();

        /** @var Collection<int, Alert> $alerts */
        $alerts = Alert::where('enabled', true)
            ->with('notificationProviders')
            ->get();
        $services = Service::all();

        foreach ($alerts as $alert) {
            try {
                $serviceIds = $alert->service_id ? [$alert->service_id] : $services->pluck('id')->all();
                if (empty($serviceIds)) {
                    Log::warning('Horizon Hub: no services to evaluate alert (add at least one service)', ['alert_id' => $alert->id]);

                    continue;
                }
                foreach ($serviceIds as $serviceId) {
                    $result = $this->private__evaluateRuleWithJobs($alert, $serviceId, null);
                    if ($result['triggered']) {
                        $this->private__triggerAlert($alert, $serviceId, null, $result['job_uuids']);
                        break;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Horizon Hub: evaluate scheduled alert failed', ['alert_id' => $alert->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Evaluate a single alert immediately (scheduled context).
     *
     * @return array{
     *     alert_id: int,
     *     pending_flushed: bool,
     *     triggered: bool,
     *     triggered_service_id: int|null,
     *     error_message: string|null,
     *     pending_flush_error_message: string|null,
     *     delivered: bool
     * }
     */
    public function evaluateAlert(Alert $alert): array
    {
        $alertId = (int) $alert->id;
        $pendingFlushed = false;
        $triggered = false;
        $triggeredServiceId = null;
        $errorMessage = null;
        $pendingFlushErrorMessage = null;
        $delivered = false;
        $lastSentAtBefore = null;

        try {
            $alert->loadMissing('notificationProviders');

            $lastSentAtBefore = $this->batchStore->getLastSentAt($alert);

            // Flush any batched pending events for this alert first.
            $pending = $this->batchStore->getPending($alert);
            if (! empty($pending) && $this->batchStore->shouldSendNow($alert)) {
                $this->private__sendBatchedAlert($alert, $pending);
                $this->batchStore->clearPending($alert);
                $this->batchStore->setLastSentAt($alert);
                $pendingFlushed = true;
            }
        } catch (\Throwable $e) {
            Log::error('Horizon Hub: evaluate alert failed while flushing pending', [
                'alert_id' => $alertId,
                'error' => $e->getMessage(),
            ]);
            $pendingFlushErrorMessage = $e->getMessage();
        }

        try {
            /** @var array<int, int> $serviceIds */
            $serviceIds = $alert->service_id ? [(int) $alert->service_id] : Service::all()->pluck('id')->all();
            if (empty($serviceIds)) {
                $errorMessage ??= 'No services to evaluate alert (add at least one service).';

                return [
                    'alert_id' => $alertId,
                    'pending_flushed' => $pendingFlushed,
                    'triggered' => false,
                    'triggered_service_id' => null,
                    'error_message' => $errorMessage,
                    'pending_flush_error_message' => $pendingFlushErrorMessage,
                    'delivered' => false,
                ];
            }

            foreach ($serviceIds as $serviceId) {
                $result = $this->private__evaluateRuleWithJobs($alert, (int) $serviceId, null);
                if ($result['triggered']) {
                    $this->private__triggerAlert($alert, (int) $serviceId, null, $result['job_uuids']);
                    $triggered = true;
                    $triggeredServiceId = (int) $serviceId;
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::error('Horizon Hub: evaluate alert failed', [
                'alert_id' => $alertId,
                'error' => $e->getMessage(),
            ]);
            $errorMessage = $e->getMessage();
        }

        try {
            $lastSentAtAfter = $this->batchStore->getLastSentAt($alert);
            $delivered = $lastSentAtAfter !== null && ($lastSentAtBefore === null || ! $lastSentAtAfter->eq($lastSentAtBefore));
        } catch (\Throwable $e) {
        }

        return [
            'alert_id' => $alertId,
            'pending_flushed' => $pendingFlushed,
            'triggered' => $triggered,
            'triggered_service_id' => $triggered ? $triggeredServiceId : null,
            'error_message' => $errorMessage,
            'pending_flush_error_message' => $pendingFlushErrorMessage,
            'delivered' => $delivered,
        ];
    }

    /**
     * Retry the alert log.
     */
    public function retryAlertLog(AlertLog $log): void
    {
        $alert = $log->alert;
        if (! $alert || $log->status !== 'failed') {
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
            'trigger_count' => \count($events),
            'job_uuids' => ! empty($jobUuids) ? $jobUuids : null,
            'status' => 'sent',
            'failure_message' => null,
            'sent_at' => \now(),
        ]);

        $this->notificationDispatcher->dispatch($alert, $events, $newLog);
    }

    /**
     * Should evaluate the alert.
     */
    private function private__shouldEvaluate(Alert $alert, string $eventType): bool
    {
        if ($alert->rule_type === Alert::RULE_JOB_SPECIFIC_FAILURE && $eventType !== 'JobFailed') {
            return false;
        }
        if ($alert->rule_type === Alert::RULE_JOB_TYPE_FAILURE && $eventType !== 'JobFailed') {
            return false;
        }
        if ($alert->rule_type === Alert::RULE_FAILURE_COUNT && $eventType !== 'JobFailed') {
            return false;
        }
        if ($alert->rule_type === Alert::RULE_AVG_EXECUTION_TIME) {
            return \in_array($eventType, ['JobProcessed', 'JobCompleted'], true);
        }
        if (\in_array($alert->rule_type, [
            Alert::RULE_QUEUE_BLOCKED,
            Alert::RULE_WORKER_OFFLINE,
            Alert::RULE_SUPERVISOR_OFFLINE,
            Alert::RULE_HORIZON_OFFLINE,
        ], true)) {
            return false;
        }

        return true;
    }

    /**
     * Evaluate the rule and return triggering job UUIDs when applicable.
     *
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    private function private__evaluateRuleWithJobs(Alert $alert, int $serviceId, ?string $jobUuid): array
    {
        return $this->ruleEvaluator->evaluateWithTriggeringJobs($alert, $serviceId, $jobUuid);
    }

    /**
     * Rebuild the events from the log.
     *
     * @description This method reconstructs alert trigger events from a stored log.
     *              It uses the current time for all reconstructed events and will
     *              generate placeholder events with null job UUIDs when the
     *              trigger_count is greater than the number of stored job UUIDs.
     *
     * @return array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}>
     */
    private function private__rebuildEventsFromLog(AlertLog $log): array
    {
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
     * Trigger the alert.
     *
     * @param  array<int, string>  $triggeringJobUuids
     */
    private function private__triggerAlert(Alert $alert, int $serviceId, ?string $jobUuid, array $triggeringJobUuids = []): void
    {
        $triggeredAt = \now()->toIso8601String();
        $eventsToAdd = [];
        if (\count($triggeringJobUuids) > 0) {
            foreach ($triggeringJobUuids as $uuid) {
                $eventsToAdd[] = [
                    'service_id' => $serviceId,
                    'job_uuid' => $uuid,
                    'triggered_at' => $triggeredAt,
                ];
            }
        } else {
            $eventsToAdd[] = [
                'service_id' => $serviceId,
                'job_uuid' => $jobUuid,
                'triggered_at' => $triggeredAt,
            ];
        }
        $pending = $this->batchStore->getPending($alert);
        foreach ($eventsToAdd as $event) {
            $pending[] = $event;
        }
        $this->batchStore->setPending($alert, $pending);

        if (! $this->batchStore->shouldSendNow($alert)) {
            return;
        }

        $this->private__sendBatchedAlert($alert, $pending);
        $this->batchStore->clearPending($alert);
        $this->batchStore->setLastSentAt($alert);
    }

    /**
     * Send the batched alert.
     *
     * @param  array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}>  $events
     */
    private function private__sendBatchedAlert(Alert $alert, array $events): void
    {
        $first = $events[0];
        $serviceId = (int) $first['service_id'];
        $jobUuids = \array_values(\array_filter(\array_column($events, 'job_uuid')));

        $log = AlertLog::create([
            'alert_id' => $alert->id,
            'service_id' => $serviceId,
            'trigger_count' => \count($events),
            'job_uuids' => $jobUuids ?: null,
            'status' => 'sent',
            'sent_at' => \now(),
        ]);

        $this->notificationDispatcher->dispatch($alert, $events, $log);
    }
}
