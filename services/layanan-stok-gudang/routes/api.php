<?php

use App\Http\Controllers\Api\StokController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Daftar stok gudang (pembuktian lewat peramban)
    Route::get('/stok', [StokController::class, 'index']);
    Route::get('/stok/{sku}', [StokController::class, 'show']);

    // TITIK INTEGRASI 1 — dipanggil SINKRON oleh Aplikasi POS (REST/RPC)
    Route::post('/validasi-stok', [StokController::class, 'validasi']);
});
