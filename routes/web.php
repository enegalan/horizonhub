<?php

use App\Http\Controllers\Api\V1\JobActionController;
use App\Http\Controllers\Api\V1\QueueActionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/horizon');
Route::get('/dashboard', fn () => redirect()->route('horizon.index'))->name('dashboard');

Route::prefix('horizon')->name('horizon.')->group(function (): void {
    Route::get('/', \App\Livewire\Horizon\JobList::class)->name('index');
    Route::get('/metrics', \App\Livewire\Horizon\Metrics::class)->name('metrics');
    Route::get('/jobs/{job}', \App\Livewire\Horizon\JobDetail::class)->name('jobs.show');
    Route::get('/queues', \App\Livewire\Horizon\QueueList::class)->name('queues.index');
    Route::get('/queues/body', \App\Http\Controllers\Horizon\QueueListBodyController::class)->name('queues.body');
    Route::get('/services', \App\Livewire\Horizon\ServiceList::class)->name('services.index');
    Route::get('/services/{service}', \App\Livewire\Horizon\ServiceDashboard::class)->name('services.show');
    Route::get('/alerts', \App\Livewire\Horizon\AlertList::class)->name('alerts.index');
    Route::get('/alerts/create', \App\Livewire\Horizon\AlertForm::class)->name('alerts.create');
    Route::get('/alerts/{alert}', \App\Livewire\Horizon\AlertDetail::class)->name('alerts.show');
    Route::get('/alerts/{alert}/edit', \App\Livewire\Horizon\AlertForm::class)->name('alerts.edit');
    Route::redirect('/providers', '/horizon/settings?tab=providers')->name('providers.index');
    Route::get('/providers/create', \App\Livewire\Horizon\ProviderForm::class)->name('providers.create');
    Route::get('/providers/{provider}/edit', \App\Livewire\Horizon\ProviderForm::class)->name('providers.edit');
    Route::get('/settings', \App\Livewire\Horizon\Settings::class)->name('settings');
});

Route::prefix('api/v1')->middleware(['throttle:60,1'])->group(function (): void {
    Route::post('jobs/{id}/retry', [JobActionController::class, 'retry'])->name('api.jobs.retry');
    Route::delete('jobs/{id}/delete', [JobActionController::class, 'delete'])->name('api.jobs.delete');
    Route::post('queues/{name}/pause', [QueueActionController::class, 'pause'])->name('api.queues.pause');
    Route::post('queues/{name}/resume', [QueueActionController::class, 'resume'])->name('api.queues.resume');
});
