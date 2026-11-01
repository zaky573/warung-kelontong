<?php

namespace App\Console\Commands;

use App\Gudang\CatatTransaksiKeStok;
use App\Gudang\PesanTidakValid;
use App\Messaging\RabbitMQ;
use Illuminate\Console\Command;
use JsonException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class KonsumsiStok extends Command
{
    protected $signature = 'gudang:konsumsi';

    protected $description = 'Mengonsumsi antrean gudang.stok dan memperbarui stok fisik dari event transaksi POS';

    public function handle(CatatTransaksiKeStok $catat): int
    {
        $koneksi = RabbitMQ::koneksi();
        $channel = $koneksi->channel();
        RabbitMQ::siapkanTopologi($channel);
        $channel->basic_qos(0, 1, false);

        $this->info('Menunggu pesan di antrean gudang.stok. Tekan Ctrl+C untuk berhenti.');

        $channel->basic_consume('gudang.stok', '', false, false, false, false,
            function (AMQPMessage $pesan) use ($catat) {
                try {
                    $event = json_decode($pesan->getBody(), true, 512, JSON_THROW_ON_ERROR);
                    $messageId = $pesan->has('message_id') ? $pesan->get('message_id') : '';

                    $baru = $catat($event, $messageId);
                    $this->line(($baru ? 'Stok diperbarui   : ' : 'Duplikat dilewati : ').$pesan->getBody());
                    $pesan->ack();
                } catch (PesanTidakValid|JsonException $e) {
                    $this->error('Ditolak ke DLQ: '.$e->getMessage());
                    $pesan->nack(false);
                } catch (Throwable $e) {
                    $this->error('Gagal, pesan dikirim ulang: '.$e->getMessage());
                    $pesan->nack(! $pesan->isRedelivered());
                }
            });

        $channel->consume();

        return self::SUCCESS;
    }
}
