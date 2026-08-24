<?php

namespace App\Services;

use App\Events\ForumReplyPosted;
use App\Models\Character;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\ForumVote;
use App\Models\User;
use App\Services\Concerns\BroadcastsQuietly;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Balai Warta — forum diskusi PERMANEN (berbeda dari chat yang fana 30 menit).
 * Kategori → Topik → Pesan. Pesan pertama sebuah topik adalah ForumPost ber-`is_first`
 * agar ubah/kutip/apresiasi memakai satu jalur kode.
 *
 * Apresiasi (+1, satu per pemain per pesan) terakumulasi jadi `characters.reputation`
 * — murni sosial, tidak memberi XP/emas/stat (mencegah farming lewat spam).
 * Semua izin diputuskan di sini; klien hanya menerima flag hasil (server-authoritative).
 */
class ForumService
{
    use BroadcastsQuietly;

    /** Jendela waktu penulis boleh mengubah pesannya sendiri. */
    public const EDIT_WINDOW_MINUTES = 15;

    public const TOPICS_PER_PAGE = 20;

    public const POSTS_PER_PAGE = 20;

    public function __construct(private RankService $ranks) {}

    // ── Baca ────────────────────────────────────────────────────────────────

    /**
     * Daftar kategori + ringkasan aktivitas (jumlah topik/pesan & topik terakhir).
     *
     * @return array<int, array<string, mixed>>
     */
    public function categories(User $user): array
    {
        $counts = ForumTopic::selectRaw('category_id, COUNT(*) as topics, COALESCE(SUM(replies_count), 0) as replies')
            ->groupBy('category_id')->get()->keyBy('category_id');

        return ForumCategory::orderBy('position')->orderBy('id')->get()
            ->map(function (ForumCategory $category) use ($counts, $user) {
                $row = $counts->get($category->id);
                $latest = $category->topics()->with('lastPostUser')
                    ->orderByDesc('last_post_at')->orderByDesc('id')->first();

                return [
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'description' => $category->description,
                    'is_locked' => $category->is_locked,
                    'min_rank' => $category->min_rank,
                    'topics' => (int) ($row->topics ?? 0),
                    'posts' => (int) ($row->topics ?? 0) + (int) ($row->replies ?? 0),
                    'can_post' => $this->postBlockReason($user, $category) === null,
                    'latest' => $latest ? [
                        'title' => $latest->title,
                        'slug' => $latest->slug,
                        'at' => $latest->last_post_at?->toIso8601String(),
                        'by' => $latest->lastPostUser?->displayName(),
                    ] : null,
                ];
            })->all();
    }

    /** Topik sebuah kategori: yang disematkan di atas, sisanya menurut aktivitas terakhir. */
    public function topics(ForumCategory $category): LengthAwarePaginator
    {
        return $category->topics()->with(['user.character', 'lastPostUser'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_post_at')
            ->orderByDesc('id')
            ->paginate(self::TOPICS_PER_PAGE)
            ->through(fn (ForumTopic $topic) => [
                'slug' => $topic->slug,
                'title' => $topic->title,
                'author' => $topic->user?->displayName() ?? 'Tanpa Nama',
                'is_pinned' => $topic->is_pinned,
                'is_locked' => $topic->is_locked,
                'replies' => $topic->replies_count,
                'views' => $topic->views,
                'created_at' => $topic->created_at?->toIso8601String(),
                'last_post_at' => $topic->last_post_at?->toIso8601String(),
                'last_post_by' => $topic->lastPostUser?->displayName(),
            ]);
    }

    /** Pesan-pesan sebuah topik (dengan status apresiasi & izin milik pembaca). */
    public function posts(ForumTopic $topic, User $viewer): LengthAwarePaginator
    {
        $paginator = $topic->posts()->with(['user.character', 'replyTo.user', 'topic'])
            ->orderBy('id')
            ->paginate(self::POSTS_PER_PAGE);

        $appreciated = ForumVote::where('user_id', $viewer->id)
            ->whereIn('post_id', collect($paginator->items())->pluck('id'))
            ->pluck('post_id')->all();

        return $paginator->through(fn (ForumPost $post) => $this->postDto($post, $viewer, $appreciated));
    }

    /** Catat satu kunjungan topik (dipanggil saat halaman topik dibuka). */
    public function countView(ForumTopic $topic): void
    {
        $topic->increment('views');
    }

    // ── Izin ────────────────────────────────────────────────────────────────

    /** Alasan pemain tidak boleh membuka topik di kategori ini, atau null bila boleh. */
    public function postBlockReason(User $user, ForumCategory $category): ?string
    {
        if (! $user->character) {
            return 'Buat karakter dulu sebelum menulis di Balai Warta.';
        }
        if ($category->is_locked && ! $user->isSuperadmin()) {
            return 'Kategori ini hanya untuk maklumat para Dewa.';
        }
        if ($category->min_rank && $this->ranks->rankIndex($user->character->rank) < $this->ranks->rankIndex($category->min_rank)) {
            return "Kategori ini butuh Rank {$category->min_rank}.";
        }

        return null;
    }

    /** Alasan pemain tidak boleh membalas di topik ini, atau null bila boleh. */
    public function replyBlockReason(User $user, ForumTopic $topic): ?string
    {
        if (! $user->character) {
            return 'Buat karakter dulu sebelum menulis di Balai Warta.';
        }
        if ($topic->is_locked && ! $user->isSuperadmin()) {
            return 'Topik ini sudah dikunci.';
        }

        $category = $topic->category;
        if ($category?->min_rank && $this->ranks->rankIndex($user->character->rank) < $this->ranks->rankIndex($category->min_rank)) {
            return "Kategori ini butuh Rank {$category->min_rank}.";
        }

        return null;
    }

    public function canEdit(User $user, ForumPost $post): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }
        if ((int) $post->user_id !== (int) $user->id || $post->topic?->is_locked) {
            return false;
        }

        return $post->created_at?->gt(now()->subMinutes(self::EDIT_WINDOW_MINUTES)) ?? false;
    }

