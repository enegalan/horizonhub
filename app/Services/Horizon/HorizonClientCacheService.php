<?php

namespace App\Services\Horizon;

use App\Models\Service;
use App\Support\Http\HttpRetryBackoff;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

class HorizonClientCacheService
{
    /**
     * The number of milliseconds to wait before re-attempting to acquire a lock while blocking.
     */
    private static int $sleepMilliseconds = 250;

    /**
     * Try to acquire a concurrency slot for a service.
     *
     * @param Service $service The service instance.
     *
     * @return bool True when a slot is available, false otherwise.
     */
    public static function acquireServiceRequestSlot(Service $service): bool
    {
        $maxConcurrent = (int) config('horizonhub.horizon_http_max_concurrent_requests_per_service');

        if ($maxConcurrent <= 0) {
            return true;
        }

        $key = self::private__serviceRequestSlotCacheKey($service);
        $waitBudgetMs = (int) config('horizonhub.horizon_http_concurrent_request_wait_ms');
        $deadline = \microtime(true) + ($waitBudgetMs / 1000);

        while (true) {
            Cache::add($key, 0, \now()->addSeconds(self::private__requestLockSeconds()));
            $count = Cache::increment($key);

            if ($count <= $maxConcurrent) {
                return true;
            }

            Cache::decrement($key);

            if (\microtime(true) >= $deadline) {
                return false;
            }

            \usleep(self::$sleepMilliseconds * 1000);
        }
    }

    /**
     * Forget the failure cooldown for a service.
     *
     * @param Service $service The service instance.
     */
    public static function forgetFailureCooldown(Service $service): void
    {
        Cache::forget(self::private__failureCooldownCacheKey($service));
    }

    /**
     * Forget the timeout advice for a service.
     *
     * @param Service $service The service instance.
     */
    public static function forgetTimeoutAdvice(Service $service): void
    {
        Cache::forget(self::timeoutAdviceCacheKey($service));
    }

    /**
     * Get the request path cache.
     *
     * @param Service $service The service instance.
     * @param string $path The path.
     *
     * @return mixed The cached data.
     */
    public static function getRequestPathCache(Service $service, string $path): mixed
    {
        return Cache::get(self::requestPathCacheKey($service, $path));
    }

    /**
     * Check if the failure cooldown is set for a service.
     *
     * @param Service $service The service instance.
     *
     * @return bool True if the failure cooldown is set, false otherwise.
     */
    public static function hasFailureCooldown(Service $service): bool
    {
        return Cache::has(self::private__failureCooldownCacheKey($service));
    }

    /**
     * Put the failure cooldown for a service.
     *
     * @param Service $service The service instance.
     */
    public static function putFailureCooldown(Service $service): void
    {
        $seconds = (int) config('horizonhub.horizon_http_failure_cooldown_seconds');

        if ($seconds > 0) {
            Cache::put(self::private__failureCooldownCacheKey($service), true, \now()->addSeconds($seconds));
        }
    }

    /**
     * Put the request path cache.
     *
     * @param Service $service The service instance.
     * @param string $path The path.
     * @param array $result The result to cache.
     */
    public static function putRequestPathCache(Service $service, string $path, array $result): void
    {
        $ttl = (float) config('horizonhub.hot_reload_interval');

        if ($ttl > 0) {
            Cache::put(self::requestPathCacheKey($service, $path), $result, \now()->addSeconds($ttl));
        }
    }

    /**
     * Put the timeout advice for a service.
     *
     * @param Service $service The service instance.
     */
    public static function putTimeoutAdvice(Service $service): void
    {
        $seconds = (int) config('horizonhub.horizon_http_failure_cooldown_seconds');

        if ($seconds > 0) {
            Cache::put(self::timeoutAdviceCacheKey($service), true, \now()->addSeconds($seconds));
        }
    }

    /**
     * Release a concurrency slot for a service.
     *
     * @param Service $service The service instance.
     */
    public static function releaseServiceRequestSlot(Service $service): void
    {
        $key = self::private__serviceRequestSlotCacheKey($service);
        $count = Cache::decrement($key);

        if ($count <= 0) {
            Cache::forget($key);
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
    public static function requestPathCacheKey(Service $service, string $path): string
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
    public static function requestPathFillLock(Service $service, string $path): Lock
    {
        return Cache::lock(self::requestPathCacheKey($service, $path) . ':fill', self::private__requestLockSeconds());
    }

    /**
     * Get the timeout advice cache key.
     *
     * @param Service $service The service instance.
     *
     * @return string The cache key.
     */
    public static function timeoutAdviceCacheKey(Service $service): string
    {
        return "horizonhub:horizon-api-timeout-advice:{$service->id}";
    }

    /**
     * Get the failure cooldown cache key.
     *
     * @param Service $service The service instance.
     *
     * @return string The cache key.
     */
    private static function private__failureCooldownCacheKey(Service $service): string
    {
        return "horizonhub:horizon-api-failure-cooldown:{$service->id}";
    }

    /**
     * Compute how many seconds a lock entry should live.
     *
     * @return int The lock TTL in seconds.
     */
    private static function private__requestLockSeconds(): int
    {
        $timeout = (int) config('horizonhub.api_timeout');
        $retryTimes = max(1, (int) config('horizonhub.horizon_http_retry.times'));

        $baseSeconds = max($timeout, $timeout * $retryTimes);

        $backoffMs = 0;

        for ($attempt = 1; $attempt < $retryTimes; $attempt++) {
            $backoffMs += HttpRetryBackoff::delayMsForAttempt($attempt);
        }

        // Small margin so the lock outlives cleanup after the last attempt.
        $backoffSeconds = (int) \ceil($backoffMs / 1000);

        return $baseSeconds + $backoffSeconds + 1;
    }

    /**
     * Get the service request slot cache key.
     *
     * @param Service $service The service instance.
     *
     * @return string The cache key.
     */
    private static function private__serviceRequestSlotCacheKey(Service $service): string
    {
        return "horizonhub:horizon-api-service-slot:{$service->id}";
    }
}
