<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        RateLimiter::for('horizon-stream', function (Request $request): Limit {
            $limit = (int) config('horizonhub.stream_rate_limit');

            return Limit::perMinute($limit)->by($request->user()?->id ?: $request->ip());
        });
    }
}
