<?php

namespace Tests\Feature;

use App\Models\JurnalPenjualan;
use App\Pembukuan\CatatPenjualan;
use App\Pembukuan\PesanTidakValid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatatPenjualanTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $ubah = []): array
    {
        return array_merge([
            'kode' => 'POS-TEST-001',
            'sumber' => 'kasir',
            'metode_bayar' => 'tunai',
            'pelanggan' => 'Pelanggan Test',
            'items' => [
                ['sku' => 'BRG-001', 'jumlah' => 2, 'harga' => 10000],
                ['sku' => 'BRG-002', 'jumlah' => 1, 'harga' => 15000],
            ],
            'total' => 35000,
            'terjadi_pada' => '2026-11-02T10:00:00+07:00',
        ], $ubah);
    }

    public function test_transaksi_dicatat_ke_jurnal(): void
    {
        $baru = app(CatatPenjualan::class)($this->event(), 'pesan-1');

        $this->assertTrue($baru);
        $this->assertSame(2, JurnalPenjualan::count());
        $this->assertSame(35000, JurnalPenjualan::first()->total_transaksi);
    }

    public function test_transaksi_utang_dicatat(): void
    {
        $event = $this->event(['metode_bayar' => 'utang']);

        app(CatatPenjualan::class)($event, 'pesan-2');

        $this->assertSame('utang', JurnalPenjualan::first()->metode_bayar);
    }

    public function test_pesan_duplikat_tidak_dicatat_dua_kali(): void
    {
        $catat = app(CatatPenjualan::class);

        $catat($this->event(), 'pesan-3');
        $baru = $catat($this->event(), 'pesan-3');

        $this->assertFalse($baru);
        $this->assertSame(2, JurnalPenjualan::count());
    }

    public function test_sku_sama_digabungkan(): void
    {
        $event = $this->event([
            'items' => [
                ['sku' => 'BRG-001', 'jumlah' => 1, 'harga' => 10000],
                ['sku' => 'BRG-001', 'jumlah' => 2, 'harga' => 10000],
            ],
            'total' => 30000,
        ]);

        app(CatatPenjualan::class)($event, 'pesan-4');

        $this->assertSame(1, JurnalPenjualan::count());
        $this->assertSame(3, JurnalPenjualan::sole()->jumlah);
    }

    public function test_message_id_kosong_ditolak(): void
    {
        $this->expectException(PesanTidakValid::class);

        app(CatatPenjualan::class)($this->event(), '');
    }

    public function test_total_tidak_sesuai_ditolak(): void
    {
        $this->expectException(PesanTidakValid::class);

        app(CatatPenjualan::class)(
            $this->event(['total' => 99999]),
            'pesan-5'
        );
    }
}
