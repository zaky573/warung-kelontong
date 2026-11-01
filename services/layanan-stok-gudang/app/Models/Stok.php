<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stok extends Model
{
    protected $table = 'stok';

    protected $fillable = ['sku', 'nama', 'jumlah', 'stok_minimum'];

    protected $casts = [
        'jumlah' => 'integer',
        'stok_minimum' => 'integer',
    ];

    public function kritis(): bool
    {
        return $this->jumlah <= $this->stok_minimum;
    }
}