    public function canDelete(User $user, ForumPost $post): bool
    {
        if ($post->is_first) {
            return false; // hapus topiknya, bukan pesan pertamanya
        }

        return $user->isSuperadmin() || ((int) $post->user_id === (int) $user->id && ! $post->topic?->is_locked);
    }

    public function isModerator(User $user): bool
    {
        return $user->isSuperadmin();
    }

    // ── Tulis ───────────────────────────────────────────────────────────────

    /** Buka topik baru (pesan pertama sekalian dibuat). */
    public function createTopic(User $user, ForumCategory $category, string $title, string $body): ForumTopic
    {
        $reason = $this->postBlockReason($user, $category);
        abort_unless($reason === null, 422, $reason);

        return DB::transaction(function () use ($user, $category, $title, $body) {
            $topic = ForumTopic::create([
                'category_id' => $category->id,
                'user_id' => $user->id,
                'title' => $title,
                'slug' => 'topik-'.Str::random(10), // sementara — slug final butuh id
                'last_post_at' => now(),
                'last_post_user_id' => $user->id,
            ]);

            $topic->forceFill(['slug' => $this->slugFor($title, $topic->id)])->save();

            ForumPost::create([
                'topic_id' => $topic->id,
                'user_id' => $user->id,
                'body' => $body,
                'is_first' => true,
            ]);

            return $topic;
        });
    }

    /** Balas sebuah topik; `$replyToId` opsional untuk mengutip satu pesan. */
    public function reply(User $user, ForumTopic $topic, string $body, ?int $replyToId = null): ForumPost
    {
        $reason = $this->replyBlockReason($user, $topic);
        abort_unless($reason === null, 422, $reason);

        $quoted = $replyToId ? ForumPost::where('topic_id', $topic->id)->find($replyToId) : null;

        $post = DB::transaction(function () use ($user, $topic, $body, $quoted) {
            $post = ForumPost::create([
                'topic_id' => $topic->id,
                'user_id' => $user->id,
                'body' => $body,
                'reply_to_id' => $quoted?->id,
            ]);

            $topic->forceFill([
                'replies_count' => $topic->replies_count + 1,
                'last_post_at' => $post->created_at,
                'last_post_user_id' => $user->id,
            ])->save();

            return $post;
        });

        // Kabari penulis topik & pemilik pesan yang dikutip (bukan diri sendiri).
        $recipients = collect([$topic->user_id, $quoted?->user_id])
            ->filter()->unique()->reject(fn ($id) => (int) $id === (int) $user->id);

        foreach ($recipients as $userId) {
            $this->broadcastQuietly(new ForumReplyPosted((int) $userId, $topic, $user->displayName()));
        }

        return $post;
    }

    public function editPost(User $user, ForumPost $post, string $body): void
    {
        abort_unless($this->canEdit($user, $post), 403, 'Pesan ini tidak bisa kamu ubah lagi.');

        $post->forceFill(['body' => $body, 'edited_at' => now()])->save();
    }

    /** Hapus-lunak sebuah balasan (jejak moderasi tetap ada di DB). */
    public function deletePost(User $user, ForumPost $post): void
    {
        abort_if($post->is_first, 422, 'Pesan pertama tidak bisa dihapus — hapus topiknya.');
        abort_unless($this->canDelete($user, $post), 403, 'Pesan ini tidak bisa kamu hapus.');

        DB::transaction(function () use ($post) {
            $this->addReputation($post->user_id, -$post->appreciations);
            $post->delete();

            $topic = $post->topic;
            $last = $topic->posts()->orderByDesc('id')->first();
            $topic->forceFill([
                'replies_count' => max(0, $topic->replies_count - 1),
                'last_post_at' => $last?->created_at,
                'last_post_user_id' => $last?->user_id,
            ])->save();
        });
    }

