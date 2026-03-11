<?php

use App\Http\Controllers\Api\V1\JobActionController;
use App\Http\Controllers\Horizon\JobController;
use App\Http\Controllers\Horizon\AlertController;
use App\Http\Controllers\Horizon\ServiceController;
use App\Http\Controllers\Horizon\QueueController;
use App\Http\Controllers\Horizon\MetricsController;
use App\Http\Controllers\Horizon\SettingsController;
use App\Http\Controllers\Horizon\ProviderController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/horizon');
Route::get('/dashboard', fn () => redirect()->route('horizon.index'))->name('dashboard');

Route::prefix('horizon')->name('horizon.')->group(function (): void {
    Route::get('/', [JobController::class, 'index'])->name('index');
    Route::get('/metrics', [MetricsController::class, 'index'])->name('metrics');
    Route::get('/metrics/data/summary', [MetricsController::class, 'dataSummary'])->name('metrics.data.summary');
    Route::get('/metrics/data/processed-vs-failed', [MetricsController::class, 'dataProcessedVsFailed'])->name('metrics.data.processed-vs-failed');
    Route::get('/metrics/data/avg-runtime', [MetricsController::class, 'dataAvgRuntime'])->name('metrics.data.avg-runtime');
    Route::get('/metrics/data/by-queue', [MetricsController::class, 'dataByQueue'])->name('metrics.data.by-queue');
    Route::get('/metrics/data/by-service', [MetricsController::class, 'dataByService'])->name('metrics.data.by-service');
    Route::get('/metrics/data/failures-table', [MetricsController::class, 'dataFailuresTable'])->name('metrics.data.failures-table');
    Route::get('/metrics/data/supervisors', [MetricsController::class, 'dataSupervisors'])->name('metrics.data.supervisors');
    Route::get('/metrics/data/workload', [MetricsController::class, 'dataWorkload'])->name('metrics.data.workload');
    Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
    Route::get('/queues', [QueueController::class, 'index'])->name('queues.index');
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
    Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');
    Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])->name('services.edit');
    Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
    Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    Route::post('/services/{service}/test-connection', [ServiceController::class, 'testConnection'])->name('services.test-connection');
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::get('/alerts/create', [AlertController::class, 'create'])->name('alerts.create');
    Route::post('/alerts', [AlertController::class, 'store'])->name('alerts.store');
    Route::get('/alerts/{alert}', [AlertController::class, 'show'])->name('alerts.show');
    Route::get('/alerts/{alert}/edit', [AlertController::class, 'edit'])->name('alerts.edit');
    Route::put('/alerts/{alert}', [AlertController::class, 'update'])->name('alerts.update');
    Route::delete('/alerts/{alert}', [AlertController::class, 'destroy'])->name('alerts.destroy');
    Route::redirect('/providers', '/horizon/settings?tab=providers')->name('providers.index');
    Route::get('/providers/create', [ProviderController::class, 'create'])->name('providers.create');
    Route::post('/providers', [ProviderController::class, 'store'])->name('providers.store');
    Route::get('/providers/{provider}/edit', [ProviderController::class, 'edit'])->name('providers.edit');
    Route::put('/providers/{provider}', [ProviderController::class, 'update'])->name('providers.update');
    Route::delete('/providers/{provider}', [ProviderController::class, 'destroy'])->name('providers.destroy');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
});

Route::prefix('api/v1')->middleware(['throttle:60,1'])->group(function (): void {
    Route::post('jobs/{id}/retry', [JobActionController::class, 'retry'])->name('api.jobs.retry');
    Route::post('jobs/retry-batch', [JobActionController::class, 'retryBatch'])->name('api.jobs.retry-batch');
    Route::get('horizon/jobs/failed', [JobActionController::class, 'failedList'])->name('api.horizon.jobs.failed');
    Route::post('horizon/jobs/clean', [JobActionController::class, 'clean'])->name('api.horizon.jobs.clean');
    Route::post('alerts/logs/{log}/retry', [AlertController::class, 'retryLog'])->name('api.alerts.logs.retry');
});
