<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\City;
use App\Models\CombatSession;
use App\Models\Country;
use App\Models\Monster;
use App\Models\Quest;
use App\Models\QuestNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MapTest extends TestCase
{
    use RefreshDatabase;

    private function makeCountry(string $slug): Country
    {
        $country = Country::create(['slug' => $slug, 'name' => ucfirst($slug)]);
        $country->provinces()->create(['slug' => $slug.'-wil', 'name' => 'Wilayah '.$slug]);

        return $country;
    }

    /** @param  array<string, mixed>  $attrs */
    private function makeCity(Country $country, string $slug, array $attrs = []): City
    {
        return $country->provinces()->first()->cities()->create(array_merge(['slug' => $slug, 'name' => ucfirst($slug)], $attrs));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Character}
     */
    private function playerIn(City $city, array $overrides = []): array
    {
        $user = User::factory()->create();
        $char = $user->characters()->create(array_merge([
            'name' => 'Hero', 'level' => 3, 'xp' => 0,
            'hp' => 50, 'max_hp' => 50, 'sp' => 10, 'max_sp' => 30, 'mp' => 5, 'max_mp' => 30,
            'attack' => 10, 'defense' => 5, 'gold' => 100, 'city_id' => $city->id, 'is_alive' => true,
        ], $overrides));

        return [$user, $char];
    }

    public function test_travelling_within_the_same_kingdom_is_free(): void
    {
        $astoria = $this->makeCountry('astoria');
        $solgrave = $this->makeCity($astoria, 'solgrave');
        $benteng = $this->makeCity($astoria, 'benteng-utara', ['type' => 'fortress']);
        [$user, $char] = $this->playerIn($solgrave);

        $this->actingAs($user)->post(route('map.travel', $benteng))
            ->assertRedirect(route('town.show'))->assertSessionHas('success');

        $char->refresh();
        $this->assertSame($benteng->id, $char->city_id);
        $this->assertSame(100, $char->gold);
    }

    public function test_travelling_to_another_kingdom_costs_ten_gold_per_level(): void
    {
        $solgrave = $this->makeCity($this->makeCountry('astoria'), 'solgrave');
        $silvaris = $this->makeCity($this->makeCountry('alfheim'), 'silvaris');
        [$user, $char] = $this->playerIn($solgrave, ['level' => 3, 'gold' => 100]);

        $this->actingAs($user)->post(route('map.travel', $silvaris))
            ->assertRedirect(route('town.show'))->assertSessionHas('success');

        $char->refresh();
        $this->assertSame($silvaris->id, $char->city_id);
        $this->assertSame(70, $char->gold);
    }

    public function test_travel_to_another_kingdom_is_refused_without_enough_gold(): void
    {
        $solgrave = $this->makeCity($this->makeCountry('astoria'), 'solgrave');
        $silvaris = $this->makeCity($this->makeCountry('alfheim'), 'silvaris');
        [$user, $char] = $this->playerIn($solgrave, ['level' => 3, 'gold' => 29]);

        $this->actingAs($user)->post(route('map.travel', $silvaris))
            ->assertRedirect()->assertSessionHas('error');

        $char->refresh();
        $this->assertSame($solgrave->id, $char->city_id);
        $this->assertSame(29, $char->gold);
    }

    public function test_travel_is_refused_during_an_active_fight(): void
    {
        $astoria = $this->makeCountry('astoria');
        $solgrave = $this->makeCity($astoria, 'solgrave');
        $benteng = $this->makeCity($astoria, 'benteng-utara');
        [$user, $char] = $this->playerIn($solgrave);

        $quest = Quest::create(['slug' => 'q', 'title' => 'Q', 'min_level' => 1]);
        $monster = Monster::create(['slug' => 'goblin', 'name' => 'Goblin', 'max_hp' => 20, 'attack' => 1, 'defense' => 0, 'xp_reward' => 1, 'gold_reward' => 1, 'loot' => []]);
        $node = QuestNode::create(['quest_id' => $quest->id, 'key' => 'fight', 'type' => 'combat', 'monster_id' => $monster->id]);
        CombatSession::create([
            'character_id' => $char->id, 'quest_node_id' => $node->id, 'monster_id' => $monster->id,
            'monster_hp' => 20, 'player_hp' => 50, 'status' => 'active',
        ]);

        $this->actingAs($user)->post(route('map.travel', $benteng))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame($solgrave->id, $char->fresh()->city_id);
    }

    public function test_world_map_lists_kingdoms_and_marks_where_the_hero_is(): void
    {
        $astoria = $this->makeCountry('astoria');
        $astoria->update(['map_x' => 50, 'map_y' => 39]);
        $alfheim = $this->makeCountry('alfheim');
        [$user] = $this->playerIn($this->makeCity($astoria, 'solgrave'));

        $this->actingAs($user)->get(route('map.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Map/World')
                ->has('kingdoms', 2)
                ->where('kingdoms.0', ['slug' => 'alfheim', 'name' => 'Alfheim', 'map_x' => null, 'map_y' => null, 'is_here' => false])
                ->where('kingdoms.1', ['slug' => 'astoria', 'name' => 'Astoria', 'map_x' => 50, 'map_y' => 39, 'is_here' => true]));
    }

    public function test_kingdom_map_lists_settlements_with_free_travel_inside_it(): void
    {
        $astoria = $this->makeCountry('astoria');
        $astoria->update(['map_image' => '/images/maps/astoria.webp']);
        $solgrave = $this->makeCity($astoria, 'solgrave', ['image' => '/images/cities/solgrave.webp', 'map_x' => 52, 'map_y' => 36]);
        $this->makeCity($astoria, 'benteng-utara', ['type' => 'fortress']);
        [$user] = $this->playerIn($solgrave);

        $this->actingAs($user)->get(route('map.kingdom', $astoria))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Map/Kingdom')
                ->where('kingdom.slug', 'astoria')
                ->where('kingdom.map_image', '/images/maps/astoria.webp')
                ->has('settlements', 2)
                ->where('settlements.0.slug', 'benteng-utara')
                ->where('settlements.0.type_label', 'Benteng')
                ->where('settlements.0.is_here', false)
                ->where('settlements.0.travel_cost', 0)
                ->where('settlements.0.can_travel', true)
                ->where('settlements.1.slug', 'solgrave')
                ->where('settlements.1.image', '/images/cities/solgrave.webp')
                ->where('settlements.1.map_x', 52)
                ->where('settlements.1.is_here', true));
    }

    public function test_kingdom_map_shows_the_cost_and_blocks_travel_without_enough_gold(): void
    {
        [$user] = $this->playerIn($this->makeCity($this->makeCountry('astoria'), 'solgrave'), ['level' => 3, 'gold' => 20]);
        $alfheim = $this->makeCountry('alfheim');
        $this->makeCity($alfheim, 'silvaris');

        $this->actingAs($user)->get(route('map.kingdom', $alfheim))
            ->assertInertia(fn (Assert $page) => $page
                ->where('settlements.0.slug', 'silvaris')
                ->where('settlements.0.travel_cost', 30)
                ->where('settlements.0.can_travel', false));
    }
}
