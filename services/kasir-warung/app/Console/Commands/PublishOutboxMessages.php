<?php

namespace App\Console\Commands;

use App\Messaging\RabbitPublisher;
use App\Models\OutboxMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('outbox:publish')]
#[Description('Mengirim pesan Outbox yang masih pending ke RabbitMQ')]
class PublishOutboxMessages extends Command
{
    public function handle(RabbitPublisher $publisher): int
    {
        $messages = OutboxMessage::query()
            ->where('status', 'pending')
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit(100)
            ->get();

        if ($messages->isEmpty()) {
            $this->info('Tidak ada pesan Outbox yang perlu dikirim.');

            return self::SUCCESS;
        }

        foreach ($messages as $message) {
            try {
                $publisher->publish(
                    $message->routing_key,
                    $message->payload,
                    $message->message_id,
                    $message->event_type
                );

                $message->update([
                    'status' => 'published',
                    'published_at' => now(),
                    'last_error' => null,
                ]);

                $this->info(
                    "Berhasil mengirim: {$message->message_id}"
                );

            } catch (Throwable $e) {

                $attempts = $message->attempts + 1;

                $delay = min(
                    300,
                    2 ** min($attempts, 8)
                );

                $message->update([
                    'attempts' => $attempts,
                    'available_at' => now()->addSeconds($delay),
                    'last_error' => $e->getMessage(),
                ]);

                $this->error(
                    "Gagal mengirim {$message->message_id}: {$e->getMessage()}"
                );
            }
        }

        $this->info('Proses pengiriman Outbox selesai.');

        return self::SUCCESS;
    }
}
