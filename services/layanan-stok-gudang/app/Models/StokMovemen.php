<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokMovemen extends Model
{
    protected $table = 'stok_movemen';

    protected $fillable = ['message_id', 'sku', 'jumlah', 'arah', 'sumber', 'terjadi_pada'];

    protected $casts = [
        'jumlah' => 'integer',
        'terjadi_pada' => 'datetime',
    ];
}
