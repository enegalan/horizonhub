<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

class VoltServiceProvider extends ServiceProvider {
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
        Volt::mount(
            config('livewire.view_path', resource_path('views/livewire')),
            resource_path('views/pages'),
        );
    }
}