    /**
     * Apresiasi sebuah pesan (toggle). Menambah/mengurangi reputasi penulisnya.
     *
     * @return array{active: bool, count: int}
     */
    public function appreciate(User $user, ForumPost $post): array
    {
        abort_unless($user->character, 403, 'Buat karakter dulu untuk memberi apresiasi.');
        abort_if((int) $post->user_id === (int) $user->id, 422, 'Tidak bisa mengapresiasi pesanmu sendiri.');

        return DB::transaction(function () use ($user, $post) {
            $vote = ForumVote::where('post_id', $post->id)->where('user_id', $user->id)->first();

            if ($vote) {
                $vote->delete();
                $post->decrement('appreciations');
                $this->addReputation($post->user_id, -1);
                $active = false;
            } else {
                ForumVote::create(['post_id' => $post->id, 'user_id' => $user->id, 'value' => 1]);
                $post->increment('appreciations');
                $this->addReputation($post->user_id, 1);
                $active = true;
            }

            return ['active' => $active, 'count' => (int) $post->refresh()->appreciations];
        });
    }

    // ── Moderasi (Dewa) ─────────────────────────────────────────────────────

    public function setPinned(User $user, ForumTopic $topic, bool $pinned): void
    {
        $this->ensureModerator($user);
        $topic->forceFill(['is_pinned' => $pinned])->save();
    }

    public function setLocked(User $user, ForumTopic $topic, bool $locked): void
    {
        $this->ensureModerator($user);
        $topic->forceFill(['is_locked' => $locked])->save();
    }

    public function deleteTopic(User $user, ForumTopic $topic): void
    {
        $this->ensureModerator($user);

        DB::transaction(function () use ($topic) {
            // Tarik kembali reputasi dari apresiasi yang ikut terhapus.
            $topic->posts()->selectRaw('user_id, COALESCE(SUM(appreciations), 0) as total')
                ->groupBy('user_id')->get()
                ->each(fn ($row) => $this->addReputation((int) $row->user_id, -(int) $row->total));

            $topic->delete();
        });
    }

    private function ensureModerator(User $user): void
    {
        abort_unless($this->isModerator($user), 403, 'Hanya Dewa yang bisa menata Balai Warta.');
    }

    // ── Lencana "balasan baru" ──────────────────────────────────────────────

    /**
     * Jumlah pesan baru (dari orang lain) di topik-topik yang pemain ikut menulis,
     * sejak kunjungan terakhirnya ke Balai Warta. Satu query — tanpa polling.
     */
    public function unreadCount(User $user): int
    {
        if (! $user->character) {
            return 0;
        }

        return ForumPost::whereIn('topic_id', ForumPost::query()->where('user_id', $user->id)->select('topic_id'))
            ->where('user_id', '!=', $user->id)
            ->where('is_first', false) // yang dihitung balasan, bukan pesan pembuka topik
            ->when($user->forum_seen_at, fn ($query, $seen) => $query->where('created_at', '>', $seen))
            ->count();
    }

    public function markSeen(User $user): void
    {
        $user->forceFill(['forum_seen_at' => now()])->save();
    }

    // ── Internal ────────────────────────────────────────────────────────────

    private function slugFor(string $title, int $id): string
    {
        $base = Str::limit(Str::slug($title), 60, '');

        return ($base !== '' ? $base : 'topik').'-'.$id;
    }

    private function addReputation(int $userId, int $delta): void
    {
        $character = Character::where('user_id', $userId)->first();
        if (! $character || $delta === 0) {
            return;
        }

        $character->forceFill(['reputation' => max(0, (int) $character->reputation + $delta)])->save();
    }

    /**
     * @param  array<int, int>  $appreciated  id pesan yang sudah diapresiasi pembaca
     * @return array<string, mixed>
     */
    private function postDto(ForumPost $post, User $viewer, array $appreciated): array
    {
        $character = $post->user?->character;

        return [
            'id' => $post->id,
            'user_id' => $post->user_id,
            'name' => $post->user?->displayName() ?? 'Tanpa Nama',
            'job' => $post->user?->job,
            'rank' => $character?->rank,
            'level' => $character?->level,
            'reputation' => (int) ($character?->reputation ?? 0),
            'body' => $post->body,
            'is_first' => $post->is_first,
            'at' => $post->created_at?->toIso8601String(),
            'edited_at' => $post->edited_at?->toIso8601String(),
            'appreciations' => $post->appreciations,
            'appreciated' => in_array($post->id, $appreciated, true),
            'can_appreciate' => (int) $post->user_id !== (int) $viewer->id && $viewer->character !== null,
            'can_edit' => $this->canEdit($viewer, $post),
            'can_delete' => $this->canDelete($viewer, $post),
            'reply_to' => $post->replyTo ? [
                'id' => $post->replyTo->id,
                'name' => $post->replyTo->user?->displayName() ?? 'Tanpa Nama',
                'excerpt' => Str::limit($post->replyTo->body, 120),
            ] : null,
        ];
    }
}
