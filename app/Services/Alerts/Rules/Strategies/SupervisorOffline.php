<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Enums\AlertRuleType;
use App\Models\Alert;
use App\Models\Service;
use App\Services\Horizon\HorizonClientApiService;
use App\Support\Horizon\ClientResponse;
use App\Support\Horizon\MasterReader;
use App\Support\Jobs\JobRuntime;

final class SupervisorOffline extends AbstractAlertRuleStrategy
{
    /**
     * Get the type.
     */
    public static function type(): AlertRuleType
    {
        return AlertRuleType::SupervisorOffline;
    }

    /**
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId): array
    {
        $service = Service::find($serviceId);

        if ($service === null) {
            return $this->notTriggered();
        }

        $mastersData = ClientResponse::data(HorizonClientApiService::getMasters($service));

        if ($mastersData === null) {
            return $this->notTriggered();
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

        return $this->notTriggered();
    }
}
