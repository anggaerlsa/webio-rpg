<?php

namespace Tests\Feature;

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
}
