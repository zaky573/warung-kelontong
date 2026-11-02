<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal_penjualan', function (Blueprint $table) {
            $table->dropUnique('jurnal_penjualan_message_id_unique');

            $table->string('kode');
            $table->string('sumber');
            $table->string('metode_bayar');
            $table->string('pelanggan')->nullable();
            $table->unsignedBigInteger('total_transaksi');

            $table->unique(
                ['message_id', 'sku'],
                'jurnal_penjualan_message_sku_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('jurnal_penjualan', function (Blueprint $table) {
            $table->dropUnique('jurnal_penjualan_message_sku_unique');

            $table->dropColumn([
                'kode',
                'sumber',
                'metode_bayar',
                'pelanggan',
                'total_transaksi',
            ]);

            $table->unique('message_id');
        });
    }
};
