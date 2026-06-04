<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Models\NotificationProvider;

trait BuildsProviderStreams
{
    /**
     * Build the providers streams.
     */
    protected function buildProviders(): string
    {
        $providers = $this->store->providersOrdered();
        $countsByProviderTypes = $this->store->alertLogCountsByProviderType();

        $deliveryStats = [
            'total' => $this->store->alertLogTotalCount(),
        ];

        foreach (\array_keys(NotificationProvider::getProviders()) as $providerType) {
            $deliveryStats[$providerType] = $countsByProviderTypes[$providerType] ?? 0;
        }

        return $this->buildStreams([
            ['update', 'turbo-horizon-provider-stats', \view('horizon.providers.partials.index.stats', ['deliveryStats' => $deliveryStats])->render(), 'morph'],
            ['update', 'turbo-tbody-horizon-provider-list', \view('horizon.providers.partials.index.tbody', ['providers' => $providers])->render(), 'morph'],
        ]);
    }
}
