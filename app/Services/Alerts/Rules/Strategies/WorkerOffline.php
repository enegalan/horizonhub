<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Contracts\HorizonHubStore;
use App\Models\Alert;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy as AlertRuleContract;

final class WorkerOffline implements AlertRuleContract
{
    /**
     * The horizon hub store.
     */
    private HorizonHubStore $store;

    /**
     * The constructor.
     *
     * @param HorizonHubStore $store The horizon hub store.
     */
    public function __construct(HorizonHubStore $store)
    {
        $this->store = $store;
    }

    /**
     * Get the type.
     */
    public static function type(): string
    {
        return 'worker_offline';
    }

    /**
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId): array
    {
        $service = $this->store->findService($serviceId);

        $triggered = false;

        if ($service?->last_seen_at) {
            $triggered = $service->last_seen_at->copy()->addMinutes($alert->getThresholdMinutes())->isPast();
        }

        return [
            'triggered' => $triggered,
            'job_uuids' => [],
        ];
    }
}
