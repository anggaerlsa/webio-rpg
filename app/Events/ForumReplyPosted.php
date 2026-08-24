<?php

namespace App\Events;

use App\Models\ForumTopic;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Memberi tahu seorang pemain bahwa ada balasan baru di topiknya (atau pada pesan
 * yang dikutip) agar lencana Balai Warta di sidebar bertambah tanpa polling.
 * Isi topik sendiri TIDAK disiarkan — forum dibaca lewat HTTP biasa.
 *
 * Disiarkan langsung; kegagalannya ditelan `BroadcastsQuietly` — sama seperti
 * chat & pertemanan, jadi Reverb yang mati tidak menggagalkan penulisan balasan.
 */
class ForumReplyPosted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $userId, public ForumTopic $topic, public string $name) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'forum-reply';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'topic' => $this->topic->title,
            'slug' => $this->topic->slug,
            'by' => $this->name,
        ];
    }
}
