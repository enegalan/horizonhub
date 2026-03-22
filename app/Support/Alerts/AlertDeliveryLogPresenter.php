<?php

namespace App\Support\Alerts;

use App\Models\AlertLog;
use App\Support\Horizon\ConfigHelper;

class AlertDeliveryLogPresenter
{
    /**
     * Get the payload for the delivery log modal.
     *
     * @return array<string, mixed>|null
     */
    public static function payloadFromLog(?AlertLog $log): ?array
    {
        if ($log === null) {
            return null;
        }

        $initialTriggerCount = (int) ($log->trigger_count ?? 0);
        if ($initialTriggerCount < 1) {
            $initialTriggerCount = 1;
        }
        $initialJobUuids = \is_array($log->job_uuids ?? null) ? $log->job_uuids : [];
        $initialJobTotals = [];
        foreach ($initialJobUuids as $initialUuid) {
            $initialJobKey = (string) $initialUuid;
            $initialJobTotals[$initialJobKey] = ($initialJobTotals[$initialJobKey] ?? 0) + 1;
        }
        $initialUniqueJobTypesCount = \count($initialJobTotals);
        $initialEffectiveJobTypesCount = \min($initialUniqueJobTypesCount, $initialTriggerCount);
        $maxDistinctJobs = ConfigHelper::getIntWithMin('horizonhub.alerts.delivery_log_max_distinct_jobs', $initialEffectiveJobTypesCount);
        $initialJobItems = [];
        foreach (\array_slice(\array_keys($initialJobTotals), 0, $initialVisibleJobTypesLimit) as $initialJobId) {
            $initialJobItems[] = [
                'id' => $initialJobId,
                'count' => (int) $initialJobTotals[$initialJobId],
            ];
        }

        return [
            'sent_at' => $log->sent_at?->format('Y-m-d H:i:s') ?? '–',
            'service_name' => $log->service?->name ?? '–',
            'events_text' => $initialTriggerCount === 1 ? '1 event' : "$initialTriggerCount events",
            'events_count' => $initialTriggerCount,
            'status' => (string) ($log->status ?? ''),
            'failure_message' => (string) ($log->failure_message ?? ''),
            'job_items' => $initialJobItems,
            'job_ids_more' => \max(0, $initialEffectiveJobTypesCount - $maxDistinctJobs),
        ];
    }
}
