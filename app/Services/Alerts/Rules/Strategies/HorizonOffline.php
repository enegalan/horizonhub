<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy as AlertRuleContract;
use App\Services\Horizon\HorizonClientService;
use App\Support\Alerts\AlertRuleStrategy;
use App\Support\Horizon\HorizonStatsReader;

final class HorizonOffline implements AlertRuleContract
{
    use AlertRuleStrategy;

    /**
     * The Horizon API client.
     */
    private HorizonClientService $horizonApi;

    /**
     * Construct the strategy.
     */
    public function __construct(HorizonClientService $horizonApi)
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
        $service = Service::find($serviceId);

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
