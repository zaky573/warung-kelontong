<?php

namespace App\Services;

use App\Exceptions\StokTidakTerjangkau;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Jembatan REST dari Aplikasi POS ke Inventory Core Service.
 * Pemanggilannya SINKRON (request-reply): jawaban dibutuhkan sebelum pesanan dikonfirmasi.
 */
class InventoryClient
{
    /**
     * @param  array<int, array{sku: string, jumlah: int}>  $items
     * @return array{ok: bool, alasan: string, detail: array<int, array<string, mixed>>}
     *
     * @throws StokTidakTerjangkau bila layanan stok tidak terjangkau
     */
    public function validasi(array $items): array
    {
        try {
            $respons = Http::timeout(config('inventory.timeout'))
                ->acceptJson()
                ->asJson()
                ->post(config('inventory.base_url').'/api/v1/validasi-stok', ['items' => $items]);
        } catch (ConnectionException $e) {
            throw new StokTidakTerjangkau(
                'Layanan stok tidak terjangkau (Inventory Service offline). Pesanan ditahan, coba lagi beberapa detik lagi.'
            );
        }

        if ($respons->failed()) {
            throw new StokTidakTerjangkau(
                'Layanan stok memberi balasan gagal (HTTP '.$respons->status().').'
            );
        }

        return $respons->json();
    }
}
