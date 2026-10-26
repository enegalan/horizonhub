<?php

use App\Http\Controllers\Stream\HorizonStreamsController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'streams/horizon'], function () {
    // Dashboard
    Route::get('/', [HorizonStreamsController::class, 'dashboard'])->name('streams.dashboard');

    // Jobs
    Route::get('/jobs', [HorizonStreamsController::class, 'jobs'])->name('streams.jobs');

    // Job
    Route::get('/jobs/{job}', [HorizonStreamsController::class, 'jobShow'])->name('streams.job-show');

    // Metrics
    Route::get('/metrics', [HorizonStreamsController::class, 'metrics'])->name('streams.metrics');

    // Batches
    Route::get('/batches', [HorizonStreamsController::class, 'batches'])->name('streams.batches');
    Route::get('/services/{service}/batches/{batch}', [HorizonStreamsController::class, 'batchShow'])
        ->whereNumber('service')
        ->name('streams.batch-show');

    // Instances
    Route::get('/instances', [HorizonStreamsController::class, 'instances'])->name('streams.instances');

    // Providers
    Route::get('/providers', [HorizonStreamsController::class, 'providerList'])->name('streams.provider-list');

    // Queues
    Route::get('/queues', [HorizonStreamsController::class, 'queues'])->name('streams.queues');
    Route::get('/queues/{queue}', [HorizonStreamsController::class, 'queueShow'])->name('streams.queue-show');

    // Services routes...
    Route::get('/services', [HorizonStreamsController::class, 'serviceList'])->name('streams.service-list');
    Route::get('/services/{service}', [HorizonStreamsController::class, 'serviceShow'])
        ->name('streams.service-show')
        ->whereNumber('service');
    Route::get('/services/{service}/supervisors/{supervisor}', [HorizonStreamsController::class, 'supervisorShow'])
        ->name('streams.supervisor-show')
        ->whereNumber('service');

    // Alerts
    Route::get('/alerts', [HorizonStreamsController::class, 'alerts'])->name('streams.alerts');
    Route::get('/alerts/{alert}', [HorizonStreamsController::class, 'alertShow'])->name('streams.alerts.show');
});
