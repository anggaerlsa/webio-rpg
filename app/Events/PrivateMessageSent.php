<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PrivateMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public ChatMessage $message, public string $name) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.dm.'.$this->message->friendship_id);
    }

    public function broadcastAs(): string
    {
        return 'message';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'user_id' => $this->message->user_id,
            'friendship_id' => $this->message->friendship_id,
            'name' => $this->name,
            'body' => $this->message->body,
            'at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
