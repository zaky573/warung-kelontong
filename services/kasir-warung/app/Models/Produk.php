<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produk extends Model
{
    protected $table = 'produk';

    protected $fillable = ['sku', 'nama', 'harga', 'stok'];

    protected $casts = [
        'harga' => 'integer',
        'stok' => 'integer',
    ];

    public function pesanan(): HasMany
    {
        return $this->hasMany(PesananItem::class, 'sku', 'sku');
    }
}
