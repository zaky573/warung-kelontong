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

        return new AMQPStreamConnection($c['host'], $c['port'], $c['user'], $c['password'], $c['vhost']);
    }

    /**
     * Topologi milik konsumen: sekaligus menjadi bukti pola publish-subscribe.
     * Satu event (exchange warung.transaksi) disalin ke dua antrean berbeda.
     */
    public static function siapkanTopologi(AMQPChannel $channel): void
    {
        $channel->exchange_declare('warung.transaksi', 'topic', false, true, false);

        // Dead letter exchange + antrean sampah berlabel
        $channel->exchange_declare('warung.dlx', 'fanout', false, true, false);
        $channel->queue_declare('gudang.dlq', false, true, false, false);
        $channel->queue_bind('gudang.dlq', 'warung.dlx');

        // Antrean 1: perbaruan stok — pesan gagal dialihkan ke DLQ
        $channel->queue_declare('gudang.stok', false, true, false, false, false,
            new AMQPTable(['x-dead-letter-exchange' => 'warung.dlx']));
        $channel->queue_bind('gudang.stok', 'warung.transaksi', 'transaksi.*');

        // Antrean 2: rekap transaksi (penerima kedua dari event yang sama)
        $channel->queue_declare('gudang.rekap', false, true, false, false, false,
            new AMQPTable(['x-dead-letter-exchange' => 'warung.dlx']));
        $channel->queue_bind('gudang.rekap', 'warung.transaksi', 'transaksi.*');
    }
}
