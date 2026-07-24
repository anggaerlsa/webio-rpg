<?php

namespace App\Services;

use App\Events\FriendshipChanged;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pertemanan: cari pemain, kirim/terima/hapus permintaan, daftar teman.
 * Diperlukan saling berteman sebelum bisa DM (lihat ChatService).
 */
class FriendService
{
    /** Pertemanan antara dua user (arah mana pun), atau null. */
    public function between(User $a, User $b): ?Friendship
    {
        return Friendship::query()
            ->where(fn (Builder $q) => $q->where('requester_id', $a->id)->where('addressee_id', $b->id))
            ->orWhere(fn (Builder $q) => $q->where('requester_id', $b->id)->where('addressee_id', $a->id))
            ->first();
    }

    /** Status hubungan $me terhadap $other: none|friends|incoming|outgoing. */
    public function status(User $me, User $other): array
    {
        $f = $this->between($me, $other);
        if (! $f) {
            return ['status' => 'none', 'friendship_id' => null];
        }
        if ($f->status === 'accepted') {
            return ['status' => 'friends', 'friendship_id' => $f->id];
        }
        // pending: tergantung arah
        $incoming = (int) $f->addressee_id === (int) $me->id;

        return ['status' => $incoming ? 'incoming' : 'outgoing', 'friendship_id' => $f->id];
    }

    /**
     * Cari pemain (punya karakter) berdasarkan nama karakter/akun, kecuali diri.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(User $me, string $query, int $limit = 12): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }

        $users = User::query()
            ->whereKeyNot($me->id)
            ->whereHas('character')
            ->with('character')
            ->where(function (Builder $w) use ($q) {
                $w->whereHas('character', fn (Builder $c) => $c->where('name', 'like', "%{$q}%"))
                    ->orWhere('name', 'like', "%{$q}%");
            })
            ->limit($limit)->get();

        return $users->map(function (User $u) use ($me) {
            $st = $this->status($me, $u);

            return [
                'user_id' => $u->id,
                'name' => $u->displayName(),
                'status' => $st['status'],
                'friendship_id' => $st['friendship_id'],
            ];
        })->all();
    }

    /** Kirim permintaan teman. */
    public function sendRequest(User $me, User $target): Friendship
    {
        abort_if((int) $me->id === (int) $target->id, 422, 'Tidak bisa menambah diri sendiri.');
        $existing = $this->between($me, $target);
        if ($existing) {
            abort(422, $existing->status === 'accepted' ? 'Kalian sudah berteman.' : 'Permintaan sudah ada.');
        }

        $f = Friendship::create([
            'requester_id' => $me->id, 'addressee_id' => $target->id, 'status' => 'pending',
        ]);
        event(new FriendshipChanged($target->id, 'request'));

        return $f;
    }

    /** Terima permintaan (hanya yang dituju). */
    public function accept(User $me, Friendship $f): void
    {
        abort_unless((int) $f->addressee_id === (int) $me->id && $f->status === 'pending', 403, 'Tidak bisa menerima permintaan ini.');
        $f->update(['status' => 'accepted']);
        event(new FriendshipChanged($f->requester_id, 'accepted'));
    }

    /** Tolak/batalkan/hapus pertemanan (siapa pun yang terlibat). DM ikut terhapus (cascade). */
    public function remove(User $me, Friendship $f): void
    {
        abort_unless($f->involves($me), 403, 'Bukan pertemananmu.');
        $other = $f->other($me);
        $f->delete();
        if ($other) {
            event(new FriendshipChanged($other->id, 'removed'));
        }
    }

    /** Daftar teman (diterima). @return array<int, array<string, mixed>> */
    public function friends(User $me): array
    {
        return Friendship::involving($me)->where('status', 'accepted')
            ->with(['requester.character', 'addressee.character'])->get()
            ->map(fn (Friendship $f) => $this->friendDto($f, $me))
            ->sortBy('name')->values()->all();
    }

    /** Permintaan masuk (menunggu persetujuan $me). */
    public function incoming(User $me): array
    {
        return Friendship::where('addressee_id', $me->id)->where('status', 'pending')
            ->with('requester.character')->get()
            ->map(fn (Friendship $f) => $this->friendDto($f, $me))->values()->all();
    }

    /** Permintaan keluar (dikirim $me, menunggu). */
    public function outgoing(User $me): array
    {
        return Friendship::where('requester_id', $me->id)->where('status', 'pending')
            ->with('addressee.character')->get()
            ->map(fn (Friendship $f) => $this->friendDto($f, $me))->values()->all();
    }

    /** @return array<string, mixed> */
    private function friendDto(Friendship $f, User $me): array
    {
        $other = $f->other($me);

        return [
            'friendship_id' => $f->id,
            'user_id' => $other?->id,
            'name' => $other?->displayName() ?? 'Tanpa Nama',
        ];
    }
}
