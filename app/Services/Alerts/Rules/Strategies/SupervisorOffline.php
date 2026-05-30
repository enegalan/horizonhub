<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy as AlertRuleContract;
use App\Services\Horizon\HorizonClientService;
use Carbon\Carbon;

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
        $staleFound = false;

        foreach ($data as $master) {
            if (! \is_array($master) || ! isset($master['supervisors']) || ! \is_array($master['supervisors'])) {
                continue;
            }

            foreach ($master['supervisors'] as $supervisor) {
                if (! \is_array($supervisor)) {
                    continue;
                }
                // TODO: Depurate this.
                $lastSeenRaw = $supervisor['last_heartbeat_at'] ?? ($supervisor['lastSeen'] ?? null);

                if (blank($lastSeenRaw)) {
                    continue;
                }

                try {
                    $lastSeen = Carbon::parse($lastSeenRaw);
                } catch (\Throwable $e) {
                    continue;
                }

                if ($lastSeen->lt($staleAt)) {
                    $staleFound = true;
                    break 2;
                }
            }
        }

        return [
            'triggered' => $staleFound,
            'job_uuids' => [],
        ];
    }
}
