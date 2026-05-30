<?php

namespace App\Services\Horizon\Contracts;

use App\Models\Service;

interface HorizonHttpClient
{
    /**
     * Call the Horizon HTTP API for a service.
     *
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function call(
        Service $service,
        string $path,
        string $method = 'post',
        bool $withDashboardSession = false,
        bool $bypassFailureCooldown = false,
        bool $allowWhenDisabled = false,
    ): array;
}
