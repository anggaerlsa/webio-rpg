<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Memberi tahu seorang user bahwa daftar teman/permintaannya berubah
 * (permintaan masuk, diterima, dibatalkan) agar panel teman menyegarkan diri.
 */
class FriendshipChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $userId, public string $action) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'friendship';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['action' => $this->action];
    }
}
