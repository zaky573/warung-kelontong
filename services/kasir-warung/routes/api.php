<?php

use App\Http\Controllers\Api\PesananController;
use App\Http\Controllers\Api\ProdukController;
use Illuminate\Support\Facades\Route;

// Semua endpoint memakai versi: /api/v1/...
Route::prefix('v1')->group(function () {
    Route::get('/produk', [ProdukController::class, 'index']);
    Route::post('/produk', [ProdukController::class, 'store']);

    Route::get('/pesanan', [PesananController::class, 'index']);

    // Titik Integrasi 1: WhatsApp -> POS (REST / RPC)
    Route::post('/pesanan-whatsapp', [PesananController::class, 'whatsapp']);

    // Titik Integrasi 2: POS -> Inventory (asynchronous messaging)
    Route::post('/penjualan', [PesananController::class, 'kasir']);
});
