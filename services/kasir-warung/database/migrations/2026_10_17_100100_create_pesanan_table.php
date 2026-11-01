<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pesanan', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 32)->unique();      // WA-0001 / POS-0001
            $table->string('sumber', 16);              // whatsapp | kasir
            $table->string('metode_bayar', 16);        // tunai | utang
            $table->string('pelanggan')->nullable();   // diisi untuk kasbon digital
            $table->string('status', 16)->default('dikonfirmasi');
            $table->unsignedBigInteger('total');
            $table->timestamp('terjadi_pada');
            $table->timestamps();
        });

        Schema::create('pesanan_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesanan_id')->constrained('pesanan')->cascadeOnDelete();
            $table->string('sku', 16);
            $table->unsignedInteger('jumlah');
            $table->unsignedInteger('harga_satuan');
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesanan_item');
        Schema::dropIfExists('pesanan');
    }
};
