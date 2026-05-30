<?php

namespace App\Services\Horizon\Concerns;

use App\Models\Service;
use App\Services\Horizon\Contracts\HorizonClientCache as HorizonClientCacheContract;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

class HorizonClientCache implements HorizonClientCacheContract
{
    /**
     * Forget the failure cooldown for a service.
     *
     * @param Service $service The service instance.
     */
    public function forgetFailureCooldown(Service $service): void
    {
        Cache::forget($this->private__failureCooldownCacheKey($service));
    }

    /**
     * Get the request path cache.
     *
     * @param Service $service The service instance.
     * @param string $path The path.
     *
     * @return mixed The cached data.
     */
    public function getRequestPathCache(Service $service, string $path): mixed
    {
        return Cache::get($this->requestPathCacheKey($service, $path));
    }

    /**
     * Check if the failure cooldown is set for a service.
     *
     * @param Service $service The service instance.
     *
     * @return bool True if the failure cooldown is set, false otherwise.
     */
    public function hasFailureCooldown(Service $service): bool
    {
        return Cache::has($this->private__failureCooldownCacheKey($service));
    }

    /**
     * Put the failure cooldown for a service.
     *
     * @param Service $service The service instance.
     */
    public function putFailureCooldown(Service $service): void
    {
        $seconds = (int) config('horizonhub.horizon_http_failure_cooldown_seconds');

        if ($seconds > 0) {
            Cache::put($this->private__failureCooldownCacheKey($service), true, \now()->addSeconds($seconds));
        }
    }

    /**
     * Put the request path cache.
     *
     * @param Service $service The service instance.
     * @param string $path The path.
     * @param array $result The result to cache.
     */
    public function putRequestPathCache(Service $service, string $path, array $result): void
    {
        $ttl = (float) config('horizonhub.hot_reload_interval');

        if ($ttl > 0) {
            Cache::put($this->requestPathCacheKey($service, $path), $result, \now()->addSeconds($ttl));
        }
    }

    /**
     * Get the request path cache key.
     *
     * @param Service $service The service instance.
     * @param string $path The path.
     *
     * @return string The cache key.
     */
    public function requestPathCacheKey(Service $service, string $path): string
    {
        return "horizonhub:horizon-api-hot-reload-path:{$service->id}:$path";
    }

    /**
     * Get the request path fill lock.
     *
     * @param Service $service The service instance.
     * @param string $path The path.
     *
     * @return Lock The lock.
     */
    public function requestPathFillLock(Service $service, string $path): Lock
    {
        $lockSeconds = (int) config('horizonhub.api_timeout');

        return Cache::lock("{$this->requestPathCacheKey($service, $path)}:fill", $lockSeconds);
    }

    /**
     * Get the failure cooldown cache key.
     *
     * @param Service $service The service instance.
     *
     * @return string The cache key.
     */
    private function private__failureCooldownCacheKey(Service $service): string
    {
        return "horizonhub:horizon-api-failure-cooldown:{$service->id}";
    }
}
