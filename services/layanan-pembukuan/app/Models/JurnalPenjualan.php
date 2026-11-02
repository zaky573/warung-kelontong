<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JurnalPenjualan extends Model
{
    protected $table = 'jurnal_penjualan';

    protected $fillable = [
        'message_id',
        'kode',
        'sumber',
        'metode_bayar',
        'pelanggan',
        'sku',
        'jumlah',
        'harga_satuan',
        'total',
        'total_transaksi',
        'terjadi_pada',
    ];

    protected $casts = [
        'jumlah' => 'integer',
        'harga_satuan' => 'integer',
        'total' => 'integer',
        'total_transaksi' => 'integer',
        'terjadi_pada' => 'datetime',
    ];
}
