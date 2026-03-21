<?php

namespace App\Providers;

use App\Contracts\EmailAlertNotifier;
use App\Contracts\SlackAlertNotifier;
use App\Services\Notifiers\EmailNotifier;
use App\Services\Notifiers\SlackNotifier;
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
        $this->app->bind(EmailAlertNotifier::class, EmailNotifier::class);
        $this->app->bind(SlackAlertNotifier::class, SlackNotifier::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        RateLimiter::for('hub-events', function (Request $request): Limit {
            $key = $request->header('X-Api-Key') ?: $request->ip();

            return Limit::perMinute(\config('horizonhub.events_rate_limit'))->by($key);
        });

        RateLimiter::for('horizon-stream', function (Request $request): Limit {
            $limit = \config('horizonhub.stream_rate_limit');

            return Limit::perMinute(\max(1, $limit))->by($request->user()?->id ?: $request->ip());
        });
    }
}
