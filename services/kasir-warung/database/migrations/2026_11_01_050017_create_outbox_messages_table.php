<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();

            $table->uuid('message_id')->unique();

            $table->string('event_type', 100);
            $table->string('exchange', 100);
            $table->string('routing_key', 150);

            $table->json('payload');

            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);

            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('published_at')->nullable();

            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(
                ['status', 'available_at'],
                'outbox_status_available_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
