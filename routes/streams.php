<?php

use App\Http\Controllers\Stream\MetricsStreamController;
use App\Http\Controllers\Stream\RefreshStreamController;
use Illuminate\Support\Facades\Route;

Route::get('/metrics/stream', [MetricsStreamController::class, 'stream'])->name('metrics.stream');
Route::get('/refresh/stream', [RefreshStreamController::class, 'stream'])->name('refresh.stream');
