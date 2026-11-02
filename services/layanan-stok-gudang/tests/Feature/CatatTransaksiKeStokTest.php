<?php

namespace Tests\Feature;

use App\Gudang\CatatTransaksiKeStok;
use App\Gudang\PesanTidakValid;
use App\Models\Stok;
use App\Models\StokMovemen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatatTransaksiKeStokTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Stok::create([
            'sku' => 'BRG-001',
            'nama' => 'Beras Ramas 5 kg',
            'jumlah' => 50,
            'stok_minimum' => 10,
        ]);
    }

    private function event(array $ubah = []): array
    {
        return array_merge([
            'kode' => 'POS-0001',
            'sumber' => 'kasir',
            'metode_bayar' => 'tunai',
            'pelanggan' => null,
            'items' => [
                ['sku' => 'BRG-001', 'jumlah' => 2, 'harga' => 72000],
            ],
            'total' => 144000,
            'terjadi_pada' => '2026-10-17T20:15:00+07:00',
        ], $ubah);
    }

    public function test_transaksi_mengurangi_stok_dan_mencatat_movemen(): void
    {
        $baru = app(CatatTransaksiKeStok::class)(
            $this->event(),
            'pesan-1'
        );

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

    public function test_stok_tidak_mencukupi_membatalkan_seluruh_transaksi(): void
    {
        $event = $this->event([
            'items' => [
                ['sku' => 'BRG-001', 'jumlah' => 60],
            ],
        ]);

        try {
            app(CatatTransaksiKeStok::class)($event, 'pesan-kurang');
            $this->fail('Transaksi dengan stok kurang seharusnya ditolak.');
        } catch (PesanTidakValid $e) {
            $this->assertStringContainsString(
                'tidak mencukupi',
                $e->getMessage()
            );
        }

        $this->assertSame(50, Stok::where('sku', 'BRG-001')->sole()->jumlah);
        $this->assertSame(0, StokMovemen::count());
    }

    public function test_jumlah_nol_ditolak(): void
    {
        $event = $this->event([
            'items' => [
                ['sku' => 'BRG-001', 'jumlah' => 0],
            ],
        ]);

        $this->expectException(PesanTidakValid::class);
        $this->expectExceptionMessage('bilangan bulat positif');

        app(CatatTransaksiKeStok::class)($event, 'pesan-nol');
    }

    public function test_sku_yang_sama_digabungkan_sebelum_mengurangi_stok(): void
    {
        $event = $this->event([
            'items' => [
                ['sku' => 'BRG-001', 'jumlah' => 2],
                ['sku' => 'BRG-001', 'jumlah' => 3],
            ],
        ]);

        app(CatatTransaksiKeStok::class)($event, 'pesan-ganda');

        $this->assertSame(45, Stok::where('sku', 'BRG-001')->sole()->jumlah);
        $this->assertSame(1, StokMovemen::count());
        $this->assertSame(5, StokMovemen::sole()->jumlah);
    }


    public function test_sku_tidak_dikenal_ditolak(): void
    {
        $event = $this->event([
            'items' => [
                ['sku' => 'BRG-999', 'jumlah' => 1],
            ],
        ]);

        $this->expectException(PesanTidakValid::class);
        $this->expectExceptionMessage('tidak dikenal di gudang');

        app(CatatTransaksiKeStok::class)($event, 'pesan-sku-asing');
    }

    public function test_jumlah_negatif_ditolak(): void
    {
        $event = $this->event([
            'items' => [
                ['sku' => 'BRG-001', 'jumlah' => -2],
            ],
        ]);

        $this->expectException(PesanTidakValid::class);

        app(CatatTransaksiKeStok::class)($event, 'pesan-negatif');
    }

    public function test_jumlah_desimal_ditolak(): void
    {
        $event = $this->event([
            'items' => [
                ['sku' => 'BRG-001', 'jumlah' => 2.5],
            ],
        ]);

        $this->expectException(PesanTidakValid::class);

        app(CatatTransaksiKeStok::class)($event, 'pesan-desimal');
    }

    public function test_item_tanpa_sku_ditolak(): void
    {
        $event = $this->event([
            'items' => [
                ['jumlah' => 2],
            ],
        ]);

        $this->expectException(PesanTidakValid::class);
        $this->expectExceptionMessage('Item tanpa sku/jumlah.');

        app(CatatTransaksiKeStok::class)($event, 'pesan-tanpa-sku');
    }

    public function test_kegagalan_satu_sku_membatalkan_semua_perubahan(): void
    {
        Stok::create([
            'sku' => 'BRG-002',
            'nama' => 'Minyak Goreng 1 liter',
            'jumlah' => 10,
            'stok_minimum' => 2,
        ]);

        $event = $this->event([
            'items' => [
                ['sku' => 'BRG-001', 'jumlah' => 2],
                ['sku' => 'BRG-002', 'jumlah' => 20],
            ],
        ]);

        try {
            app(CatatTransaksiKeStok::class)($event, 'pesan-rollback');
            $this->fail('Transaksi seharusnya ditolak karena stok BRG-002 kurang.');
        } catch (PesanTidakValid $e) {
            $this->assertStringContainsString('tidak mencukupi', $e->getMessage());
        }

        $this->assertSame(
            50,
            Stok::where('sku', 'BRG-001')->sole()->jumlah
        );

        $this->assertSame(
            10,
            Stok::where('sku', 'BRG-002')->sole()->jumlah
        );

        $this->assertSame(0, StokMovemen::count());
    }

    public function test_transaksi_beberapa_sku_berhasil_dan_idempoten(): void
    {
        Stok::create([
            'sku' => 'BRG-002',
            'nama' => 'Minyak Goreng 1 liter',
            'jumlah' => 30,
            'stok_minimum' => 5,
        ]);

        $event = $this->event([
            'items' => [
                ['sku' => 'BRG-001', 'jumlah' => 2],
                ['sku' => 'BRG-002', 'jumlah' => 3],
            ],
        ]);

        $catat = app(CatatTransaksiKeStok::class);

        $pertama = $catat($event, 'pesan-multi-sku');
        $kedua = $catat($event, 'pesan-multi-sku');

        $this->assertTrue($pertama);
        $this->assertFalse($kedua);

        $this->assertSame(
            48,
            Stok::where('sku', 'BRG-001')->sole()->jumlah
        );

        $this->assertSame(
            27,
            Stok::where('sku', 'BRG-002')->sole()->jumlah
        );

        $this->assertSame(2, StokMovemen::count());
    }

}
