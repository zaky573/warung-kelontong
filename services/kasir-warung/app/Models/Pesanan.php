<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pesanan extends Model
{
    protected $table = 'pesanan';

    protected $fillable = ['kode', 'sumber', 'metode_bayar', 'pelanggan', 'status', 'total', 'terjadi_pada'];

    protected $casts = [
        'total' => 'integer',
        'terjadi_pada' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PesananItem::class);
    }
}
