<?php

namespace App\Pembukuan;

use App\Models\JurnalPenjualan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class CatatPenjualan
{
    public function __invoke(array $event, string $messageId): bool
    {
        if ($messageId === '') {
            throw new PesanTidakValid('Message ID kosong.');
        }

        foreach ([
            'kode',
            'sumber',
            'metode_bayar',
            'items',
            'total',
            'terjadi_pada',
        ] as $field) {
            if (!array_key_exists($field, $event)) {
                throw new PesanTidakValid("Field {$field} tidak ada.");
            }
        }

        if (!in_array($event['metode_bayar'], ['tunai', 'utang'], true)) {
            throw new PesanTidakValid('Metode pembayaran tidak valid.');
        }

        if (!is_array($event['items']) || count($event['items']) === 0) {
            throw new PesanTidakValid('Item transaksi kosong.');
        }

        if (!is_int($event['total']) || $event['total'] < 0) {
            throw new PesanTidakValid('Total transaksi tidak valid.');
        }

        $items = [];

        foreach ($event['items'] as $item) {
            if (
                !is_array($item) ||
                !isset($item['sku'], $item['jumlah'], $item['harga']) ||
                !is_string($item['sku']) ||
                $item['sku'] === '' ||
                strlen($item['sku']) > 7 ||
                !is_int($item['jumlah']) ||
                $item['jumlah'] <= 0 ||
                !is_int($item['harga']) ||
                $item['harga'] < 0
            ) {
                throw new PesanTidakValid('Data item transaksi tidak valid.');
            }

            $sku = $item['sku'];
            $harga = $item['harga'];

            if (isset($items[$sku]) && $items[$sku]['harga'] !== $harga) {
                throw new PesanTidakValid('Harga SKU tidak konsisten.');
            }

            if (!isset($items[$sku])) {
                $items[$sku] = [
                    'jumlah' => 0,
                    'harga' => $harga,
                ];
            }

            $items[$sku]['jumlah'] += $item['jumlah'];
        }

        $jumlahTotal = 0;

        foreach ($items as $item) {
            $jumlahTotal += $item['jumlah'] * $item['harga'];
        }

        if ($jumlahTotal !== $event['total']) {
            throw new PesanTidakValid('Total item tidak sesuai total transaksi.');
        }

        try {
            $terjadiPada = Carbon::parse($event['terjadi_pada']);
        } catch (Throwable $e) {
            throw new PesanTidakValid('Waktu transaksi tidak valid.');
        }

        return DB::transaction(function () use (
            $event,
            $messageId,
            $items,
            $terjadiPada
        ) {
            $adaBarisBaru = false;

            foreach ($items as $sku => $item) {
                $jurnal = JurnalPenjualan::firstOrCreate(
                    [
                        'message_id' => $messageId,
                        'sku' => $sku,
                    ],
                    [
                        'kode' => $event['kode'],
                        'sumber' => $event['sumber'],
                        'metode_bayar' => $event['metode_bayar'],
                        'pelanggan' => $event['pelanggan'] ?? null,
                        'jumlah' => $item['jumlah'],
                        'harga_satuan' => $item['harga'],
                        'total' => $item['jumlah'] * $item['harga'],
                        'total_transaksi' => $event['total'],
                        'terjadi_pada' => $terjadiPada,
                    ]
                );

                if ($jurnal->wasRecentlyCreated) {
                    $adaBarisBaru = true;
                }
            }

            return $adaBarisBaru;
        });
    }
}
