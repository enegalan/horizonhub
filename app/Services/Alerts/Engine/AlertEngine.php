<?php

namespace App\Services\Alerts\Engine;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\Service;
use App\Services\Alerts\Rules\AlertRuleStrategyRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class AlertEngine
{
    /**
     * The batch store.
     */
    private AlertBatchStore $batchStore;

    /**
     * The notification dispatcher.
     */
    private AlertNotificationDispatcher $notificationDispatcher;

    /**
     * The rule strategy registry.
     */
    private AlertRuleStrategyRegistry $ruleStrategyRegistry;

    /**
     * Construct the alert engine.
     */
    public function __construct(AlertBatchStore $batchStore, AlertNotificationDispatcher $notificationDispatcher, AlertRuleStrategyRegistry $ruleStrategyRegistry)
    {
        $this->batchStore = $batchStore;
        $this->notificationDispatcher = $notificationDispatcher;
        $this->ruleStrategyRegistry = $ruleStrategyRegistry;
    }

    /**
     * Evaluate a single alert immediately (scheduled context).
     *
     * @param Alert $alert The alert.
     *
     * @return array{
     *     alert_id: int,
     *     pending_flushed: bool,
     *     triggered: bool,
     *     triggered_service_id: int|null,
     *     error_message: string|null,
     *     pending_flush_error_message: string|null,
     *     delivered: bool,
     *     delivered_check_error_message: string|null
     * }
     */
    public function evaluateAlert(Alert $alert): array
    {
        $pendingFlushed = false;
        $triggered = false;
        $triggeredServiceId = null;
        $errorMessage = null;
        $pendingFlushErrorMessage = null;
        $delivered = false;
        $deliveredCheckErrorMessage = null;
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
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
            $pendingFlushErrorMessage = $e->getMessage();
        }

        try {
            /** @var array<int, int> $serviceIds */
            $serviceIds = $alert->service_ids;

            if (empty($serviceIds)) {
                $serviceIds = Service::query()->enabled()->pluck('id')->all();
            } else {
                $serviceIds = Service::query()
                    ->enabled()
                    ->whereIn('id', $serviceIds)
                    ->pluck('id')
                    ->all();
            }

            if (empty($serviceIds)) {
                $errorMessage = 'No enabled services to evaluate alert (enable at least one service).';

                return [
                    'alert_id' => $alert->id,
                    'pending_flushed' => $pendingFlushed,
                    'triggered' => false,
                    'triggered_service_id' => null,
                    'error_message' => $errorMessage,
                    'pending_flush_error_message' => $pendingFlushErrorMessage,
                    'delivered' => false,
                    'delivered_check_error_message' => null,
                ];
            }

            foreach ($serviceIds as $serviceId) {
                $result = $this->evaluateWithTriggeringJobs($alert, (int) $serviceId, null);

                if ($result['triggered']) {
                    $this->private__triggerAlert($alert, (int) $serviceId, null, $result['job_uuids']);
                    $triggered = true;
                    $triggeredServiceId = (int) $serviceId;
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::error('Horizon Hub: evaluate alert failed', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
            $errorMessage = $e->getMessage();
        }

        try {
            $lastSentAtAfter = $this->batchStore->getLastSentAt($alert);
            $delivered = $lastSentAtAfter !== null && ($lastSentAtBefore === null || ! $lastSentAtAfter->eq($lastSentAtBefore));
        } catch (\Throwable $e) {
            Log::error('Horizon Hub: evaluate alert failed while checking delivery', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
            $deliveredCheckErrorMessage = $e->getMessage();
        }

        return [
            'alert_id' => $alert->id,
            'pending_flushed' => $pendingFlushed,
            'triggered' => $triggered,
            'triggered_service_id' => $triggered ? $triggeredServiceId : null,
            'error_message' => $errorMessage,
            'pending_flush_error_message' => $pendingFlushErrorMessage,
            'delivered' => $delivered,
            'delivered_check_error_message' => $deliveredCheckErrorMessage,
        ];
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
        $enabledServiceIds = Service::query()->enabled()->pluck('id')->all();

        foreach ($alerts as $alert) {
            try {
                $serviceIds = $alert->service_ids;

                if (empty($serviceIds)) {
                    $serviceIds = $enabledServiceIds;
                } else {
                    $serviceIds = \array_values(\array_intersect(
                        $serviceIds,
                        $enabledServiceIds,
                    ));
                }

                if (empty($serviceIds)) {
                    Log::warning('Horizon Hub: no enabled services to evaluate alert', ['alert_id' => $alert->id]);

                    continue;
                }

                foreach ($serviceIds as $serviceId) {
                    $result = $this->evaluateWithTriggeringJobs($alert, $serviceId, null);

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
     * Evaluate the alert rule and return whether it triggered plus the list of job UUIDs that triggered it.
     *
     * @param Alert $alert The alert.
     * @param int $serviceId The service ID.
     * @param string|null $jobUuid The job UUID.
     *
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId, ?string $jobUuid): array
    {
        $strategy = $this->ruleStrategyRegistry->resolve((string) $alert->rule_type);

        return $strategy->evaluateWithTriggeringJobs($alert, $serviceId, $jobUuid);
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

                if (empty($pending) || ! $this->batchStore->shouldSendNow($alert)) {
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
     * Retry the alert log.
     *
     * @param AlertLog $log The alert log.
     */
    public function retryAlertLog(AlertLog $log): void
    {
        $alert = $log->alert;

        if (! $alert instanceof Alert || $log->status !== 'failed') {
            return;
        }
        $events = $this->private__rebuildEventsFromLog($log);

        if (empty($events)) {
            return;
        }
        $jobUuids = \array_values(\array_filter(\array_column($events, 'job_uuid')));

        $newLog = AlertLog::create([
            'alert_id' => $alert->id,
            'service_id' => (int) $log->service_id,
            'trigger_count' => \count($events),
            'job_uuids' => ! empty($jobUuids) ? $jobUuids : null,
            'status' => 'sent',
            'failure_message' => null,
            'sent_at' => \now(),
        ]);

        $this->notificationDispatcher->dispatch($alert, $events, $newLog);
    }

    /**
     * Rebuild the events from the log.
     *
     * @description This method reconstructs alert trigger events from a stored log.
     *              It uses the current time for all reconstructed events and will
     *              generate placeholder events with null job UUIDs when the
     *              trigger_count is greater than the number of stored job UUIDs.
     *
     * @param AlertLog $log The alert log.
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
     * Send the batched alert.
     *
     * @param Alert $alert The alert.
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events
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

    /**
     * Trigger the alert.
     *
     * @param Alert $alert The alert.
     * @param int $serviceId The service ID.
     * @param string|null $jobUuid The job UUID.
     * @param array<int, string> $triggeringJobUuids
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
}
