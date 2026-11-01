<?php

namespace Tests\Feature;

use App\Events\TransaksiTercatat;
use App\Listeners\TerbitkanTransaksi;
use App\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji Transactional Outbox:
 * Listener menyimpan event transaksi ke tabel outbox_messages.
 */
class TerbitkanTransaksiTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_menyimpan_event_ke_outbox(): void
    {
        $event = new TransaksiTercatat(
            kode: 'POS-0001',
            sumber: 'kasir',
            metodeBayar: 'tunai',
            pelanggan: null,
            items: [
                [
                    'sku' => 'BRG-001',
                    'jumlah' => 2,
                    'harga' => 72000,
                ],
            ],
            total: 144000,
        );

        // Jalankan listener secara langsung.
        app(TerbitkanTransaksi::class)->handle($event);

        // Pastikan pesan berhasil disimpan ke database.
        $outbox = OutboxMessage::sole();

        $this->assertSame($event->id, $outbox->message_id);
        $this->assertSame('TransaksiTercatat', $outbox->event_type);
        $this->assertSame(config('rabbitmq.exchange'), $outbox->exchange);
        $this->assertSame('transaksi.tunai', $outbox->routing_key);

        // Pastikan status awal pesan adalah pending.
        $this->assertSame('pending', $outbox->status);
        $this->assertSame(0, $outbox->attempts);

        // Pastikan isi payload sesuai dengan transaksi.
        $this->assertSame('POS-0001', $outbox->payload['kode']);
        $this->assertSame('kasir', $outbox->payload['sumber']);
        $this->assertSame('tunai', $outbox->payload['metode_bayar']);
        $this->assertSame(144000, $outbox->payload['total']);

        // Pastikan hanya satu pesan yang tersimpan.
        $this->assertDatabaseCount('outbox_messages', 1);
    }
}