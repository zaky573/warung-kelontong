<?php

namespace Tests\Feature;

use App\Models\Stok;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uji Titik Integrasi 3: file transfer / document export (batch).
 */
class EksporPoTest extends TestCase
{
    use RefreshDatabase;

    public function test_hanya_barang_kritis_yang_masuk_dokumen_po(): void
    {
        Storage::fake('local');

        Stok::create(['sku' => 'BRG-001', 'nama' => 'Beras Ramas 5 kg', 'jumlah' => 120, 'stok_minimum' => 40]);
        Stok::create(['sku' => 'BRG-003', 'nama' => 'Gula Pasir 1 kg', 'jumlah' => 18, 'stok_minimum' => 20]);
        Stok::create(['sku' => 'BRG-004', 'nama' => 'Telur Ayam 1 kg', 'jumlah' => 25, 'stok_minimum' => 30]);

        $this->artisan('gudang:ekspor-po', ['--format' => 'csv', '--tanggal' => '2026-10-17'])
            ->assertExitCode(0);

        $csv = Storage::disk('local')->get('ekspor/PO-2026-10-17.csv');

        $this->assertStringContainsString('BRG-003', $csv);
        $this->assertStringContainsString('BRG-004', $csv);
        $this->assertStringNotContainsString('BRG-001', $csv, 'Barang dengan stok aman tidak boleh ikut dikulak');
        $this->assertStringContainsString('Usul Kulakan', $csv);
    }
}
