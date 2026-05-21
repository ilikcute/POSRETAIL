<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/price-tags/print', [App\Http\Controllers\Api\Master\PriceTagController::class, 'print']);
