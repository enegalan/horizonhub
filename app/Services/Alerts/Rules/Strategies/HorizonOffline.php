<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy as AlertRuleContract;
use App\Services\Horizon\HorizonClientService;
use App\Support\Horizon\HorizonStatsReader;

final class HorizonOffline implements AlertRuleContract
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
     * Evaluate the rule and return whether it triggered plus triggering job UUIDs (if applicable).
     *
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId): array
    {
        $service = Service::find($serviceId);

        if ($service === null) {
            $triggered = false;
        } else {
            $data = HorizonStatsReader::dataFromResponse($this->horizonApi->getStats($service));
            $status = HorizonStatsReader::status($data);
            $triggered = $status === null || \strtolower($status) !== 'active' && \strtolower($status) !== 'running';
        }

        return [
            'triggered' => $triggered,
            'job_uuids' => [],
        ];
    }
}
