<?php

namespace Tests\Feature;

use App\Events\ForumReplyPosted;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use App\Services\ForumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ForumTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $overrides */
    private function player(string $charName, array $overrides = []): User
    {
        $user = User::factory()->create();
        $user->characters()->create(array_merge([
            'name' => $charName, 'level' => 1, 'xp' => 0, 'hp' => 50, 'max_hp' => 50,
            'attack' => 10, 'defense' => 5, 'gold' => 0, 'is_alive' => true, 'rank' => 'F',
        ], $overrides));

        return $user->fresh();
    }

    private function dewa(): User
    {
        $user = $this->player('Dewa Uji');
        $user->role = 'superadmin';
        $user->save();

        return $user->fresh();
    }

    /** Kategori bawaan dari migrasi. */
    private function category(string $slug = 'kedai-minum'): ForumCategory
    {
        return ForumCategory::where('slug', $slug)->firstOrFail();
    }

    private function forum(): ForumService
    {
        return app(ForumService::class);
    }

    private function topicBy(User $user, string $title = 'Topik Uji'): ForumTopic
    {
        return $this->forum()->createTopic($user, $this->category(), $title, 'Isi pembuka topik.');
    }

    public function test_forum_index_loads_and_marks_visit(): void
    {
        $user = $this->player('Aria');

        $this->actingAs($user)->get(route('forum.index'))->assertOk();

        $this->assertNotNull($user->fresh()->forum_seen_at);
        $this->assertSame(5, ForumCategory::count()); // kategori bawaan migrasi
    }

    public function test_creating_a_topic_also_creates_the_first_post(): void
    {
        $user = $this->player('Aria');

        $this->actingAs($user)->post(route('forum.topic.store', 'kedai-minum'), [
            'title' => 'Ale terbaik di benua',
            'body' => 'Menurutku ale Eldoria juaranya.',
        ])->assertRedirect();

        $topic = ForumTopic::firstOrFail();
        $this->assertSame('ale-terbaik-di-benua-'.$topic->id, $topic->slug);
        $this->assertSame(0, $topic->replies_count);
        $this->assertDatabaseHas('forum_posts', [
            'topic_id' => $topic->id, 'user_id' => $user->id, 'is_first' => true,
        ]);
    }

    public function test_reply_updates_counters_and_notifies_the_topic_author(): void
    {
        Event::fake([ForumReplyPosted::class]);
        $penulis = $this->player('Aria');
        $pembalas = $this->player('Borin');
        $topic = $this->topicBy($penulis);

        $this->actingAs($pembalas)->post(route('forum.reply', $topic->slug), ['body' => 'Setuju sekali.'])
            ->assertRedirect();

        $topic->refresh();
        $this->assertSame(1, $topic->replies_count);
        $this->assertSame($pembalas->id, $topic->last_post_user_id);
        Event::assertDispatched(ForumReplyPosted::class, fn (ForumReplyPosted $e) => $e->userId === $penulis->id);
    }

    public function test_replying_to_your_own_topic_notifies_nobody(): void
    {
        Event::fake([ForumReplyPosted::class]);
        $user = $this->player('Aria');
        $topic = $this->topicBy($user);

        $this->forum()->reply($user, $topic, 'Menambahkan catatan sendiri.');

        Event::assertNotDispatched(ForumReplyPosted::class);
    }

    public function test_locked_category_only_accepts_topics_from_dewa(): void
    {
        $player = $this->player('Aria');
        $dewa = $this->dewa();

        $this->actingAs($player)->post(route('forum.topic.store', 'warta-kerajaan'), [
            'title' => 'Maklumat palsu', 'body' => 'Aku dewa juga kok.',
        ])->assertRedirect()->assertSessionHas('error');

        $this->actingAs($dewa)->post(route('forum.topic.store', 'warta-kerajaan'), [
            'title' => 'Maklumat resmi', 'body' => 'Pajak turun.',
        ])->assertRedirect();

        $this->assertSame(1, ForumTopic::count());
    }

    public function test_locked_topic_rejects_replies_from_players(): void
    {
        $penulis = $this->player('Aria');
        $pembalas = $this->player('Borin');
        $dewa = $this->dewa();
        $topic = $this->topicBy($penulis);

        $this->forum()->setLocked($dewa, $topic, true);

        $this->actingAs($pembalas)->post(route('forum.reply', $topic->slug), ['body' => 'Masih boleh?'])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, $topic->fresh()->replies_count);
    }

    public function test_appreciation_toggles_and_moves_reputation(): void
    {
        $penulis = $this->player('Aria');
        $pembaca = $this->player('Borin');
        $topic = $this->topicBy($penulis);
        $post = $topic->firstPost;

        $hasil = $this->forum()->appreciate($pembaca, $post);
        $this->assertTrue($hasil['active']);
        $this->assertSame(1, $hasil['count']);
        $this->assertSame(1, (int) $penulis->character->fresh()->reputation);

        $hasil = $this->forum()->appreciate($pembaca, $post->fresh());
        $this->assertFalse($hasil['active']);
        $this->assertSame(0, $hasil['count']);
        $this->assertSame(0, (int) $penulis->character->fresh()->reputation);
        $this->assertDatabaseCount('forum_votes', 0);
    }

    public function test_cannot_appreciate_your_own_post(): void
    {
        $user = $this->player('Aria');
        $topic = $this->topicBy($user);

        $this->expectException(HttpException::class);
        $this->forum()->appreciate($user, $topic->firstPost);
    }

    public function test_edit_window_closes_after_the_grace_period(): void
    {
        $user = $this->player('Aria');
        $topic = $this->topicBy($user);
        $post = $topic->firstPost;

        $this->assertTrue($this->forum()->canEdit($user, $post));

        $post->forceFill(['created_at' => now()->subMinutes(ForumService::EDIT_WINDOW_MINUTES + 1)])->save();
        $this->assertFalse($this->forum()->canEdit($user, $post->fresh()));

        $this->expectException(HttpException::class);
        $this->forum()->editPost($user, $post->fresh(), 'Curang, ubah belakangan.');
    }

    public function test_dewa_can_edit_any_post_at_any_time(): void
    {
        $user = $this->player('Aria');
        $dewa = $this->dewa();
        $topic = $this->topicBy($user);
        $post = $topic->firstPost;
        $post->forceFill(['created_at' => now()->subDay()])->save();

        $this->forum()->editPost($dewa, $post->fresh(), 'Disunting Dewa.');

        $this->assertSame('Disunting Dewa.', $post->fresh()->body);
        $this->assertNotNull($post->fresh()->edited_at);
    }

    public function test_author_can_delete_own_reply_and_counters_shrink(): void
    {
        $penulis = $this->player('Aria');
        $pembalas = $this->player('Borin');
        $topic = $this->topicBy($penulis);
        $reply = $this->forum()->reply($pembalas, $topic, 'Balasan yang nanti dihapus.');

        $this->forum()->deletePost($pembalas, $reply);

        $topic->refresh();
        $this->assertSame(0, $topic->replies_count);
        $this->assertSame($penulis->id, $topic->last_post_user_id); // kembali ke pesan pertama
        $this->assertSoftDeleted('forum_posts', ['id' => $reply->id]);
    }

    public function test_first_post_cannot_be_deleted_on_its_own(): void
    {
        $user = $this->player('Aria');
        $topic = $this->topicBy($user);

        $this->expectException(HttpException::class);
        $this->forum()->deletePost($user, $topic->firstPost);
    }

    public function test_a_stranger_cannot_delete_someone_elses_reply(): void
    {
        $penulis = $this->player('Aria');
        $pembalas = $this->player('Borin');
        $orangLain = $this->player('Cael');
        $topic = $this->topicBy($penulis);
        $reply = $this->forum()->reply($pembalas, $topic, 'Punyaku.');

        $this->expectException(HttpException::class);
        $this->forum()->deletePost($orangLain, $reply);
    }

    public function test_only_dewa_can_pin_lock_or_delete_a_topic(): void
    {
        $user = $this->player('Aria');
        $topic = $this->topicBy($user);

        $this->actingAs($user)->post(route('forum.topic.pin', $topic->slug))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertFalse($topic->fresh()->is_pinned);

        $dewa = $this->dewa();
        $this->actingAs($dewa)->post(route('forum.topic.pin', $topic->slug))->assertRedirect();
        $this->assertTrue($topic->fresh()->is_pinned);

        $this->actingAs($dewa)->delete(route('forum.topic.destroy', $topic->slug))->assertRedirect();
        $this->assertDatabaseCount('forum_topics', 0);
        $this->assertDatabaseCount('forum_posts', 0); // cascade
    }

    public function test_deleting_a_topic_reclaims_the_reputation_it_gave(): void
    {
        $penulis = $this->player('Aria');
        $pembaca = $this->player('Borin');
        $dewa = $this->dewa();
        $topic = $this->topicBy($penulis);
        $this->forum()->appreciate($pembaca, $topic->firstPost);
        $this->assertSame(1, (int) $penulis->character->fresh()->reputation);

        $this->forum()->deleteTopic($dewa, $topic);

        $this->assertSame(0, (int) $penulis->character->fresh()->reputation);
    }

    public function test_unread_badge_counts_other_peoples_replies_since_last_visit(): void
    {
        $penulis = $this->player('Aria');
        $pembalas = $this->player('Borin');
        $topic = $this->topicBy($penulis);

        $this->assertSame(0, $this->forum()->unreadCount($penulis));

        $this->forum()->reply($pembalas, $topic, 'Balasan pertama.');
        $this->assertSame(1, $this->forum()->unreadCount($penulis->fresh()));
        $this->assertSame(0, $this->forum()->unreadCount($pembalas->fresh())); // balasannya sendiri

        $this->forum()->markSeen($penulis);
        $this->assertSame(0, $this->forum()->unreadCount($penulis->fresh()));
    }

    public function test_writing_requires_a_character(): void
    {
        $user = User::factory()->create(); // tanpa karakter

        $this->actingAs($user)->post(route('forum.topic.store', 'kedai-minum'), [
            'title' => 'Hantu tanpa tubuh', 'body' => 'Aku belum punya karakter.',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseCount('forum_topics', 0);
    }

    public function test_rank_gated_category_blocks_low_rank_players(): void
    {
        $category = $this->category('papan-strategi');
        $category->update(['min_rank' => 'C']);
        $rendah = $this->player('Aria', ['rank' => 'F']);
        $kurang = $this->player('Borin', ['rank' => 'D']);
        $pas = $this->player('Cael', ['rank' => 'C']);
        $tinggi = $this->player('Dain', ['rank' => 'A']);

        $this->assertNotNull($this->forum()->postBlockReason($rendah, $category));
        $this->assertNotNull($this->forum()->postBlockReason($kurang, $category));
        $this->assertNull($this->forum()->postBlockReason($pas, $category));
        $this->assertNull($this->forum()->postBlockReason($tinggi, $category));
    }

    public function test_opening_a_topic_counts_a_view(): void
    {
        $user = $this->player('Aria');
        $topic = $this->topicBy($user);

        $this->actingAs($user)->get(route('forum.topic', $topic->slug))->assertOk();

        $this->assertSame(1, $topic->fresh()->views);
    }

    public function test_quoting_links_the_reply_to_the_quoted_post(): void
    {
        $penulis = $this->player('Aria');
        $pembalas = $this->player('Borin');
        $topic = $this->topicBy($penulis);

        $reply = $this->forum()->reply($pembalas, $topic, 'Mengutip.', $topic->firstPost->id);

        $this->assertSame($topic->firstPost->id, $reply->reply_to_id);
    }

    public function test_only_superadmin_can_manage_forum_categories(): void
    {
        $player = $this->player('Aria');
        $dewa = $this->dewa();

        $this->actingAs($player)->get(route('admin.forum-categories.index'))->assertStatus(403);

        $this->actingAs($dewa)->get(route('admin.forum-categories.index'))->assertOk();
        $this->actingAs($dewa)->post(route('admin.forum-categories.store'), [
            'slug' => 'balai-lelang', 'name' => 'Balai Lelang', 'position' => 9,
        ])->assertRedirect(route('admin.forum-categories.index'));

        $this->assertDatabaseHas('forum_categories', ['slug' => 'balai-lelang', 'position' => 9]);
    }
}
