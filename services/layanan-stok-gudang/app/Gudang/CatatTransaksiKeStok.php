<?php

namespace App\Gudang;

use App\Models\Stok;
use App\Models\StokMovemen;
use Illuminate\Support\Facades\DB;

/**
 * Handler Titik Integrasi 2: mengubah event TransaksiTercatat menjadi
 * pergerakan stok fisik di gudang. Idempoten berdasarkan message_id.
 */
class CatatTransaksiKeStok
{
    private const FIELD_WAJIB = ['kode', 'sumber', 'metode_bayar', 'items', 'total', 'terjadi_pada'];

    /**
     * @return bool true bila pesan diproses, false bila duplikat (dilewati)
     *
     * @throws PesanTidakValid
     */
    public function __invoke(array $event, string $messageId): bool
    {
        if ($messageId === '') {
            throw new PesanTidakValid('Pesan tidak memiliki message_id.');
        }

        foreach (self::FIELD_WAJIB as $field) {
            if (! array_key_exists($field, $event)) {
                throw new PesanTidakValid("Field {$field} tidak ada.");
            }
        }

        if (! is_array($event['items']) || $event['items'] === []) {
            throw new PesanTidakValid('Field items kosong.');
        }

        foreach ($event['items'] as $item) {
            if (! isset($item['sku'], $item['jumlah'])) {
                throw new PesanTidakValid('Item tanpa sku/jumlah.');
            }
        }

        return DB::transaction(function () use ($event, $messageId) {
            // Idempoten: pesan yang sama tidak boleh mengurangi stok dua kali
            if (StokMovemen::where('message_id', $messageId)->exists()) {
                return false;
            }

            foreach ($event['items'] as $item) {
                $stok = Stok::where('sku', $item['sku'])->lockForUpdate()->first();

                if (! $stok) {
                    throw new PesanTidakValid("SKU {$item['sku']} tidak dikenal di gudang.");
                }

                $jumlah = (int) $item['jumlah'];
                $stok->jumlah = max(0, $stok->jumlah - $jumlah);
                $stok->save();

                StokMovemen::create([
                    'message_id' => $messageId,
                    'sku' => $stok->sku,
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
