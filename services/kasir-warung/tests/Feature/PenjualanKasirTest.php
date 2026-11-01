<?php

namespace Tests\Feature;

use App\Exceptions\ProblemDetails;
use App\Models\OutboxMessage;
use App\Models\Pesanan;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenjualanKasirTest extends TestCase
{
    use RefreshDatabase;

    private function produkContoh(int $stok = 20): void
    {
        Produk::create([
            'sku' => 'BRG-001',
            'nama' => 'Beras Ramas 5 kg',
            'harga' => 72000,
            'stok' => $stok,
        ]);
    }

    public function test_penjualan_tunai_tercatat_dan_event_dimasukkan_ke_outbox(): void
    {
        $this->produkContoh(stok: 20);

        $this->postJson('/api/v1/penjualan', [
            'metode_bayar' => 'tunai',
            'items' => [
                ['sku' => 'BRG-001', 'jumlah' => 2],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'dikonfirmasi')
            ->assertJsonPath('sumber', 'kasir')
            ->assertJsonPath('total', 144000);

        $pesanan = Pesanan::sole();

        $this->assertStringStartsWith('POS-', $pesanan->kode);
        $this->assertSame(18, Produk::where('sku', 'BRG-001')->sole()->stok);

        $outbox = OutboxMessage::sole();

        $this->assertSame('TransaksiTercatat', $outbox->event_type);
        $this->assertSame('transaksi.tunai', $outbox->routing_key);
        $this->assertSame('pending', $outbox->status);
        $this->assertSame('tunai', $outbox->payload['metode_bayar']);
        $this->assertSame(144000, $outbox->payload['total']);
    }

    public function test_penjualan_utang_menghasilkan_routing_key_utang(): void
    {
        $this->produkContoh(stok: 20);

        $this->postJson('/api/v1/penjualan', [
            'pelanggan' => 'Pak Udin',
            'metode_bayar' => 'utang',
            'items' => [
                ['sku' => 'BRG-001', 'jumlah' => 1],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('metode_bayar', 'utang');

        $pesanan = Pesanan::sole();

        $this->assertSame('utang', $pesanan->metode_bayar);
        $this->assertSame('Pak Udin', $pesanan->pelanggan);

        $outbox = OutboxMessage::sole();

        $this->assertSame('transaksi.utang', $outbox->routing_key);
        $this->assertSame('Pak Udin', $outbox->payload['pelanggan']);
        $this->assertSame('utang', $outbox->payload['metode_bayar']);
        $this->assertSame('pending', $outbox->status);
    }

    public function test_penjualan_ditolak_bila_stok_kasir_kurang(): void
    {
        $this->produkContoh(stok: 1);

        $this->postJson('/api/v1/penjualan', [
            'metode_bayar' => 'tunai',
            'items' => [
                ['sku' => 'BRG-001', 'jumlah' => 5],
            ],
        ])
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 409)
            ->assertJsonPath('type', ProblemDetails::STOK_RAK_KASIR_KURANG)
            ->assertJsonPath('title', 'Stok Rak di Kasir Tidak Cukup');

        $this->assertSame(1, Produk::where('sku', 'BRG-001')->sole()->stok);
        $this->assertSame(0, Pesanan::count());
        $this->assertDatabaseCount('outbox_messages', 0);
    }
}
