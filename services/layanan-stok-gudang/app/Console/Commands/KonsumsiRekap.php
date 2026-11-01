<?php

namespace App\Console\Commands;

use App\Gudang\CatatRekap;
use App\Gudang\PesanTidakValid;
use App\Messaging\RabbitMQ;
use Illuminate\Console\Command;
use JsonException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class KonsumsiRekap extends Command
{
    protected $signature = 'gudang:rekap';

    protected $description = 'Penerima kedua: merekap transaksi (tunai & utang) untuk arus kas harian warung';

    public function handle(CatatRekap $catat): int
    {
        $koneksi = RabbitMQ::koneksi();
        $channel = $koneksi->channel();
        RabbitMQ::siapkanTopologi($channel);

        $this->info('Menunggu pesan di antrean gudang.rekap. Tekan Ctrl+C untuk berhenti.');

        $channel->basic_consume('gudang.rekap', '', false, false, false, false,
            function (AMQPMessage $pesan) use ($catat) {
                try {
                    $event = json_decode($pesan->getBody(), true, 512, JSON_THROW_ON_ERROR);
                    $messageId = $pesan->has('message_id') ? $pesan->get('message_id') : '';

                    $baru = $catat($event, $messageId);
                    $this->line(($baru ? 'Rekap tercatat   : ' : 'Duplikat dilewati : ').$pesan->getBody());
                    $pesan->ack();
                } catch (PesanTidakValid|JsonException $e) {
                    $this->error('Ditolak: '.$e->getMessage());
                    $pesan->nack(false);
                } catch (Throwable $e) {
                    $this->error('Gagal: '.$e->getMessage());
                    $pesan->nack(! $pesan->isRedelivered());
                }
            });

        $channel->consume();

        return self::SUCCESS;
    }
}
