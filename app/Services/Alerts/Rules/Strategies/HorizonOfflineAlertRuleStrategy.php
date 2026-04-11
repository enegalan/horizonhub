<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;
use App\Services\Horizon\HorizonApiProxyService;

final class HorizonOfflineAlertRuleStrategy implements AlertRuleStrategyInterface
{
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
     * Evaluate the rule and return whether it triggered plus triggering job UUIDs (if applicable).
     *
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId, ?string $jobUuid): array
    {
        return [
            'triggered' => $this->private__evaluateHorizonOffline($serviceId),
            'job_uuids' => [],
        ];
    }

    /**
     * Evaluate the horizon offline.
     * 
     * @param int $serviceId
     * @return bool
     */
    private function private__evaluateHorizonOffline(int $serviceId): bool
    {
        $service = Service::find($serviceId);
        if (! $service || ! $service->base_url) {
            return false;
        }

        $response = $this->horizonApi->getStats($service);
        $data = $response['data'] ?? null;
        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return true;
        }

        $status = \strtolower((string) ($data['status'] ?? ''));
        if ($status === 'active' || $status === 'running') {
            return false;
        }

        return true;
    }
}
