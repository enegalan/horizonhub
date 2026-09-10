<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy as AlertRuleContract;
use App\Services\Horizon\HorizonClientApiService;
use App\Support\Horizon\ClientResponse;
use App\Support\Horizon\MasterReader;
use App\Support\Jobs\JobRuntime;

final class SupervisorOffline implements AlertRuleContract
{
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

        $mastersData = ClientResponse::data(HorizonClientApiService::getMasters($service));

        if ($mastersData === null) {
            return ['triggered' => false, 'job_uuids' => []];
        }

        $staleAt = \now()->subMinutes($alert->getThresholdMinutes());

        foreach (MasterReader::eachSupervisor($mastersData) as $supervisor) {
            // TO-DEPURATE: last_heartbeat_at or lastSeen?
            $lastSeen = JobRuntime::parseJobTimestamp(
                $supervisor['last_heartbeat_at'] ?? ($supervisor['lastSeen'] ?? null),
            );

            if ($lastSeen !== null && $lastSeen->lt($staleAt)) {
                return [
                    'triggered' => true,
                    'job_uuids' => [],
                ];
            }
        }

        return ['triggered' => false, 'job_uuids' => []];
    }
}
