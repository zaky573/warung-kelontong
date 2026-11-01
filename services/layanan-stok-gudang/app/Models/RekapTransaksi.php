<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RekapTransaksi extends Model
{
    protected $table = 'rekap_transaksi';

    protected $fillable = ['message_id', 'kode', 'sumber', 'metode_bayar', 'pelanggan', 'jumlah_item', 'total', 'terjadi_pada'];

    protected $casts = [
        'jumlah_item' => 'integer',
        'total' => 'integer',
        'terjadi_pada' => 'datetime',
    ];
}
