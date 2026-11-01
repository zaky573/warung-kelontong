<?php

namespace Tests\Feature;

use App\Events\TransaksiTercatat;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Uji butir B2: setiap kegagalan dijawab dengan Problem Details RFC 7807,
 * bukan bodi JSON suka-suka layanan.
 */
class ProblemDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([TransaksiTercatat::class]);
    }

    private function produkContoh(int $stok): void
    {
        Produk::create(['sku' => 'BRG-001', 'nama' => 'Beras Ramas 5 kg', 'harga' => 72000, 'stok' => $stok]);
    }

    public function test_stok_rak_kasir_kurang_menghasilkan_problem_details_409(): void
    {
        $this->produkContoh(stok: 1);

        $this->postJson('/api/v1/penjualan', [
            'metode_bayar' => 'tunai',
            'items' => [['sku' => 'BRG-001', 'jumlah' => 5]],
        ])
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 409)
            ->assertJsonPath('type', 'urn:warung-kelontong:masalah:stok-rak-kasir-kurang')
            ->assertJsonPath('title', 'Stok Rak di Kasir Tidak Cukup')
            ->assertJsonPath('instance', '/api/v1/penjualan')
            ->assertJsonStructure(['type', 'title', 'status', 'detail', 'instance', 'sisa_stok']);

        $this->assertSame('Stok rak di kasir tidak mencukupi untuk memenuhi pesanan ini.',
            $this->postJson('/api/v1/penjualan', [
                'metode_bayar' => 'tunai',
                'items' => [['sku' => 'BRG-001', 'jumlah' => 5]],
            ])->json('detail'));
    }

    public function test_stok_gudang_kurang_menghasilkan_problem_details_409(): void
    {
        $this->produkContoh(stok: 20);

        \Illuminate\Support\Facades\Http::fake([
            '*/api/v1/validasi-stok' => \Illuminate\Support\Facades\Http::response([
                'ok' => false,
                'alasan' => 'Stok gudang BRG-001 tidak mencukupi.',
                'detail' => [['sku' => 'BRG-001', 'diminta' => 99, 'stok_gudang' => 1, 'tersedia' => false]],
            ], 200),
        ]);

        $this->postJson('/api/v1/pesanan-whatsapp', [
            'kode' => 'WA-0001',
            'metode_bayar' => 'tunai',
            'items' => [['sku' => 'BRG-001', 'jumlah' => 99]],
        ])
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 409)
            ->assertJsonPath('type', 'urn:warung-kelontong:masalah:stok-gudang-kurang')
            ->assertJsonPath('title', 'Stok Gudang Tidak Mencukupi')
            ->assertJsonPath('sisa_stok.0.sku', 'BRG-001');
    }

    public function test_isian_tidak_valid_menghasilkan_problem_details_422(): void
    {
        $this->postJson('/api/v1/penjualan', [
            'metode_bayar' => 'COD',
            'items' => [],
        ])
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 422)
            ->assertJsonPath('type', 'urn:warung-kelontong:masalah:isian-tidak-valid')
            ->assertJsonStructure([
                'type', 'title', 'status', 'detail', 'instance',
                'errors' => ['metode_bayar', 'items'],
            ]);
    }

    public function test_layanan_stok_offline_menghasilkan_problem_details_503(): void
    {
        $this->produkContoh(stok: 20);

        // alamat sengaja ditujukan ke port yang tidak melayani apa pun
        config(['inventory.base_url' => 'http://127.0.0.1:59999']);

        $this->postJson('/api/v1/pesanan-whatsapp', [
            'kode' => 'WA-0001',
            'metode_bayar' => 'tunai',
            'items' => [['sku' => 'BRG-001', 'jumlah' => 2]],
        ])
            ->assertStatus(503)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 503)
            ->assertJsonPath('type', 'urn:warung-kelontong:masalah:layanan-tidak-terjangkau')
            ->assertJsonStructure(['type', 'title', 'status', 'detail', 'saran']);
    }
}
