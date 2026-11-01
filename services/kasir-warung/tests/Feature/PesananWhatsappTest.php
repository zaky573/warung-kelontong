<?php

namespace Tests\Feature;

use App\Events\TransaksiTercatat;
use App\Exceptions\ProblemDetails;
use App\Models\OutboxMessage;
use App\Models\Pesanan;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Uji Titik Integrasi 1: WhatsApp Order System -> POS (REST / RPC).
 */
class PesananWhatsappTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([TransaksiTercatat::class]);
    }

    private function produkContoh(int $stok = 20): void
    {
        Produk::create(['sku' => 'BRG-001', 'nama' => 'Beras Ramas 5 kg', 'harga' => 72000, 'stok' => $stok]);
    }

    private function body(array $ubah = []): array
    {
        return array_merge([
            'kode' => 'WA-0001',
            'pelanggan' => 'Bu Ani',
            'metode_bayar' => 'tunai',
            'items' => [['sku' => 'BRG-001', 'jumlah' => 2]],
        ], $ubah);
    }

    public function test_pesanan_whatsapp_dikonfirmasi_bila_stok_gudang_tersedia(): void
    {
        $this->produkContoh(stok: 20);

        Http::fake([
            '*/api/v1/validasi-stok' => Http::response([
                'ok' => true,
                'alasan' => '',
                'detail' => [
                    [
                        'sku' => 'BRG-001',
                        'diminta' => 2,
                        'stok_gudang' => 100,
                        'tersedia' => true,
                    ],
                ],
            ], 200),
        ]);

        $this->postJson('/api/v1/pesanan-whatsapp', $this->body())
            ->assertCreated()
            ->assertJsonPath('kode', 'WA-0001')
            ->assertJsonPath('status', 'dikonfirmasi')
            ->assertJsonPath('total', 144000);

        // stok rak di kasir ikut berkurang dan pesanan tersimpan
        $this->assertSame(18, Produk::where('sku', 'BRG-001')->sole()->stok);
        $this->assertSame(1, Pesanan::count());
        $this->assertSame('whatsapp', Pesanan::sole()->sumber);

        // validasi stok benar-benar dipanggil secara sinkron
        Http::assertSent(fn ($permintaan) => str_contains($permintaan->url(), '/api/v1/validasi-stok')
            && $permintaan['items'][0]['sku'] === 'BRG-001'
            && $permintaan['items'][0]['jumlah'] === 2);

        $outbox = OutboxMessage::sole();

        $this->assertSame('TransaksiTercatat', $outbox->event_type);
        $this->assertSame('transaksi.tunai', $outbox->routing_key);
        $this->assertSame('pending', $outbox->status);
        $this->assertSame('whatsapp', $outbox->payload['sumber']);
        $this->assertSame(144000, $outbox->payload['total']);
    }

    public function test_pesanan_ditolak_bila_stok_gudang_tidak_mencukupi(): void
    {
        $this->produkContoh(stok: 20);

        Http::fake([
            '*/api/v1/validasi-stok' => Http::response([
                'ok' => false,
                'alasan' => 'Stok gudang BRG-001 tidak mencukupi.',
                'detail' => [['sku' => 'BRG-001', 'diminta' => 2, 'stok_gudang' => 1, 'tersedia' => false]],
            ], 200),
        ]);

        $this->postJson('/api/v1/pesanan-whatsapp', $this->body())
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 409)
            ->assertJsonPath('type', ProblemDetails::STOK_GUDANG_KURANG)
            ->assertJsonPath('title', 'Stok Gudang Tidak Mencukupi');

        $this->assertSame(20, Produk::where('sku', 'BRG-001')->sole()->stok);
        $this->assertSame(0, Pesanan::count());
        Event::assertNotDispatched(TransaksiTercatat::class);
    }

    public function test_layanan_stok_offline_menghasilkan_503(): void
    {
        $this->produkContoh(stok: 20);

        // alamat sengaja ditujukan ke port yang tidak melayani apa pun
        config(['inventory.base_url' => 'http://127.0.0.1:59999']);

        $this->postJson('/api/v1/pesanan-whatsapp', $this->body())
            ->assertStatus(503)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 503)
            ->assertJsonPath('type', ProblemDetails::LAYANAN_TIDAK_TERJANGKAU)
            ->assertJsonPath('title', 'Layanan Stok Gudang Tidak Terjangkau')
            ->assertJsonStructure(['saran']);

        $this->assertSame(20, Produk::where('sku', 'BRG-001')->sole()->stok);
        $this->assertSame(0, Pesanan::count());
        Event::assertNotDispatched(TransaksiTercatat::class);
    }
}
