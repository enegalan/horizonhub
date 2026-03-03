<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void {
        RateLimiter::for('hub-events', function (Request $request): Limit {
            $key = $request->header('X-Api-Key') ?: $request->ip();
            return Limit::perMinute(config('horizon_hub.events_rate_limit', 2000))->by($key);
        });
    }
}
