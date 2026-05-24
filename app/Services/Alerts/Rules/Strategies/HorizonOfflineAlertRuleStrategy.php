<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;
use App\Services\Horizon\HorizonApiProxyService;
use App\Support\Alerts\AlertRuleStrategySupport;
use App\Support\Horizon\HorizonStatsReader;

final class HorizonOfflineAlertRuleStrategy implements AlertRuleStrategyInterface
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
     * Evaluate the rule and return whether it triggered plus triggering job UUIDs (if applicable).
     *
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId): array
    {
        return [
            'triggered' => $this->private__evaluateHorizonOffline($serviceId),
            'job_uuids' => [],
        ];
    }

    private function private__evaluateHorizonOffline(int $serviceId): bool
    {
        $service = $this->private__resolveServiceForEvaluation($serviceId);

        if ($service === null) {
            return false;
        }

        $data = HorizonStatsReader::dataFromResponse($this->horizonApi->getStats($service));

        if ($data === null) {
            return true;
        }

        $status = \strtolower((string) (HorizonStatsReader::status($data) ?? ''));

        if ($status === 'active' || $status === 'running') {
            return false;
        }

        return true;
    }
}
