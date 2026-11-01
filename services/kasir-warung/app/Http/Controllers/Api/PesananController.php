<?php

namespace App\Http\Controllers\Api;

use App\Events\TransaksiTercatat;
use App\Exceptions\ProblemDetails;
use App\Exceptions\StokTidakTerjangkau;
use App\Models\OutboxMessage;
use App\Models\Pesanan;
use App\Http\Controllers\Controller;
use App\Models\Produk;
use App\Services\InventoryClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PesananController extends Controller
{
    public function __construct(private InventoryClient $inventory) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Pesanan::with('items')->latest('id')->get(),
        ]);
    }

    /**
     * TITIK INTEGRASI 1 — WhatsApp Order System -> POS Backend Service.
     * Gaya: RPC / REST request-reply. Stok divalidasi SINKRON dulu,
     * baru pesanan dikonfirmasi ke pelanggan.
     */
    public function whatsapp(Request $request): JsonResponse
    {
        $in = $request->validate([
            'kode' => ['required', 'string', 'max:32', 'unique:pesanan,kode'],
            'pelanggan' => ['nullable', 'string', 'max:100'],
            'metode_bayar' => ['required', 'in:tunai,utang'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.sku' => ['required', 'string', 'max:16'],
            'items.*.jumlah' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        // Panggilan sinkron ke Inventory Core Service (inti gaya RPC)
        try {
            $hasil = $this->inventory->validasi($in['items']);
        } catch (StokTidakTerjangkau $e) {
            // Gaya RPC punya ketergantungan waktu: kalau gudang tidak terjangkau,
            // pesanan ditahan. Inilah sisi lemah RPC yang dibandingkan dengan event.
            throw new ProblemDetails(
                status: 503,
                type: ProblemDetails::LAYANAN_TIDAK_TERJANGKAU,
                title: 'Layanan Stok Gudang Tidak Terjangkau',
                detail: $e->getMessage(),
                perluasan: [
                    'saran' => 'Kirim ulang pesanan (retry) — arsitektur RPC memiliki ketergantungan waktu.',
                ],
            );
        }

        if (! ($hasil['ok'] ?? false)) {
            throw new ProblemDetails(
                status: 409,
                type: ProblemDetails::STOK_GUDANG_KURANG,
                title: 'Stok Gudang Tidak Mencukupi',
                detail: $hasil['alasan'] ?? 'Stok gudang tidak mencukupi.',
                // `detail` sudah terpakai sebagai medan baku RFC 7807,
                // jadi rincian per SKU diberi nama `sisa_stok`.
                perluasan: ['sisa_stok' => $hasil['detail'] ?? []],
            );
        }

        return $this->catat($in, 'whatsapp');
    }

    /**
     * TITIK INTEGRASI 2 — Aplikasi POS Baru -> Inventory Core Service (modul stok & utang).
     * Gaya: Asynchronous / Event-Driven. TANPA panggilan sinkron apa pun,
     * supaya transaksi kasir tidak pernah terhambat oleh kondisi gudang.
     */
    public function kasir(Request $request): JsonResponse
    {
        $in = $request->validate([
            'kode' => ['nullable', 'string', 'max:32', 'unique:pesanan,kode'],
            'pelanggan' => ['nullable', 'string', 'max:100'],
            'metode_bayar' => ['required', 'in:tunai,utang'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.sku' => ['required', 'string', 'max:16'],
            'items.*.jumlah' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        return $this->catat($in, 'kasir');
    }

    /**
     * Pencatatan bersama: cek stok rak kasir -> simpan pesanan -> umumkan event.
     *
     * @param  array{kode: ?string, pelanggan: ?string, metode_bayar: string, items: array<int, array{sku: string, jumlah: int}>}  $in
     */
    private function catat(array $in, string $sumber): JsonResponse
    {
        // Cek salinan stok rak di kasir (tanpa jaringan, selalu cepat)
        $tidakCukup = [];
        foreach ($in['items'] as $item) {
            $stok = Produk::where('sku', $item['sku'])->value('stok') ?? 0;
            if ($stok < $item['jumlah']) {
                $tidakCukup[] = ['sku' => $item['sku'], 'stok_kasir' => $stok, 'diminta' => $item['jumlah']];
            }
        }

        if ($tidakCukup !== []) {
            throw new ProblemDetails(
                status: 409,
                type: ProblemDetails::STOK_RAK_KASIR_KURANG,
                title: 'Stok Rak di Kasir Tidak Cukup',
                detail: 'Stok rak di kasir tidak mencukupi untuk memenuhi pesanan ini.',
                // Rincian per SKU disimpan sebagai medan perluasan `sisa_stok`.
                perluasan: ['sisa_stok' => $tidakCukup],
            );
        }

        $kode = $in['kode'] ?? ('POS-'.str_pad((string) (Pesanan::count() + 1), 4, '0', STR_PAD_LEFT));

        $pesanan = DB::transaction(function () use ($in, $kode, $sumber) {
            $total = 0;
            $ringkas = [];

            foreach ($in['items'] as $item) {
                $produk = Produk::where('sku', $item['sku'])->firstOrFail();
                $subtotal = $produk->harga * $item['jumlah'];

                $ringkas[] = [
                    'sku' => $produk->sku,
                    'jumlah' => $item['jumlah'],
                    'harga' => $produk->harga,
                    'subtotal' => $subtotal,
                ];

                $total += $subtotal;

                $produk->decrement('stok', $item['jumlah']);
            }

            $pesanan = Pesanan::create([
                'kode' => $kode,
                'sumber' => $sumber,
                'metode_bayar' => $in['metode_bayar'],
                'pelanggan' => $in['pelanggan'] ?? null,
                'status' => 'dikonfirmasi',
                'total' => $total,
                'terjadi_pada' => now(),
            ]);

            
foreach ($ringkas as $baris) {
    $pesanan->items()->create([
        'sku' => $baris['sku'],
        'jumlah' => $baris['jumlah'],
        'harga_satuan' => $baris['harga'],
        'subtotal' => $baris['subtotal'],
    ]);
}

// Buat fakta transaksi dengan kontrak event yang sudah ada.
$event = new TransaksiTercatat(
    kode: $kode,
    sumber: $sumber,
    metodeBayar: $in['metode_bayar'],
    pelanggan: $in['pelanggan'] ?? null,
    items: collect($ringkas)->map(fn ($b) => [
        'sku' => $b['sku'],
        'jumlah' => $b['jumlah'],
        'harga' => $b['harga'],
    ])->all(),
    total: $total,
);

// Simpan event ke Outbox dalam transaksi database yang sama.
OutboxMessage::create([
    'message_id' => $event->id,
    'event_type' => 'TransaksiTercatat',
    'exchange' => config('rabbitmq.exchange'),
    'routing_key' => $event->routingKey(),
    'payload' => $event->payload(),
    'status' => 'pending',
    'attempts' => 0,
    'available_at' => now(),
]);

return [$pesanan, $ringkas, $total];

        });

        [$pesanan, $ringkas, $total] = $pesanan;


        return response()->json([
            'status' => 'dikonfirmasi',
            'kode' => $pesanan->kode,
            'sumber' => $sumber,
            'metode_bayar' => $pesanan->metode_bayar,
            'pelanggan' => $pesanan->pelanggan,
            'total' => $total,
            'items' => $ringkas,
            'pesan' => $sumber === 'whatsapp'
                ? 'Pesanan WhatsApp dikonfirmasi; stok gudang menyusul lewat antrean pesan.'
                : 'Transaksi tercatat; pembaruan stok gudang berjalan di latar belakang.',
        ], 201);
    }
}
