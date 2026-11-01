<?php

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Respons kesalahan baku RFC 7807 "Problem Details for HTTP APIs".
 *
 * Semua kegagalan bisnis dijawab dengan bentuk yang seragam:
 *
 *     HTTP/1.1 409 Conflict
 *     Content-Type: application/problem+json
 *
 *     {
 *         "type":     "urn:warung-kelontong:masalah:stok-rak-kasir-kurang",
 *         "title":    "Stok Rak di Kasir Tidak Cukup",
 *         "status":   409,
 *         "detail":   "Stok rak di kasir tidak mencukupi untuk memenuhi pesanan ini.",
 *         "instance": "/api/v1/penjualan",
 *         "sisa_stok": [{"sku": "BRG-001", "stok_kasir": 1, "diminta": 5}]
 *     }
 *
 * `type`, `title`, `status`, dan `detail` adalah medan baku RFC 7807.
 * `instance` dan `sisa_stok` adalah medan perluasan — RFC 7807 mengizinkannya
 * selama tidak menimpa nama medan baku. Karena `detail` sudah terpakai,
 * rincian per SKU diberi nama `sisa_stok` (bukan `detail` lagi).
 *
 * `type` memakai skema URN, bukan URL, supaya tetap valid sebagai pengenal
 * meski repositori dipindah ke host lain.
 */
class ProblemDetails extends RuntimeException implements HttpExceptionInterface
{
    /**
     * Pengenal jenis masalah (RFC 7807 "type"), dipakai seragam di seluruh layanan
     * dan didokumentasikan di openapi/kasir-warung.yaml.
     */
    public const STOK_RAK_KASIR_KURANG = 'urn:warung-kelontong:masalah:stok-rak-kasir-kurang';
    public const STOK_GUDANG_KURANG = 'urn:warung-kelontong:masalah:stok-gudang-kurang';
    public const LAYANAN_TIDAK_TERJANGKAU = 'urn:warung-kelontong:masalah:layanan-tidak-terjangkau';
    public const ISIAN_TIDAK_VALID = 'urn:warung-kelontong:masalah:isian-tidak-valid';

    /**
     * @param  int  $status  kode HTTP yang akan dikirimkan
     * @param  string  $type  pengenal jenis masalah (URN)
     * @param  string  $title  judul singkat yang stabil untuk jenis masalah ini
     * @param  string  $detail  penjelasan spesifik untuk kejadian ini
     * @param  string|null  $instance  URI permintaan yang memicu masalah
     * @param  array<string, mixed>  $perluasan  medan perluasan di luar medan baku
     */
    public function __construct(
        private readonly int $status,
        private readonly string $type,
        private readonly string $title,
        string $detail = '',
        private readonly ?string $instance = null,
        private readonly array $perluasan = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($detail !== '' ? $detail : $title, $status, $previous);
    }

    /**
     * Kode HTTP yang harus dipakai untuk respons ini.
     */
    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * Header tambahan yang harus ikut dikirim bersama bodi masalah.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return ['Content-Type' => 'application/problem+json'];
    }

    /**
     * Medan baku RFC 7807 yang dibawa exception ini.
     */
    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Susun bodi respons RFC 7807.
     *
     * @param  string|null  $instance  URI permintaan, diisi oleh renderer di bootstrap/app.php
     * @return array<string, mixed>
     */
    public function toArray(?string $instance = null): array
    {
        $bodi = array_filter([
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->getMessage(),
            'instance' => $instance ?? $this->instance,
        ], fn ($nilai) => $nilai !== null && $nilai !== '');

        return array_merge($bodi, $this->perluasan);
    }
}
