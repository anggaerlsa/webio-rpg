<?php

namespace App\Services;

use App\Events\PrivateMessageSent;
use App\Events\WorldMessageSent;
use App\Models\ChatMessage;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Chat fana: dunia (global) & DM (antar teman). Pesan lebih dari TTL_MINUTES
 * menit otomatis dihapus (prune dipanggil tiap baca + perintah chat:prune).
 * Real-time lewat broadcast (Reverb); riwayat awal diambil via HTTP.
 */
class ChatService
{
    public const TTL_MINUTES = 30;

    /** Hapus semua pesan yang lebih tua dari TTL. */
    public function prune(): int
    {
        return ChatMessage::where('created_at', '<', now()->subMinutes(self::TTL_MINUTES))->delete();
    }

    /** Pesan chat dunia terbaru (maks $limit), sudah dipangkas yang kedaluwarsa. */
    public function worldMessages(int $limit = 50): array
    {
        $this->prune();

        return ChatMessage::with('user.character')
            ->where('scope', 'world')
            ->latest('id')->limit($limit)->get()
            ->reverse()->values()
            ->map(fn (ChatMessage $m) => $this->dto($m))->all();
    }

    /** Kirim pesan ke chat dunia. */
    public function postWorld(User $user, string $body): ChatMessage
    {
        $message = ChatMessage::create(['user_id' => $user->id, 'scope' => 'world', 'body' => $body]);
        event(new WorldMessageSent($message, $user->displayName()));

        return $message;
    }

    /** Pesan DM sebuah pertemanan (maks $limit), sudah dipangkas. */
    public function dmMessages(Friendship $friendship, int $limit = 50): array
    {
        $this->prune();

        return ChatMessage::with('user.character')
            ->where('scope', 'dm')->where('friendship_id', $friendship->id)
            ->latest('id')->limit($limit)->get()
            ->reverse()->values()
            ->map(fn (ChatMessage $m) => $this->dto($m))->all();
    }

    /** Kirim pesan DM ke seorang teman (hanya pertemanan yang diterima). */
    public function postDm(User $user, Friendship $friendship, string $body): ChatMessage
    {
        abort_unless($friendship->status === 'accepted' && $friendship->involves($user), 403, 'Bukan temanmu.');

        $message = ChatMessage::create([
            'user_id' => $user->id, 'scope' => 'dm', 'friendship_id' => $friendship->id, 'body' => $body,
        ]);
        event(new PrivateMessageSent($message, $user->displayName()));

        return $message;
    }

    /** @return array<string, mixed> */
    private function dto(ChatMessage $m): array
    {
        return [
            'id' => $m->id,
            'user_id' => $m->user_id,
            'name' => $m->user?->displayName() ?? 'Tanpa Nama',
            'body' => $m->body,
            'at' => $m->created_at?->toIso8601String(),
        ];
    }

    /** Bungkus koleksi jadi DTO (dipakai eksternal bila perlu). */
    public function toDtos(Collection $messages): array
    {
        return $messages->map(fn (ChatMessage $m) => $this->dto($m))->all();
    }
}
