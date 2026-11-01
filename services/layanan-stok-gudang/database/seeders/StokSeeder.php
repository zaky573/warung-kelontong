<?php

namespace Database\Seeders;

use App\Models\Stok;
use Illuminate\Database\Seeder;

class StokSeeder extends Seeder
{
    public function run(): void
    {
        // SKU sama dengan kasir-warung (kontrak). Tiga item sengaja berada
        // di bawah batas minimum supaya ekspor Purchase Order punya bahan.
        $daftar = [
            ['sku' => 'BRG-001', 'nama' => 'Beras Ramas 5 kg', 'jumlah' => 120, 'stok_minimum' => 40],
            ['sku' => 'BRG-002', 'nama' => 'Minyak Goreng 1 liter', 'jumlah' => 60, 'stok_minimum' => 24],
            ['sku' => 'BRG-003', 'nama' => 'Gula Pasir 1 kg', 'jumlah' => 18, 'stok_minimum' => 20],
            ['sku' => 'BRG-004', 'nama' => 'Telur Ayam 1 kg', 'jumlah' => 25, 'stok_minimum' => 30],
            ['sku' => 'BRG-005', 'nama' => 'Mie Instan Dus (40 pcs)', 'jumlah' => 10, 'stok_minimum' => 12],
            ['sku' => 'BRG-006', 'nama' => 'Sabun Cuci Piring 800 ml', 'jumlah' => 40, 'stok_minimum' => 15],
        ];

        foreach ($daftar as $item) {
            Stok::firstOrCreate(['sku' => $item['sku']], $item);
        }
    }
}
