<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Character, 2: Item}
     */
    private function playerWithPotion(int $hp = 20, int $heal = 30, int $qty = 1): array
    {
        $user = User::factory()->create();
        $char = $user->characters()->create([
            'name' => 'Hero', 'level' => 1, 'xp' => 0, 'hp' => $hp, 'max_hp' => 50,
            'attack' => 10, 'defense' => 5, 'gold' => 0, 'is_alive' => true,
        ]);
        $potion = Item::create([
            'slug' => 'potion', 'name' => 'Health Potion', 'type' => 'consumable',
            'stats' => ['heal' => $heal], 'value' => 25,
        ]);
        $char->items()->attach($potion->id, ['quantity' => $qty]);

        return [$user, $char, $potion];
    }

    public function test_player_can_use_a_potion_to_heal(): void
    {
        [$user, $char, $potion] = $this->playerWithPotion(hp: 20, heal: 30, qty: 1);

        $this->actingAs($user)
            ->from('/character')
            ->post(route('character.use-item'), ['item_id' => $potion->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $char->refresh();
        $this->assertSame(50, $char->hp);            // 20 + 30, capped at max 50
        $this->assertSame(0, $char->items()->count()); // last potion consumed -> detached
    }

    public function test_potion_heal_is_capped_at_max_hp(): void
    {
        [$user, $char, $potion] = $this->playerWithPotion(hp: 40, heal: 30, qty: 2);

        $this->actingAs($user)->post(route('character.use-item'), ['item_id' => $potion->id])->assertRedirect();

        $char->refresh();
        $this->assertSame(50, $char->hp); // not 70
        $this->assertSame(1, $char->items()->where('item_id', $potion->id)->first()->pivot->quantity);
    }

    public function test_using_a_potion_at_full_hp_is_blocked(): void
    {
        [$user, $char, $potion] = $this->playerWithPotion(hp: 50, heal: 30, qty: 1);

        $this->actingAs($user)
            ->from('/character')
            ->post(route('character.use-item'), ['item_id' => $potion->id])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(50, $char->fresh()->hp);
        $this->assertSame(1, $char->items()->where('item_id', $potion->id)->first()->pivot->quantity); // not consumed
    }

    public function test_a_potion_can_revive_a_downed_character(): void
    {
        [$user, $char, $potion] = $this->playerWithPotion(hp: 0, heal: 30, qty: 1);
        $char->update(['is_alive' => false]);

        $this->actingAs($user)->post(route('character.use-item'), ['item_id' => $potion->id])->assertRedirect();

        $char->refresh();
        $this->assertSame(30, $char->hp);
        $this->assertTrue($char->is_alive);
    }

    public function test_a_non_consumable_cannot_be_used_to_heal(): void
    {
        $user = User::factory()->create();
        $char = $user->characters()->create([
            'name' => 'Hero', 'level' => 1, 'xp' => 0, 'hp' => 20, 'max_hp' => 50,
            'attack' => 10, 'defense' => 5, 'gold' => 0, 'is_alive' => true,
        ]);
        $sword = Item::create(['slug' => 'pedang', 'name' => 'Pedang', 'type' => 'weapon', 'stats' => ['attack' => 5]]);
        $char->items()->attach($sword->id, ['quantity' => 1]);

        $this->actingAs($user)
            ->from('/character')
            ->post(route('character.use-item'), ['item_id' => $sword->id])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(20, $char->fresh()->hp);
    }
}
