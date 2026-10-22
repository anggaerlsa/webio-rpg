<?php

namespace Tests\Feature;

use App\Models\Monster;
use App\Models\Quest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_landing_shows_world_stats()
    {
        Quest::create(['slug' => 'q', 'title' => 'Q', 'min_level' => 1]);
        foreach (['a', 'b'] as $slug) {
            Monster::create(['slug' => $slug, 'name' => $slug, 'max_hp' => 10, 'attack' => 1, 'defense' => 0, 'xp_reward' => 1, 'gold_reward' => 1, 'loot' => []]);
        }

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('stats', ['heroes' => 0, 'quests' => 1, 'monsters' => 2, 'cities' => 0]));
    }
}
