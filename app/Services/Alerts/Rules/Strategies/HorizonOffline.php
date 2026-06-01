<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy as AlertRuleContract;
use App\Services\Horizon\HorizonClientService;
use App\Support\Horizon\HorizonStatsReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class HorizonOffline implements AlertRuleContract
{
    private const CACHE_KEY_PREFIX = 'horizon_offline_since:';

    private const CACHE_TTL_MARGIN_MINUTES = 60;

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
     * Get the type.
     */
    public static function type(): string
    {
        return 'horizon_offline';
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
            return ['triggered' => false, 'job_uuids' => []];
        }

        $data = HorizonStatsReader::dataFromResponse($this->horizonApi->getStats($service));
        $status = HorizonStatsReader::status($data);
        $isOnline = $status !== null
            && (\strtolower($status) === 'active' || \strtolower($status) === 'running');

        $cacheKey = self::CACHE_KEY_PREFIX . $serviceId;

        if ($isOnline) {
            Cache::forget($cacheKey);

            return ['triggered' => false, 'job_uuids' => []];
        }

        $offlineSinceTimestamp = Cache::get($cacheKey);

        if (! \is_numeric($offlineSinceTimestamp)) {
            $cacheTtlSeconds = ($alert->getThresholdMinutes() + self::CACHE_TTL_MARGIN_MINUTES) * 60;
            Cache::put($cacheKey, \now()->getTimestamp(), $cacheTtlSeconds);

            return ['triggered' => false, 'job_uuids' => []];
        }

        $offlineSinceTimestamp = (int) $offlineSinceTimestamp;
        $graceEndsAt = Carbon::createFromTimestamp($offlineSinceTimestamp)
            ->addMinutes($alert->getThresholdMinutes());
        $triggered = \now()->gte($graceEndsAt);

        return [
            'triggered' => $triggered,
            'job_uuids' => [],
        ];
    }
}
