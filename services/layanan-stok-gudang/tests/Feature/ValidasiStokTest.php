<?php

namespace Tests\Feature;

use App\Models\Stok;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji Titik Integrasi 1 dari sisi penyedia layanan (jawaban sinkron untuk POS).
 */
class ValidasiStokTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Stok::create(['sku' => 'BRG-001', 'nama' => 'Beras Ramas 5 kg', 'jumlah' => 50, 'stok_minimum' => 10]);
        Stok::create(['sku' => 'BRG-003', 'nama' => 'Gula Pasir 1 kg', 'jumlah' => 3, 'stok_minimum' => 20]);
    }

    public function test_validasi_stok_membedakan_item_tersedia_dan_tidak(): void
    {
        $this->postJson('/api/v1/validasi-stok', [
            'items' => [
                ['sku' => 'BRG-001', 'jumlah' => 2],
                ['sku' => 'BRG-003', 'jumlah' => 5],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('detail.0.tersedia', true)
            ->assertJsonPath('detail.1.tersedia', false)
            ->assertJsonPath('detail.1.stok_gudang', 3);
    }

    public function test_validasi_stok_menyatakan_ok_bila_semua_tersedia(): void
    {
        $this->postJson('/api/v1/validasi-stok', [
            'items' => [['sku' => 'BRG-001', 'jumlah' => 2]],
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_satu_sku_bisa_dibaca_dan_sku_tidak_dikenal_berhasil_404(): void
    {
        $this->getJson('/api/v1/stok/BRG-001')
            ->assertOk()
            ->assertJsonPath('data.sku', 'BRG-001')
            ->assertJsonPath('data.jumlah', 50);

        $this->getJson('/api/v1/stok/BRG-999')->assertNotFound();
    }
}
