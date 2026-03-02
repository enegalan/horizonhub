<?php

use App\Http\Controllers\Api\V1\EventController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('events', [EventController::class, 'store'])
        ->middleware(['horizon.hub.signature', 'throttle:hub-events']);
});
