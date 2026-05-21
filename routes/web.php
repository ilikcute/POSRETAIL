<?php

use App\Http\Controllers\Api\Master\PriceTagController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/app/');
});

Route::get('/price-tags/print', [PriceTagController::class, 'print']);

// Fallback route untuk Single Page Application (SPA) Vue 3
// Mengarahkan seluruh sub-path di /app/ ke file index.html hasil build frontend
Route::get('/app/{any?}', function () {
    $path = public_path('app/index.html');
    if (file_exists($path)) {
        return file_get_contents($path);
    }

    return response('Frontend Vue app belum di-build. Jalankan "npm run build" terlebih dahulu di folder frontend.', 404);
})->where('any', '.*');
