<?php

use App\Http\Controllers\Stream\HorizonStreamsController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'streams/horizon'], function () {
    // Index
    Route::get('/', [HorizonStreamsController::class, 'jobs'])->name('streams.jobs');

    // Metrics
    Route::get('/metrics', [HorizonStreamsController::class, 'metrics'])->name('streams.metrics');

    // Jobs
    Route::get('/jobs/{job}', [HorizonStreamsController::class, 'jobShow'])->name('streams.job-show');

    // Queues
    Route::get('/queues', [HorizonStreamsController::class, 'queues'])->name('streams.queues');

    // Services routes...
    Route::get('/services', [HorizonStreamsController::class, 'serviceList'])->name('streams.service-list');
    Route::get('/services/{service}', [HorizonStreamsController::class, 'serviceShow'])
        ->name('streams.service-show')
        ->whereNumber('service');

    // Alerts
    Route::get('/alerts', [HorizonStreamsController::class, 'alerts'])->name('streams.alerts');
});
