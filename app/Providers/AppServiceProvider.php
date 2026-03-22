<?php

namespace App\Providers;

use App\Contracts\EmailAlertNotifier;
use App\Contracts\SlackAlertNotifier;
use App\Services\Notifiers\EmailNotifier;
use App\Services\Notifiers\SlackNotifier;
use App\Support\Horizon\ConfigHelper;
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
        RateLimiter::for('horizon-stream', function (Request $request): Limit {
            $limit = ConfigHelper::getIntWithMin('horizonhub.stream_rate_limit', 1);

            return Limit::perMinute($limit)->by($request->user()?->id ?: $request->ip());
        });
    }
}
