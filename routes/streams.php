<?php

use App\Http\Controllers\Stream\RefreshStreamController;
use Illuminate\Support\Facades\Route;

Route::get('/refresh/stream', [RefreshStreamController::class, 'stream'])->name('refresh.stream');
