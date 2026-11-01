<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

/**
 * Fakta: "sebuah transaksi kasir sudah tercatat".
 * Bentuk lampau karena event menyatakan kejadian, bukan perintah.
 */
class TransaksiTercatat
{
    use Dispatchable;

    public readonly string $id;
    public readonly string $terjadiPada;

    /**
     * @param  array<int, array{sku: string, jumlah: int, harga: int}>  $items
     */
    public function __construct(
        public readonly string $kode,
        public readonly string $sumber,          // whatsapp | kasir
        public readonly string $metodeBayar,      // tunai | utang
        public readonly ?string $pelanggan,
        public readonly array $items,
        public readonly int $total,
    ) {
        $this->id = (string) Str::uuid();
        $this->terjadiPada = now()->toIso8601String();
    }

    /** Routing key dibuat dari metode bayar: transaksi.tunai | transaksi.utang */
    public function routingKey(): string
    {
        return "transaksi.{$this->metodeBayar}";
    }

    /** Kontrak pesan (JSON) yang disepakati kedua layanan. */
    public function payload(): array
    {
        return [
            'kode' => $this->kode,
            'sumber' => $this->sumber,
            'metode_bayar' => $this->metodeBayar,
            'pelanggan' => $this->pelanggan,
            'items' => $this->items,
            'total' => $this->total,
            'terjadi_pada' => $this->terjadiPada,
        ];
    }
}
