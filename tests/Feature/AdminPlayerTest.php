<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPlayerTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        $user = User::factory()->create();
        $user->role = 'superadmin';
        $user->job = 'Dewa Pencipta';
        $user->save();

        return $user;
    }

    /**
     * @return array{0: User, 1: Character}
     */
    private function playerWithProgress(): array
    {
        $user = User::factory()->create(['name' => 'Budi', 'email' => 'budi@test.com']);
        $char = $user->characters()->create([
            'name' => 'Pahlawan', 'level' => 3, 'xp' => 50, 'hp' => 40, 'max_hp' => 60,
            'attack' => 10, 'defense' => 5, 'gold' => 100, 'is_alive' => true,
        ]);
        $char->saves()->create(['slot' => 1, 'current_node_key' => 'start', 'state' => ['flags' => []]]);
        $item = Item::create(['slug' => 'potion', 'name' => 'Potion', 'type' => 'consumable']);
        $char->items()->attach($item->id, ['quantity' => 2]);

        return [$user, $char];
    }

    /**
     * @return array<string, mixed>
     */
    private function charPayload(Character $c): array
    {
        return [
            'level' => $c->level, 'xp' => $c->xp, 'gold' => $c->gold,
            'hp' => $c->hp, 'max_hp' => $c->max_hp, 'sp' => $c->sp, 'max_sp' => $c->max_sp,
            'mp' => $c->mp, 'max_mp' => $c->max_mp, 'attack' => $c->attack, 'defense' => $c->defense,
            'magic_attack' => $c->magic_attack, 'magic_defense' => $c->magic_defense,
            'strength' => $c->strength, 'agility' => $c->agility, 'dexterity' => $c->dexterity,
            'intelligence' => $c->intelligence, 'vitality' => $c->vitality, 'luck' => $c->luck,
            'rank' => $c->rank,
        ];
    }

    public function test_regular_player_cannot_access_players_admin(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/players')->assertForbidden();
    }

    public function test_superadmin_can_view_players_index(): void
    {
        $this->actingAs($this->superadmin())->get(route('admin.players.index'))->assertOk();
    }

    public function test_superadmin_can_update_a_player_account_and_character(): void
    {
        $admin = $this->superadmin();
        [$user, $char] = $this->playerWithProgress();

        $this->actingAs($admin)->put(route('admin.players.update', $user->id), [
            'name' => 'Budi Updated', 'email' => 'budi2@test.com', 'role' => 'player', 'job' => 'merchant',
            'character' => array_merge($this->charPayload($char), ['level' => 9, 'gold' => 999, 'strength' => 42]),
        ])->assertRedirect(route('admin.players.index'))->assertSessionHasNoErrors();

        $user->refresh();
        $char->refresh();
        $this->assertSame('Budi Updated', $user->name);
        $this->assertSame('budi2@test.com', $user->email);
        $this->assertSame('merchant', $user->job);
        $this->assertSame(9, $char->level);
        $this->assertSame(999, $char->gold);
        $this->assertSame(42, $char->strength);
    }

    public function test_superadmin_can_change_a_players_role(): void
    {
        $admin = $this->superadmin();
        $user = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.players.update', $user->id), [
            'name' => $user->name, 'email' => $user->email, 'role' => 'superadmin',
        ])->assertSessionHasNoErrors();

        $this->assertSame('superadmin', $user->fresh()->role);
    }

    public function test_superadmin_cannot_change_their_own_role(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->put(route('admin.players.update', $admin->id), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'player', // mencoba menurunkan diri
        ])->assertSessionHasNoErrors();

        $this->assertSame('superadmin', $admin->fresh()->role); // tetap Dewa
    }

    public function test_deleting_a_player_cascades_character_and_all_progress(): void
    {
        $admin = $this->superadmin();
        [$user, $char] = $this->playerWithProgress();

        $this->actingAs($admin)->delete(route('admin.players.destroy', $user->id))
            ->assertRedirect(route('admin.players.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('characters', ['id' => $char->id]);
        $this->assertDatabaseMissing('game_saves', ['character_id' => $char->id]);
        $this->assertDatabaseMissing('character_items', ['character_id' => $char->id]);
    }

    public function test_superadmin_cannot_delete_their_own_account(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->from(route('admin.players.index'))
            ->delete(route('admin.players.destroy', $admin->id))
            ->assertRedirect(route('admin.players.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
