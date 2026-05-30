<?php

namespace App\Services\Horizon\Contracts;

use App\Models\Service;
use Illuminate\Contracts\Cache\Lock;

interface HorizonClientCache
{
    /**
     * Forget the failure cooldown for a service.
     */
    public function forgetFailureCooldown(Service $service): void;

    /**
     * Get the request path cache.
     */
    public function getRequestPathCache(Service $service, string $path): mixed;

    /**
     * Check if the failure cooldown is set for a service.
     */
    public function hasFailureCooldown(Service $service): bool;

    /**
     * Put the failure cooldown for a service.
     */
    public function putFailureCooldown(Service $service): void;

    /**
     * Put the request path cache.
     *
     * @param array<string, mixed> $result
     */
    public function putRequestPathCache(Service $service, string $path, array $result): void;

    /**
     * Get the request path cache key.
     */
    public function requestPathCacheKey(Service $service, string $path): string;

    /**
     * Get the request path fill lock.
     */
    public function requestPathFillLock(Service $service, string $path): Lock;
}
