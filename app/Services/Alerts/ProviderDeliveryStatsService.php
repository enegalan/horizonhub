<?php

namespace App\Services\Alerts;

use App\Models\AlertLog;
use App\Models\NotificationProvider;

class ProviderDeliveryStatsService
{
    /**
     * Count emitted alert deliveries grouped by notification provider type.
     *
     * @return array{total: int, slack: int, email: int}
     */
    public function countsByProviderType(): array
    {
        return [
            'total' => AlertLog::query()->count(),
            'slack' => $this->private__countForProviderType(NotificationProvider::TYPE_SLACK),
            'email' => $this->private__countForProviderType(NotificationProvider::TYPE_EMAIL),
        ];
    }

    /**
     * Count alert deliveries that used at least one provider of the given type.
     */
    private function private__countForProviderType(string $providerType): int
    {
        return AlertLog::query()
            ->whereHas('alert.notificationProviders', static function ($query) use ($providerType): void {
                $query->where('type', $providerType);
            })
            ->count();
    }
}
