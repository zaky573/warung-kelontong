<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 16)->unique();
            $table->string('nama', 100);
            $table->unsignedInteger('jumlah');
            $table->unsignedInteger('stok_minimum');
            $table->timestamps();
        });

        Schema::create('stok_movemen', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 36);       // kunci idempotensi
            $table->string('sku', 16);
            $table->unsignedInteger('jumlah');
            $table->string('arah', 16);             // keluar | masuk
            $table->string('sumber', 32);           // kode transaksi
            $table->timestamp('terjadi_pada');
            $table->timestamps();
            $table->unique(['message_id', 'sku']);
        });

        Schema::create('rekap_transaksi', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 36)->unique();
            $table->string('kode', 32);
            $table->string('sumber', 16);
            $table->string('metode_bayar', 16);
            $table->string('pelanggan')->nullable();
            $table->unsignedInteger('jumlah_item');
            $table->unsignedBigInteger('total');
            $table->timestamp('terjadi_pada');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_transaksi');
        Schema::dropIfExists('stok_movemen');
        Schema::dropIfExists('stok');
    }
};
