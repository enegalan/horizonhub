<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(array('app' => config('app.name'), 'status' => 'ok'));
});
