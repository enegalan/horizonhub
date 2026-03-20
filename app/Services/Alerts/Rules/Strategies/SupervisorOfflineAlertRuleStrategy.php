<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;
use App\Services\HorizonApiProxyService;
use Carbon\Carbon;

final class SupervisorOfflineAlertRuleStrategy implements AlertRuleStrategyInterface {

    /**
     * The Horizon API proxy service.
     *
     * @var HorizonApiProxyService
     */
    private HorizonApiProxyService $horizonApi;

    /**
     * Construct the strategy.
     *
     * @param HorizonApiProxyService $horizonApi
     */
    public function __construct(HorizonApiProxyService $horizonApi) {
        $this->horizonApi = $horizonApi;
    }

    /**
     * Evaluate the rule and return whether it triggered plus triggering job UUIDs (if applicable).
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId, ?string $jobUuid): array {
        return [
            'triggered' => $this->private__evaluateSupervisorOffline($alert, $serviceId),
            'job_uuids' => [],
        ];
    }

    /**
     * Evaluate the supervisor offline.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function private__evaluateSupervisorOffline(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? [];
        $minutes = (int) ($threshold['minutes'] ?? 5);

        $service = Service::find($serviceId);
        if (! $service || ! $service->base_url) {
            return false;
        }

        $response = $this->horizonApi->getMasters($service);
        $data = $response['data'] ?? null;

        if (! ($response['success'] ?? false) || ! \is_array($data)) {
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
                if (! \is_string($lastSeenRaw) || $lastSeenRaw === '') {
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
