<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;
use App\Services\Horizon\HorizonApiProxyService;
use App\Support\Alerts\AlertRuleStrategySupport;
use Carbon\Carbon;

final class SupervisorOfflineAlertRuleStrategy implements AlertRuleStrategyInterface
{
    use AlertRuleStrategySupport;

    /**
     * The Horizon API proxy service.
     */
    private HorizonApiProxyService $horizonApi;

    /**
     * Construct the strategy.
     */
    public function __construct(HorizonApiProxyService $horizonApi)
    {
        $this->horizonApi = $horizonApi;
    }

    /**
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId): array
    {
        return [
            'triggered' => $this->private__evaluateSupervisorOffline($alert, $serviceId),
            'job_uuids' => [],
        ];
    }

    private function private__evaluateSupervisorOffline(Alert $alert, int $serviceId): bool
    {
        $minutes = $this->private__thresholdMinutes($alert);
        $service = $this->private__resolveServiceForEvaluation($serviceId);

        if ($service === null) {
            return false;
        }

        $response = $this->horizonApi->getMasters($service);
        $data = $response['data'] ?? null;

        if (! $response['success'] || ! \is_array($data)) {
            return false;
        }

        $staleAt = \now()->subMinutes($minutes);
        $staleFound = false;

        foreach ($data as $master) {
            if (! \is_array($master) || ! isset($master['supervisors']) || ! \is_array($master['supervisors'])) {
                continue;
            }

            foreach ($master['supervisors'] as $supervisor) {
                if (! \is_array($supervisor)) {
                    continue;
                }
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

        return $staleFound;
    }
}
