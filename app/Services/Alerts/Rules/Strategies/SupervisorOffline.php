<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Enums\AlertRuleType;
use App\Enums\HorizonStatus;
use App\Models\Alert;
use App\Models\Service;
use App\Services\Horizon\HorizonClientApiService;
use App\Support\Horizon\ClientResponse;
use App\Support\Horizon\MasterReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class SupervisorOffline extends AbstractAlertRuleStrategy
{
    private const CACHE_KEY_PREFIX = 'supervisor_offline_since:';

    private const CACHE_TTL_MARGIN_MINUTES = 60;

    /**
     * Get the type.
     */
    public static function type(): AlertRuleType
    {
        return AlertRuleType::SupervisorOffline;
    }

    /**
     * Evaluate the rule and return whether it triggered plus triggering job UUIDs.
     *
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

        $cacheTtlSeconds = ($alert->getThresholdMinutes() + self::CACHE_TTL_MARGIN_MINUTES) * 60;
        $triggered = false;

        foreach (MasterReader::eachSupervisor($mastersData) as $supervisor) {
            $identity = isset($supervisor['name']) && (string) $supervisor['name'] !== ''
            ? (string) $supervisor['name']
            : 'unnamed';

            $cacheKey = self::CACHE_KEY_PREFIX . $serviceId . ':' . $identity;

            if (! isset($supervisor['status']) || (string) $supervisor['status'] !== HorizonStatus::Inactive->value) {
                Cache::forget($cacheKey);

                continue;
            }

            $offlineSinceTimestamp = Cache::get($cacheKey);

            if (! \is_numeric($offlineSinceTimestamp)) {
                Cache::put($cacheKey, \now()->getTimestamp(), $cacheTtlSeconds);

                continue;
            }

            if (\now()->gte(Carbon::createFromTimestamp((int) $offlineSinceTimestamp)
                ->addMinutes($alert->getThresholdMinutes()))) {
                $triggered = true;

                break;
            }
        }

        return [
            'triggered' => $triggered,
            'job_uuids' => [],
        ];
    }
}
