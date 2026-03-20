<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;
use App\Services\HorizonApiProxyService;

final class HorizonOfflineAlertRuleStrategy implements AlertRuleStrategyInterface {

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
            'triggered' => $this->private__evaluateHorizonOffline($alert, $serviceId),
            'job_uuids' => [],
        ];
    }

    /**
     * Evaluate the horizon offline.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function private__evaluateHorizonOffline(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? [];
        $minutes = (int) ($threshold['minutes'] ?? 5);

        $service = Service::find($serviceId);
        if (! $service || ! $service->base_url) {
            return false;
        }

        if ((string) $service->status === 'offline') {
            if (! $service->last_seen_at) {
                return true;
            }

            return $service->last_seen_at->copy()->addMinutes($minutes)->isPast();
        }

        $response = $this->horizonApi->getStats($service);
        $data = $response['data'] ?? null;
        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return false;
        }

        $status = \strtolower((string) ($data['status'] ?? ''));
        if ($status === 'active' || $status === 'running') {
            return false;
        }

        if (! \in_array($status, ['inactive', 'offline', 'paused'], true)) {
            return false;
        }

        $referenceTime = $service->last_seen_at;
        if (! $referenceTime) {
            return true;
        }

        return $referenceTime->copy()->addMinutes($minutes)->isPast();
    }
}
