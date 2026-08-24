<?php

namespace Tests\Feature;

use App\Events\FriendshipChanged;
use App\Events\PrivateMessageSent;
use App\Events\WorldMessageSent;
use App\Models\ChatMessage;
use App\Models\Friendship;
use App\Models\User;
use App\Services\ChatService;
use App\Services\FriendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FriendChatTest extends TestCase
{
    use RefreshDatabase;

    private function player(string $charName): User
    {
        $user = User::factory()->create();
        $user->characters()->create([
            'name' => $charName, 'level' => 1, 'xp' => 0, 'hp' => 50, 'max_hp' => 50,
            'attack' => 10, 'defense' => 5, 'gold' => 0, 'is_alive' => true,
        ]);

        return $user->fresh();
    }

    private function friends(): FriendService
    {
        return app(FriendService::class);
    }

    public function test_friend_request_then_accept_makes_them_friends(): void
    {
        $a = $this->player('Aria');
        $b = $this->player('Borin');

        $f = $this->friends()->sendRequest($a, $b);
        $this->assertSame('outgoing', $this->friends()->status($a, $b)['status']);
        $this->assertSame('incoming', $this->friends()->status($b, $a)['status']);

        $this->friends()->accept($b, $f->fresh());
        $this->assertSame('friends', $this->friends()->status($a, $b)['status']);
        $this->assertCount(1, $this->friends()->friends($a));
    }

    public function test_cannot_befriend_self_or_duplicate(): void
    {
        $a = $this->player('Aria');
        $b = $this->player('Borin');

        $this->friends()->sendRequest($a, $b);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->friends()->sendRequest($a, $b); // duplikat
    }

    public function test_only_the_addressee_can_accept(): void
    {
        $a = $this->player('Aria');
        $b = $this->player('Borin');
        $f = $this->friends()->sendRequest($a, $b);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->friends()->accept($a, $f); // pengirim tak boleh menerima sendiri
    }

    public function test_cannot_dm_before_friends_but_can_after(): void
    {
        $a = $this->player('Aria');
        $b = $this->player('Borin');
        $f = $this->friends()->sendRequest($a, $b);

        // belum diterima → 403
        $this->actingAs($a)->postJson(route('chat.dm.post', $f->id), ['body' => 'hai'])->assertStatus(403);

        $this->friends()->accept($b, $f->fresh());
        $this->actingAs($a)->postJson(route('chat.dm.post', $f->id), ['body' => 'hai'])->assertOk();
        $this->actingAs($b)->getJson(route('chat.dm', $f->id))->assertOk()
            ->assertJsonPath('messages.0.body', 'hai');
    }

    public function test_a_stranger_cannot_read_a_dm(): void
    {
        $a = $this->player('Aria');
        $b = $this->player('Borin');
        $c = $this->player('Cael');
        $f = Friendship::create(['requester_id' => $a->id, 'addressee_id' => $b->id, 'status' => 'accepted']);

        $this->actingAs($c)->getJson(route('chat.dm', $f->id))->assertStatus(403);
    }

    public function test_world_message_posts_fetches_and_broadcasts(): void
    {
        Event::fake([WorldMessageSent::class]);
        $a = $this->player('Aria');

        $this->actingAs($a)->postJson(route('chat.world.post'), ['body' => 'Halo dunia'])
            ->assertOk()->assertJsonPath('message.name', 'Aria');

        $this->actingAs($a)->getJson(route('chat.world'))->assertOk()
            ->assertJsonPath('messages.0.body', 'Halo dunia');

        Event::assertDispatched(WorldMessageSent::class);
    }

    public function test_dm_broadcasts_a_private_message(): void
    {
        Event::fake([PrivateMessageSent::class]);
        $a = $this->player('Aria');
        $b = $this->player('Borin');
        $f = Friendship::create(['requester_id' => $a->id, 'addressee_id' => $b->id, 'status' => 'accepted']);

        app(ChatService::class)->postDm($a, $f, 'rahasia');

        Event::assertDispatched(PrivateMessageSent::class);
    }

    public function test_messages_older_than_30_minutes_are_pruned(): void
    {
        $a = $this->player('Aria');
        $old = ChatMessage::create(['user_id' => $a->id, 'scope' => 'world', 'body' => 'lama']);
        $old->forceFill(['created_at' => now()->subMinutes(31)])->save();
        ChatMessage::create(['user_id' => $a->id, 'scope' => 'world', 'body' => 'baru']);

        $messages = app(ChatService::class)->worldMessages();

        $this->assertCount(1, $messages);
        $this->assertSame('baru', $messages[0]['body']);
        $this->assertDatabaseMissing('chat_messages', ['id' => $old->id]); // benar-benar dihapus
    }

    public function test_search_finds_players_by_character_name_excluding_self(): void
    {
        $a = $this->player('Aria the Bold');
        $this->player('Borin');

        $results = $this->friends()->search($a, 'Borin');
        $this->assertCount(1, $results);
        $this->assertSame('Borin', $results[0]['name']);
        $this->assertSame('none', $results[0]['status']);

        // tidak menemukan diri sendiri
        $this->assertCount(0, $this->friends()->search($a, 'Aria'));
    }

    public function test_chat_requires_a_character(): void
    {
        $user = User::factory()->create(); // tanpa karakter

        $this->actingAs($user)->getJson(route('chat.world'))->assertStatus(403);
        $this->actingAs($user)->getJson(route('chat.me'))->assertOk()->assertJsonPath('can_chat', false);
    }

    public function test_removing_a_friendship_deletes_its_dms(): void
    {
        $a = $this->player('Aria');
        $b = $this->player('Borin');
        $f = Friendship::create(['requester_id' => $a->id, 'addressee_id' => $b->id, 'status' => 'accepted']);
        app(ChatService::class)->postDm($a, $f, 'hai');

        $this->friends()->remove($a, $f);

        $this->assertDatabaseMissing('friendships', ['id' => $f->id]);
        $this->assertDatabaseMissing('chat_messages', ['friendship_id' => $f->id]);
    }

    // ── Reverb mati tidak boleh menggagalkan aksi ───────────────────────────

    /** Paksa dispatch event gagal, seperti saat server Reverb tidak berjalan. */
    private function breakBroadcasting(string $event): void
    {
        Event::listen($event, function () {
            throw new \RuntimeException('Reverb mati');
        });
    }

    public function test_world_message_is_saved_even_when_broadcasting_fails(): void
    {
        $a = $this->player('Aria');
        $this->breakBroadcasting(WorldMessageSent::class);

        $message = app(ChatService::class)->postWorld($a, 'Halo dunia');

        $this->assertDatabaseHas('chat_messages', ['id' => $message->id, 'body' => 'Halo dunia']);
    }

    public function test_posting_to_world_chat_returns_ok_when_broadcasting_fails(): void
    {
        $a = $this->player('Aria');
        $this->breakBroadcasting(WorldMessageSent::class);

        $this->actingAs($a)->postJson(route('chat.world.post'), ['body' => 'Halo dunia'])
            ->assertOk()->assertJsonPath('message.body', 'Halo dunia');
    }

    public function test_dm_is_saved_even_when_broadcasting_fails(): void
    {
        $a = $this->player('Aria');
        $b = $this->player('Borin');
        $f = Friendship::create(['requester_id' => $a->id, 'addressee_id' => $b->id, 'status' => 'accepted']);
        $this->breakBroadcasting(PrivateMessageSent::class);

        $message = app(ChatService::class)->postDm($a, $f, 'rahasia');

        $this->assertDatabaseHas('chat_messages', ['id' => $message->id, 'body' => 'rahasia']);
    }

    public function test_friend_request_still_lands_when_broadcasting_fails(): void
    {
        $a = $this->player('Aria');
        $b = $this->player('Borin');
        $this->breakBroadcasting(FriendshipChanged::class);

        $f = $this->friends()->sendRequest($a, $b);

        $this->assertDatabaseHas('friendships', ['id' => $f->id, 'status' => 'pending']);
        $this->assertSame('outgoing', $this->friends()->status($a, $b)['status']);
    }

    public function test_accepting_a_friend_request_survives_a_dead_broadcaster(): void
    {
        $a = $this->player('Aria');
        $b = $this->player('Borin');
        $f = $this->friends()->sendRequest($a, $b);
        $this->breakBroadcasting(FriendshipChanged::class);

        $this->friends()->accept($b, $f->fresh());

        $this->assertSame('friends', $this->friends()->status($a, $b)['status']);
    }
}
