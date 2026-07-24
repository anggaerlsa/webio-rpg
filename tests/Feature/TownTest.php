<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\City;
use App\Models\Country;
use App\Models\Item;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TownTest extends TestCase
{
    use RefreshDatabase;

    private function makeCity(string $slug = 'eldoria'): City
    {
        $country = Country::create(['slug' => $slug.'-land', 'name' => 'Tanah '.$slug]);
        $province = $country->provinces()->create(['slug' => $slug.'-prov', 'name' => 'Provinsi']);

        return $province->cities()->create(['slug' => $slug, 'name' => 'Kota '.ucfirst($slug)]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Character}
     */
    private function playerIn(City $city, array $overrides = []): array
    {
        $user = User::factory()->create();
        $char = $user->characters()->create(array_merge([
            'name' => 'Hero', 'level' => 1, 'xp' => 0,
            'hp' => 20, 'max_hp' => 50, 'sp' => 10, 'max_sp' => 30, 'mp' => 5, 'max_mp' => 30,
            'attack' => 10, 'defense' => 5, 'gold' => 100, 'city_id' => $city->id, 'is_alive' => true,
        ], $overrides));

        return [$user, $char];
    }

    public function test_town_page_loads_and_lazily_assigns_starting_city(): void
    {
        $city = $this->makeCity();
        $user = User::factory()->create();
        $char = $user->characters()->create([
            'name' => 'Wanderer', 'level' => 1, 'xp' => 0, 'hp' => 30, 'max_hp' => 50,
            'attack' => 10, 'defense' => 5, 'gold' => 0, 'is_alive' => true, // city_id null
        ]);

        $this->actingAs($user)->get(route('town.show'))->assertOk();

        $this->assertSame($city->id, $char->fresh()->city_id);
    }

    public function test_resting_at_the_inn_restores_pools_and_charges_gold(): void
    {
        $city = $this->makeCity();
        [$user, $char] = $this->playerIn($city, ['gold' => 100, 'level' => 2]);
        $inn = $city->places()->create(['category' => 'inn', 'slug' => 'inn', 'name' => 'Rabbit Moon Inn']);

        $this->actingAs($user)->post(route('town.rest', $inn->slug))
            ->assertRedirect()->assertSessionHas('success');

        $char->refresh();
        $this->assertSame(50, $char->hp);
        $this->assertSame(30, $char->sp);
        $this->assertSame(30, $char->mp);
        $this->assertSame(80, $char->gold); // 100 - (10 * level 2)
    }

    public function test_resting_is_blocked_without_enough_gold(): void
    {
        $city = $this->makeCity();
        [$user, $char] = $this->playerIn($city, ['gold' => 5, 'level' => 1]); // butuh 10
        $inn = $city->places()->create(['category' => 'inn', 'slug' => 'inn', 'name' => 'Inn']);

        $this->actingAs($user)->post(route('town.rest', $inn->slug))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(20, $char->fresh()->hp); // tidak berubah
        $this->assertSame(5, $char->fresh()->gold);
    }

    public function test_resting_is_blocked_when_already_full(): void
    {
        $city = $this->makeCity();
        [$user, $char] = $this->playerIn($city, ['hp' => 50, 'sp' => 30, 'mp' => 30, 'gold' => 100]);
        $inn = $city->places()->create(['category' => 'inn', 'slug' => 'inn', 'name' => 'Inn']);

        $this->actingAs($user)->post(route('town.rest', $inn->slug))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(100, $char->fresh()->gold);
    }

    public function test_buying_a_potion_adds_it_and_deducts_gold(): void
    {
        $city = $this->makeCity();
        [$user, $char] = $this->playerIn($city, ['gold' => 100]);
        $shop = $city->places()->create(['category' => 'potion_shop', 'slug' => 'shop', 'name' => 'Toko Ramuan']);
        $potion = Item::create(['slug' => 'potion', 'name' => 'Health Potion', 'type' => 'consumable', 'stats' => ['heal' => 30], 'value' => 25]);

        $this->actingAs($user)->post(route('town.buy', $shop->slug), ['item_id' => $potion->id, 'qty' => 2])
            ->assertRedirect()->assertSessionHas('success');

        $char->refresh();
        $this->assertSame(50, $char->gold); // 100 - 2*25
        $this->assertSame(2, $char->items()->where('item_id', $potion->id)->first()->pivot->quantity);
    }

    public function test_blacksmith_does_not_sell_consumables(): void
    {
        $city = $this->makeCity();
        [$user, $char] = $this->playerIn($city, ['gold' => 100]);
        $smith = $city->places()->create(['category' => 'blacksmith', 'slug' => 'smith', 'name' => 'Pandai Besi']);
        $potion = Item::create(['slug' => 'potion', 'name' => 'Potion', 'type' => 'consumable', 'stats' => ['heal' => 30], 'value' => 25]);

        $this->actingAs($user)->post(route('town.buy', $smith->slug), ['item_id' => $potion->id])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(100, $char->fresh()->gold);
        $this->assertSame(0, $char->items()->count());
    }

    public function test_selling_an_item_removes_it_and_adds_gold(): void
    {
        $city = $this->makeCity();
        [$user, $char] = $this->playerIn($city, ['gold' => 0]);
        $shop = $city->places()->create(['category' => 'potion_shop', 'slug' => 'shop', 'name' => 'Toko']);
        $potion = Item::create(['slug' => 'potion', 'name' => 'Potion', 'type' => 'consumable', 'stats' => ['heal' => 30], 'value' => 24]);
        $char->items()->attach($potion->id, ['quantity' => 3]);

        $this->actingAs($user)->post(route('town.sell', $shop->slug), ['item_id' => $potion->id, 'qty' => 2])
            ->assertRedirect()->assertSessionHas('success');

        $char->refresh();
        $this->assertSame(24, $char->gold); // 2 * floor(24/2)
        $this->assertSame(1, $char->items()->where('item_id', $potion->id)->first()->pivot->quantity);
    }

    public function test_cannot_interact_with_a_place_outside_your_city(): void
    {
        $home = $this->makeCity('home');
        $other = $this->makeCity('faraway');
        [$user] = $this->playerIn($home);
        $foreignInn = $other->places()->create(['category' => 'inn', 'slug' => 'far-inn', 'name' => 'Far Inn']);

        $this->actingAs($user)->get(route('town.place', $foreignInn->slug))->assertForbidden();
        $this->actingAs($user)->post(route('town.rest', $foreignInn->slug))->assertForbidden();
    }

    public function test_sp_potion_restores_stamina_out_of_combat(): void
    {
        $city = $this->makeCity();
        [$user, $char] = $this->playerIn($city, ['sp' => 5, 'max_sp' => 30]);
        $spPotion = Item::create(['slug' => 'potion-sp', 'name' => 'Ramuan Stamina', 'type' => 'consumable', 'stats' => ['restore_sp' => 30], 'value' => 20]);
        $char->items()->attach($spPotion->id, ['quantity' => 1]);

        $this->actingAs($user)->post(route('character.use-item'), ['item_id' => $spPotion->id])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(30, $char->fresh()->sp); // 5 + 30, capped at 30
        $this->assertSame(0, $char->items()->count());
    }
}
