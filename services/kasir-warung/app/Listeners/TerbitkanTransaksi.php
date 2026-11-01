<?php

namespace App\Listeners;

use App\Events\TransaksiTercatat;
use App\Models\OutboxMessage;

class TerbitkanTransaksi
{
    public function handle(TransaksiTercatat $event): void
    {
        OutboxMessage::firstOrCreate(
            [
                'message_id' => $event->id,
            ],
            [
                'event_type' => 'TransaksiTercatat',
                'exchange' => config('rabbitmq.exchange'),
                'routing_key' => $event->routingKey(),
                'payload' => $event->payload(),
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => now(),
            ]
        );
    }
}
