<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Models\NotificationProvider;

trait BuildsProviderStreams
{
    private function private__buildProvidersStreams(): string
    {
        $providers = NotificationProvider::query()
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $deliveryStats = $this->alertIndexStreamData->countsByProviderType();

        return $this->buildStreams([
            ['update', 'turbo-horizon-provider-stats', \view('horizon.providers.partials.index.stats', ['deliveryStats' => $deliveryStats])->render(), 'morph'],
            ['update', 'turbo-tbody-horizon-provider-list', \view('horizon.providers.partials.index.tbody', ['providers' => $providers])->render(), 'morph'],
        ]);
    }
}
