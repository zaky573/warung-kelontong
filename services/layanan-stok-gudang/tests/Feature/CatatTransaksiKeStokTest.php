<?php

namespace Tests\Feature;

use App\Gudang\CatatTransaksiKeStok;
use App\Gudang\PesanTidakValid;
use App\Models\Stok;
use App\Models\StokMovemen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji Titik Integrasi 2 dari sisi konsumen: event -> pergerakan stok gudang.
 */
class CatatTransaksiKeStokTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Stok::create(['sku' => 'BRG-001', 'nama' => 'Beras Ramas 5 kg', 'jumlah' => 50, 'stok_minimum' => 10]);
    }

    private function event(array $ubah = []): array
    {
        return array_merge([
            'kode' => 'POS-0001',
            'sumber' => 'kasir',
            'metode_bayar' => 'tunai',
            'pelanggan' => null,
            'items' => [['sku' => 'BRG-001', 'jumlah' => 2, 'harga' => 72000]],
            'total' => 144000,
            'terjadi_pada' => '2026-10-17T20:15:00+07:00',
        ], $ubah);
    }

    public function test_transaksi_mengurangi_stok_dan_mencatat_movemen(): void
    {
        $baru = app(CatatTransaksiKeStok::class)($this->event(), 'pesan-1');

        $this->assertTrue($baru);
        $this->assertSame(48, Stok::where('sku', 'BRG-001')->sole()->jumlah);

        $movemen = StokMovemen::sole();
        $this->assertSame('pesan-1', $movemen->message_id);
        $this->assertSame('BRG-001', $movemen->sku);
        $this->assertSame(2, $movemen->jumlah);
        $this->assertSame('keluar', $movemen->arah);
        $this->assertSame('POS-0001', $movemen->sumber);
    }

    public function test_pesan_duplikat_dilewati_tanpa_mengurangi_stok_dua_kali(): void
    {
        $catat = app(CatatTransaksiKeStok::class);

        $catat($this->event(), 'pesan-1');
        $kaliKedua = $catat($this->event(), 'pesan-1');

        $this->assertFalse($kaliKedua);
        $this->assertSame(48, Stok::where('sku', 'BRG-001')->sole()->jumlah);
        $this->assertSame(1, StokMovemen::count());
    }

    public function test_pesan_tanpa_field_wajib_ditolak(): void
    {
        $event = $this->event();
        unset($event['items']);

        $this->expectException(PesanTidakValid::class);
        $this->expectExceptionMessage('Field items tidak ada.');

        app(CatatTransaksiKeStok::class)($event, 'pesan-rusak');
    }

    public function test_pesan_tanpa_message_id_ditolak(): void
    {
        $this->expectException(PesanTidakValid::class);

        app(CatatTransaksiKeStok::class)($this->event(), '');
    }
}
