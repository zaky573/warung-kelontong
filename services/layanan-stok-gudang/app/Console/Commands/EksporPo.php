<?php

namespace App\Console\Commands;

use App\Models\Stok;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class EksporPo extends Command
{
    protected $signature = 'gudang:ekspor-po
        {--format=semua : csv | pdf | semua}
        {--tanggal= : Nama berkas (default hari ini)}';

    protected $description = 'TITIK INTEGRASI 3: membuat dokumen Purchase Order (CSV/PDF) dari barang yang stoknya di bawah batas minimum';

    public function handle(): int
    {
        $tanggal = $this->option('tanggal') ?: now()->format('Y-m-d');
        $format = strtolower($this->option('format'));

        $items = Stok::whereColumn('jumlah', '<=', 'stok_minimum')
            ->orderBy('sku')
            ->get()
            ->map(fn (Stok $s) => [
                'sku' => $s->sku,
                'nama' => $s->nama,
                'stok_sekarang' => $s->jumlah,
                'stok_minimum' => $s->stok_minimum,
                'kurang' => $s->stok_minimum - $s->jumlah,
                'usul_kulakan' => max(($s->stok_minimum * 2) - $s->jumlah, 1),
            ]);

        if ($items->isEmpty()) {
            $this->warn('Semua barang di atas batas minimum — tidak ada yang perlu dikulakan hari ini.');

            return self::SUCCESS;
        }

        $nama = 'PO-'.$tanggal;
        $dibuat = [];

        if ($format === 'csv' || $format === 'semua') {
            $csv = "SKU,Nama Barang,Stok Sekarang,Stok Minimum,Kurang,Usul Kulakan\n";
            foreach ($items as $baris) {
                $csv .= implode(',', [
                    $baris['sku'],
                    '"'.$baris['nama'].'"',
                    $baris['stok_sekarang'],
                    $baris['stok_minimum'],
                    $baris['kurang'],
                    $baris['usul_kulakan'],
                ])."\n";
            }
            Storage::disk('local')->put("ekspor/{$nama}.csv", $csv);
            $dibuat[] = storage_path("app/ekspor/{$nama}.csv");
        }

        if ($format === 'pdf' || $format === 'semua') {
            $pdf = Pdf::loadView('ekspor.po', [
                'judul' => 'PURCHASE ORDER - WARUNG KELONTONG',
                'nomor' => 'PO-'.$tanggal,
                'tanggal' => $tanggal,
                'items' => $items,
            ]);
            Storage::disk('local')->put("ekspor/{$nama}.pdf", $pdf->output());
            $dibuat[] = storage_path("app/ekspor/{$nama}.pdf");
        }

        $this->table(
            ['SKU', 'Nama', 'Sekarang', 'Minimum', 'Kurang', 'Usul Kulakan'],
            $items->all(),
        );

        $this->info('Purchase Order dibuat:');
        foreach ($dibuat as $berkas) {
            $this->line('  - '.$berkas);
        }
        $this->newLine();
        $this->comment('Cara pakai: berkas CSV dikirim ke distributor lewat chat, PDF dicetak untuk dibawa saat kulakan.');

        return self::SUCCESS;
    }
}
