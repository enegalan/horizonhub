<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy as AlertRuleContract;
use App\Services\Horizon\HorizonClientService;
use App\Support\Horizon\HorizonMastersReader;

final class SupervisorOffline implements AlertRuleContract
{
    /**
     * The Horizon API client.
     */
    private HorizonClientService $horizonApi;

    /**
     * The constructor.
     *
     * @param HorizonClientService $horizonApi The Horizon API client.
     */
    public function __construct(HorizonClientService $horizonApi)
    {
        $this->horizonApi = $horizonApi;
    }

    /**
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId): array
    {
        $service = Service::find($serviceId);

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
