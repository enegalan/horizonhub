<?php

use App\Http\Controllers\Stream\HorizonStreamsController;
use Illuminate\Support\Facades\Route;

Route::get('streams/horizon/metrics', [HorizonStreamsController::class, 'metrics'])->name('streams.metrics');
Route::get('streams/horizon/queues', [HorizonStreamsController::class, 'queues'])->name('streams.queues');
Route::get('streams/horizon/services', [HorizonStreamsController::class, 'serviceList'])->name('streams.service-list');
Route::get('streams/horizon/alerts', [HorizonStreamsController::class, 'alerts'])->name('streams.alerts');
Route::get('streams/horizon', [HorizonStreamsController::class, 'jobs'])->name('streams.jobs');
Route::get('streams/horizon/services/{service}', [HorizonStreamsController::class, 'serviceShow'])
    ->name('streams.service-show')
    ->whereNumber('service');
