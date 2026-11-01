<?php

namespace App\Gudang;

use App\Models\RekapTransaksi;
use Illuminate\Support\Facades\DB;

/**
 * Penerima kedua dari event yang sama (bukti publish-subscribe):
 * merekap transaksi untuk arus kas harian warung.
 */
class CatatRekap
{
    /**
     * @return bool true bila rekap baru, false bila duplikat
     *
     * @throws PesanTidakValid
     */
    public function __invoke(array $event, string $messageId): bool
    {
        if ($messageId === '') {
            throw new PesanTidakValid('Pesan tidak memiliki message_id.');
        }

        foreach (['kode', 'metode_bayar', 'total', 'items'] as $field) {
            if (! array_key_exists($field, $event)) {
                throw new PesanTidakValid("Field {$field} tidak ada.");
            }
        }

        return DB::transaction(function () use ($event, $messageId) {
            $rekap = RekapTransaksi::firstOrCreate(
                ['message_id' => $messageId],
                [
                    'kode' => $event['kode'],
                    'sumber' => $event['sumber'] ?? 'kasir',
                    'metode_bayar' => $event['metode_bayar'],
                    'pelanggan' => $event['pelanggan'] ?? null,
                    'jumlah_item' => count($event['items']),
                    'total' => (int) $event['total'],
                    'terjadi_pada' => $event['terjadi_pada'] ?? now()->toIso8601String(),
                ],
            );

            return $rekap->wasRecentlyCreated;
        });
    }
}
