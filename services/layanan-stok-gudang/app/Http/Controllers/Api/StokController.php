<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stok;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StokController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Stok::orderBy('sku')->get()->map(fn (Stok $s) => $s->toArray() + ['kritis' => $s->kritis()]);

        return response()->json(['data' => $data]);
    }

    public function show(string $sku): JsonResponse
    {
        $stok = Stok::where('sku', $sku)->first();

        if (! $stok) {
            return response()->json(['message' => "SKU {$sku} tidak ditemukan."], 404);
        }

        return response()->json(['data' => $stok->toArray() + ['kritis' => $stok->kritis()]]);
    }

    /**
     * TITIK INTEGRASI 1 — jawaban sinkron untuk Aplikasi POS.
     * Selalu menjawab 200; keputusan boleh/tidak ada di field ok.
     */
    public function validasi(Request $request): JsonResponse
    {
        $in = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku' => ['required', 'string', 'max:16'],
            'items.*.jumlah' => ['required', 'integer', 'min:1'],
        ]);

        $detail = [];
        $ok = true;

        foreach ($in['items'] as $item) {
            $stok = Stok::where('sku', $item['sku'])->first();
            $tersedia = $stok !== null && $stok->jumlah >= (int) $item['jumlah'];

            $detail[] = [
                'sku' => $item['sku'],
                'diminta' => (int) $item['jumlah'],
                'stok_gudang' => $stok?->jumlah ?? 0,
                'tersedia' => $tersedia,
            ];

            if (! $tersedia) {
                $ok = false;
            }
        }

        return response()->json([
            'ok' => $ok,
            'alasan' => $ok ? '' : 'Ada barang yang stok gudangnya tidak mencukupi.',
            'detail' => $detail,
        ]);
    }
}
