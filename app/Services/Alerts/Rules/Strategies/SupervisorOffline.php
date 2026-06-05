<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Contracts\HorizonHubStore;
use App\Models\Alert;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy as AlertRuleContract;
use App\Services\Horizon\Contracts\HorizonClientApi;
use App\Support\Horizon\HorizonMastersReader;

final class SupervisorOffline implements AlertRuleContract
{
    /**
     * The horizon API client.
     */
    private HorizonClientApi $horizonApi;

    /**
     * The horizon hub store.
     */
    private HorizonHubStore $store;

    /**
     * The constructor.
     *
     * @param HorizonHubStore $store The horizon hub store.
     */
    public function __construct(HorizonClientApi $horizonApi, HorizonHubStore $store)
    {
        $this->horizonApi = $horizonApi;
        $this->store = $store;
    }

    /**
     * Get the type.
     */
    public static function type(): string
    {
        return 'supervisor_offline';
    }

    /**
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId): array
    {
        $service = $this->store->findService($serviceId);

        if ($service === null) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $response = $this->horizonApi->getMasters($service);
        $data = $response['data'] ?? null;

        if (! $response['success'] || ! \is_array($data)) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $staleAt = \now()->subMinutes($alert->getThresholdMinutes());
        $staleFound = HorizonMastersReader::hasStaleSupervisorHeartbeat($data, $staleAt);

        return [
            'triggered' => $staleFound,
            'job_uuids' => [],
        ];
    }
}
