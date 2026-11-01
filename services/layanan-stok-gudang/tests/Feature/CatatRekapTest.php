<?php

namespace Tests\Feature;

use App\Gudang\CatatRekap;
use App\Models\RekapTransaksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji penerima kedua (bukti pola publish-subscribe: satu event, dua antrean).
 */
class CatatRekapTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $ubah = []): array
    {
        return array_merge([
            'kode' => 'POS-0001',
            'sumber' => 'kasir',
            'metode_bayar' => 'utang',
            'pelanggan' => 'Pak Udin',
            'items' => [['sku' => 'BRG-002', 'jumlah' => 1, 'harga' => 18000]],
            'total' => 18000,
            'terjadi_pada' => '2026-10-17T20:16:00+07:00',
        ], $ubah);
    }

    public function test_rekap_tercatat_dan_duplikat_tidak_membuat_baris_kedua(): void
    {
        $catat = app(CatatRekap::class);

        $pertama = $catat($this->event(), 'pesan-1');
        $kedua = $catat($this->event(), 'pesan-1');

        $this->assertTrue($pertama);
        $this->assertFalse($kedua);

        $rekap = RekapTransaksi::sole();
        $this->assertSame('POS-0001', $rekap->kode);
        $this->assertSame('utang', $rekap->metode_bayar);
        $this->assertSame('Pak Udin', $rekap->pelanggan);
        $this->assertSame(1, $rekap->jumlah_item);
        $this->assertSame(18000, $rekap->total);
        $this->assertSame(1, RekapTransaksi::count());
    }
}
