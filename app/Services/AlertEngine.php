<?php

namespace App\Services;

use App\Services\Alerts\AlertBatchStore;
use App\Services\Alerts\AlertNotificationDispatcher;
use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\Service;
use Illuminate\Support\Facades\Log;

class AlertEngine {

    /**
     * The rule evaluator.
     *
     * @var AlertRuleEvaluator
     */
    private AlertRuleEvaluator $ruleEvaluator;

    /**
     * The batch store.
     *
     * @var AlertBatchStore
     */
    private AlertBatchStore $batchStore;

    /**
     * The notification dispatcher.
     *
     * @var AlertNotificationDispatcher
     */
    private AlertNotificationDispatcher $notificationDispatcher;

    /**
     * Construct the alert engine.
     *
     * @param AlertRuleEvaluator $ruleEvaluator
     * @param AlertBatchStore $batchStore
     * @param AlertNotificationDispatcher $notificationDispatcher
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
     *
     * @return void
     */
    public function flushPendingAlerts(): void {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Alert> $alerts */
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
     *
     * @return void
     */
    public function evaluateScheduled(): void {
        $this->flushPendingAlerts();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Alert> $alerts */
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
     * Retry the alert log.
     *
     * @param AlertLog $log
     * @return void
     */
    public function retryAlertLog(AlertLog $log): void {
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
     *
     * @param Alert $alert
     * @param string $eventType
     * @return bool
     */
    private function private__shouldEvaluate(Alert $alert, string $eventType): bool {
        if ($alert->rule_type === Alert::RULE_JOB_SPECIFIC_FAILURE && $eventType !== 'JobFailed') {
            return false;
        }
        if ($alert->rule_type === Alert::RULE_JOB_TYPE_FAILURE && $eventType !== 'JobFailed') {
            return false;
        }
        if ($alert->rule_type === Alert::RULE_FAILURE_COUNT && $eventType !== 'JobFailed') {
            return false;
        }
        if (\in_array($alert->rule_type, [
            Alert::RULE_QUEUE_BLOCKED,
            Alert::RULE_WORKER_OFFLINE,
            Alert::RULE_SUPERVISOR_OFFLINE,
        ], true)) {
            return false;
        }
        return true;
    }

    /**
     * Evaluate the rule and return triggering job UUIDs when applicable.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    private function private__evaluateRuleWithJobs(Alert $alert, int $serviceId, ?string $jobUuid): array {
        return $this->ruleEvaluator->evaluateWithTriggeringJobs($alert, $serviceId, $jobUuid);
    }

    /**
     * Rebuild the events from the log.
     *
     * @param AlertLog $log
     * @description This method reconstructs alert trigger events from a stored log.
     *              It uses the current time for all reconstructed events and will
     *              generate placeholder events with null job UUIDs when the
     *              trigger_count is greater than the number of stored job UUIDs.
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
     * Trigger the alert.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @param array<int, string> $triggeringJobUuids
     * @return void
     */
    private function private__triggerAlert(Alert $alert, int $serviceId, ?string $jobUuid, array $triggeringJobUuids = []): void {
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
            'trigger_count' => \count($events),
            'job_uuids' => $jobUuids ?: null,
            'status' => 'sent',
            'sent_at' => \now(),
        ]);

        $this->notificationDispatcher->dispatch($alert, $events, $log);
    }
}
