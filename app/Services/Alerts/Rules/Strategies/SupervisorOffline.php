<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy as AlertRuleContract;
use App\Services\Horizon\HorizonClientService;
use App\Services\Jobs\JobRuntimeHelperService;
use App\Support\Horizon\ClientResponse;

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
        $service = Service::find($serviceId);

        if ($service === null) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $mastersData = ClientResponse::data($this->horizonApi->getMasters($service));

        if ($mastersData === null) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $staleAt = \now()->subMinutes($alert->getThresholdMinutes());
        $staleFound = false;

        foreach ($mastersData as $master) {
            if (! \is_array($master)) {
                continue;
            }

            $supervisorsData = $master['supervisors'] ?? null;

            if (! \is_array($supervisorsData)) {
                continue;
            }

            foreach ($supervisorsData as $supervisor) {
                if (! \is_array($supervisor)) {
                    continue;
                }

                // TO-DEPURATE: last_heartbeat_at or lastSeen?
                $lastSeenRaw = $supervisor['last_heartbeat_at'] ?? ($supervisor['lastSeen'] ?? null);
                $lastSeen = JobRuntimeHelperService::parseJobTimestamp($lastSeenRaw);

                if ($lastSeen !== null && $lastSeen->lt($staleAt)) {
                    $staleFound = true;
                    break;
                }
            }
        }

        return [
            'triggered' => $staleFound,
            'job_uuids' => [],
        ];
    }
}
