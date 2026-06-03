<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Models\AlertLog;
use App\Models\NotificationProvider;

trait BuildsProviderStreams
{
    /**
     * Build the providers streams.
     */
    protected function buildProviders(): string
    {
        $providers = NotificationProvider::query()
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $countsByProviderTypes = AlertLog::query()
            ->selectRaw('notification_providers.type, COUNT(*) as aggregate')
            ->join('alerts', 'alerts.id', '=', 'alert_logs.alert_id')
            ->join('alert_notification_provider', 'alert_notification_provider.alert_id', '=', 'alerts.id')
            ->join('notification_providers', 'notification_providers.id', '=', 'alert_notification_provider.notification_provider_id')
            ->groupBy('notification_providers.type')
            ->pluck('aggregate', 'notification_providers.type');

        $providerTypes = array_keys(NotificationProvider::getProviders());

        $deliveryStats = [
            'total' => AlertLog::query()->count(),
        ];

        foreach ($providerTypes as $providerType) {
            $deliveryStats[$providerType] = $countsByProviderTypes[$providerType] ?? 0;
        }

        return $this->buildStreams([
            ['update', 'turbo-horizon-provider-stats', \view('horizon.providers.partials.index.stats', ['deliveryStats' => $deliveryStats])->render(), 'morph'],
            ['update', 'turbo-tbody-horizon-provider-list', \view('horizon.providers.partials.index.tbody', ['providers' => $providers])->render(), 'morph'],
        ]);
    }
}
