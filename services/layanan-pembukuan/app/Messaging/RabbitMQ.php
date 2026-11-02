<?php

namespace App\Messaging;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQ
{
    public static function koneksi(): AMQPStreamConnection
    {
        $c = config('rabbitmq');

        return new AMQPStreamConnection(
            $c['host'],
            $c['port'],
            $c['user'],
            $c['password'],
            $c['vhost']
        );
    }

    public static function siapkanTopologi(AMQPChannel $channel): void
    {
        $channel->exchange_declare(
            'warung.transaksi', 'topic', false, true, false
        );

        $channel->exchange_declare(
            'warung.dlx', 'fanout', false, true, false
        );

        $channel->queue_declare(
            'pembukuan.penjualan.dlq', false, true, false, false
        );

        $channel->queue_bind(
            'pembukuan.penjualan.dlq', 'warung.dlx'
        );

        $channel->queue_declare(
            'pembukuan.penjualan',
            false,
            true,
            false,
            false,
            false,
            new AMQPTable([
                'x-dead-letter-exchange' => 'warung.dlx'
            ])
        );

        $channel->queue_bind(
            'pembukuan.penjualan',
            'warung.transaksi',
            'transaksi.*'
        );
    }
}
