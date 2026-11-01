<?php

namespace Database\Seeders;

use App\Models\Produk;
use Illuminate\Database\Seeder;

class ProdukSeeder extends Seeder
{
    public function run(): void
    {
        // SKU yang sama dipakai di layanan-stok-gudang (kontrak antar layanan)
        $daftar = [
            ['sku' => 'BRG-001', 'nama' => 'Beras Ramas 5 kg', 'harga' => 72000, 'stok' => 20],
            ['sku' => 'BRG-002', 'nama' => 'Minyak Goreng 1 liter', 'harga' => 18000, 'stok' => 15],
            ['sku' => 'BRG-003', 'nama' => 'Gula Pasir 1 kg', 'harga' => 17000, 'stok' => 12],
            ['sku' => 'BRG-004', 'nama' => 'Telur Ayam 1 kg', 'harga' => 28000, 'stok' => 10],
            ['sku' => 'BRG-005', 'nama' => 'Mie Instan Dus (40 pcs)', 'harga' => 138000, 'stok' => 6],
            ['sku' => 'BRG-006', 'nama' => 'Sabun Cuci Piring 800 ml', 'harga' => 14500, 'stok' => 8],
        ];

        foreach ($daftar as $produk) {
            Produk::firstOrCreate(['sku' => $produk['sku']], $produk);
        }
    }
}
