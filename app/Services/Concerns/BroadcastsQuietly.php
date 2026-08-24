<?php

namespace App\Services\Concerns;

use Throwable;

/**
 * Menyiarkan event real-time tanpa mempertaruhkan aksi yang sudah selesai.
 *
 * Semua siaran di game ini bersifat pemanis: data sudah tersimpan lewat HTTP dan
 * sudah ikut di respons, siaran hanya menambah update langsung. Karena itu server
 * websocket (Reverb) yang mati TIDAK boleh menggagalkan kirim pesan, permintaan
 * teman, atau balasan forum — dulu itu membuat responsnya jadi 500 padahal datanya
 * sudah masuk. Kegagalan tetap dilaporkan ke log lewat `report()`, jadi Reverb
 * yang salah konfigurasi tidak hilang tanpa jejak.
 */
trait BroadcastsQuietly
{
    protected function broadcastQuietly(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
