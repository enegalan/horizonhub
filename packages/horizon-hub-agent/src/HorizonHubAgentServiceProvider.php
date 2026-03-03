<?php

namespace HorizonHub\Agent;

use HorizonHub\Agent\Http\Controllers\HorizonHubActionController;
use HorizonHub\Agent\Listeners\PushHorizonEventToHub;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class HorizonHubAgentServiceProvider extends ServiceProvider {
    public function boot(): void {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/horizonhub.php' => config_path('horizonhub.php'),
            ], 'horizon-hub-agent-config');
        }

        $this->registerEventListeners();
        $this->registerRoutes();
    }

    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/../config/horizonhub.php', 'horizonhub');
    }

    private function registerEventListeners(): void {
        $listener = PushHorizonEventToHub::class;

        $events = [
            \Laravel\Horizon\Events\JobProcessed::class,
            \Laravel\Horizon\Events\JobDeleted::class,
            \Laravel\Horizon\Events\JobFailed::class,
            \Laravel\Horizon\Events\JobProcessing::class,
            \Laravel\Horizon\Events\JobReserved::class,
            \Illuminate\Queue\Events\JobProcessed::class,
            \Illuminate\Queue\Events\JobFailed::class,
            \Illuminate\Queue\Events\JobProcessing::class,
            \Laravel\Horizon\Events\SupervisorLooped::class,
            \Laravel\Horizon\Events\QueuePaused::class,
            \Laravel\Horizon\Events\QueueResumed::class,
        ];

        foreach ($events as $event) {
            if (class_exists($event)) {
                Event::listen($event, $listener);
            }
        }
    }

    private function registerRoutes(): void {
        $middleware = [\HorizonHub\Agent\Http\Middleware\ValidateHubSignature::class];

        Route::middleware($middleware)->prefix('horizon-hub')->group(function (): void {
            Route::post('jobs/{id}/retry', [HorizonHubActionController::class, 'retry'])->name('horizon-hub.jobs.retry');
            Route::delete('jobs/{id}/delete', [HorizonHubActionController::class, 'delete'])->name('horizon-hub.jobs.delete');
            Route::post('queues/{name}/pause', [HorizonHubActionController::class, 'pause'])->name('horizon-hub.queues.pause');
            Route::post('queues/{name}/resume', [HorizonHubActionController::class, 'resume'])->name('horizon-hub.queues.resume');
        });
    }
}
