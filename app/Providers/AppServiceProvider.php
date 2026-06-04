<?php

namespace App\Providers;

use App\Contracts\HorizonHubStore as HorizonHubStoreContract;
use App\Services\Horizon\Contracts\HorizonClientApi;
use App\Services\Horizon\HorizonClientService;
use App\Services\Horizon\MockHorizonClientService;
use App\Support\HorizonHub\HorizonHubStore;
use App\Support\HorizonHub\MockHorizonHubStore;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        if (config('horizonhub.mock')) {
            $this->app->singleton(HorizonClientApi::class, MockHorizonClientService::class);
            $this->app->singleton(HorizonHubStoreContract::class, MockHorizonHubStore::class);
        } else {
            $this->app->singleton(HorizonClientApi::class, HorizonClientService::class);
            $this->app->singleton(HorizonHubStoreContract::class, HorizonHubStore::class);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (! config('horizonhub.mock')) {
            return;
        }

        Route::bind('service', fn (string $value) => $this->app->make(HorizonHubStoreContract::class)->findServiceOrFail($value));
        Route::bind('alert', fn (string $value) => $this->app->make(HorizonHubStoreContract::class)->findAlertOrFail($value));
        Route::bind('provider', fn (string $value) => $this->app->make(HorizonHubStoreContract::class)->findNotificationProviderOrFail($value));
        Route::bind('log', fn (string $value) => $this->app->make(HorizonHubStoreContract::class)->findAlertLogOrFail($value));
    }
}
