<?php

namespace App\Gudang;

use App\Models\Stok;
use App\Models\StokMovemen;
use Illuminate\Support\Facades\DB;

class CatatTransaksiKeStok
{
    private const FIELD_WAJIB = [
        'kode',
        'sumber',
        'metode_bayar',
        'items',
        'total',
        'terjadi_pada',
    ];

    /**
     * Memproses event transaksi menjadi pergerakan stok.
     *
     * @return bool true jika diproses, false jika duplikat.
     */
    public function __invoke(array $event, string $messageId): bool
    {
        if (trim($messageId) === '' || strlen($messageId) > 36) {
            throw new PesanTidakValid('message_id tidak valid.');
        }

        foreach (self::FIELD_WAJIB as $field) {
            if (!array_key_exists($field, $event)) {
                throw new PesanTidakValid("Field {$field} tidak ada.");
            }
        }

        foreach (['kode', 'sumber', 'metode_bayar', 'terjadi_pada'] as $field) {
            if (!is_string($event[$field]) || trim($event[$field]) === '') {
                throw new PesanTidakValid("Field {$field} tidak valid.");
            }
        }

        if (
            !is_array($event['items']) ||
            $event['items'] === []
        ) {
            throw new PesanTidakValid('Field items kosong.');
        }

        // Gabungkan SKU yang sama agar tidak terjadi konflik
        // unique constraint (message_id, sku).
        $itemsBySku = [];

        foreach ($event['items'] as $item) {
            if (!is_array($item) || !isset($item['sku'], $item['jumlah'])) {
                throw new PesanTidakValid('Item tanpa sku/jumlah.');
            }

            if (
                !is_string($item['sku']) ||
                trim($item['sku']) === '' ||
                strlen($item['sku']) > 16
            ) {
                throw new PesanTidakValid('SKU tidak valid.');
            }

            $jumlah = $item['jumlah'];

            if (
                !(is_int($jumlah) ||
                    (is_string($jumlah) && ctype_digit($jumlah))) ||
                (int) $jumlah < 1
            ) {
                throw new PesanTidakValid(
                    "Jumlah barang untuk SKU {$item['sku']} harus bilangan bulat positif."
                );
            }

            $sku = $item['sku'];
            $jumlah = (int) $jumlah;

            if (
                isset($itemsBySku[$sku]) &&
                $itemsBySku[$sku] > PHP_INT_MAX - $jumlah
            ) {
                throw new PesanTidakValid('Jumlah barang terlalu besar.');
            }

            $itemsBySku[$sku] = ($itemsBySku[$sku] ?? 0) + $jumlah;
        }

        // Urutan SKU konsisten untuk mengurangi risiko deadlock.
        ksort($itemsBySku);

        return DB::transaction(function () use ($event, $messageId, $itemsBySku) {
            // Idempotensi: satu message_id hanya boleh diproses sekali.
            if (StokMovemen::where('message_id', $messageId)->exists()) {
                return false;
            }

            foreach ($itemsBySku as $sku => $jumlah) {
                $stok = Stok::where('sku', $sku)
                    ->lockForUpdate()
                    ->first();

                if (!$stok) {
                    throw new PesanTidakValid(
                        "SKU {$sku} tidak dikenal di gudang."
                    );
                }

                // Jangan pernah mengizinkan stok menjadi negatif
                // atau menerima transaksi melebihi stok tersedia.
                if ($stok->jumlah < $jumlah) {
                    throw new PesanTidakValid(
                        "Stok {$sku} tidak mencukupi. Tersedia {$stok->jumlah}, diminta {$jumlah}."
                    );
                }

                $stok->jumlah -= $jumlah;
                $stok->save();

                StokMovemen::create([
                    'message_id' => $messageId,
                    'sku' => $sku,
                    'jumlah' => $jumlah,
                    'arah' => 'keluar',
                    'sumber' => $event['kode'],
                    'terjadi_pada' => $event['terjadi_pada'],
                ]);
            }

            return true;
        });
    }
}
