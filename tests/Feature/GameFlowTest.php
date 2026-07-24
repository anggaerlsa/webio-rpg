<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StoryEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Load the goblin-cave content from JSON into the test database.
        $this->artisan('game:import');
    }

    /** Jadikan goblin-cave misi aktif karakter (kini misi butuh afiliasi guild + diambil). */
    private function acceptGoblinCave(User $user): void
    {
        $char = $user->fresh()->character;
        $char->update(['affiliation' => 'adventurer', 'rank' => 'F']);
        app(\App\Services\RankService::class)->accept($char, \App\Models\Quest::where('slug', 'goblin-cave')->firstOrFail());
    }

    /** Find the current node's choice by label and POST it. */
    private function advance(User $user, string $label): void
    {
        $state = app(StoryEngine::class)->currentState($user->fresh()->character);
        $choice = collect($state['node']['choices'])->firstWhere('label', $label);
        $this->assertNotNull($choice, "Expected choice '{$label}' at node ".$state['node']['key']);

        $this->actingAs($user->fresh())
            ->post('/quests/goblin-cave/choose', ['choice_id' => $choice['id']])
            ->assertRedirect(route('quests.play', 'goblin-cave'));
    }

    public function test_game_pages_require_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/quests')->assertRedirect('/login');
    }

    public function test_dashboard_loads_for_user_without_a_character(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user->fresh())->get('/dashboard')->assertOk();
    }

    public function test_full_goblin_cave_playthrough_to_victory(): void
    {
        $user = User::factory()->create();
        $story = app(StoryEngine::class);

        $this->actingAs($user->fresh())
            ->post('/character', ['name' => 'Aria', 'gender' => 'female', 'birth_date' => '2000-01-01', 'class' => 'Warrior'])
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->character);

        $this->acceptGoblinCave($user);
        $this->actingAs($user->fresh())->get('/quests/goblin-cave/play')->assertOk();

        $this->advance($user, 'Masuk ke dalam');                                          // intro -> search
        $this->advance($user, 'Masuk lebih dalam');                                       // search -> fork
        $this->advance($user, 'Nyalakan obor dan menyelip lewat celah berkilau');         // fork -> treasure
        $this->advance($user, 'Ambil emasnya dan hadapi lorong');                         // treasure -> goblin_fight

        $char = $user->fresh()->character;
        $this->assertTrue($char->items()->where('slug', 'torch')->exists());
        $this->assertSame(40, $char->gold);

        $state = $story->currentState($char);
        $this->assertSame('combat', $state['node']['type']);

        $start = $this->actingAs($user->fresh())
            ->postJson('/combat/start', ['node_id' => $state['node']['id']])
            ->assertOk()->json();

        $this->assertNotEmpty($start['attacks']);
        $this->assertSame($start['max_monster_hp'], $start['monster_hp']);

        $sessionId = $start['session_id'];
        $attack = $start['attacks'][0]; // Pukul (default)
        $turn = null;
        $guard = 0;

        do {
            $turn = $this->actingAs($user->fresh())->postJson('/combat/act', [
                'session_id' => $sessionId,
                'attack_kind' => $attack['kind'],
                'attack_id' => $attack['id'],
            ])->assertOk()->json();

            $this->assertSame($attack['name'], $turn['used']['name']);

            $guard++;
        } while (($turn['status'] ?? '') === 'active' && $guard < 30);

        $this->assertSame('won', $turn['status']);
        $this->assertSame(60, $turn['rewards']['xp']);
        $this->assertSame('victory', $turn['next_node']['node_key']);

        $char = $user->fresh()->character;
        $this->assertSame('victory', $char->saves()->first()->current_node_key);
        $this->actingAs($user->fresh())->get('/quests/goblin-cave/play')->assertOk();
    }

    public function test_forged_combat_attack_is_rejected(): void
    {
        $user = User::factory()->create();
        $story = app(StoryEngine::class);

        $this->actingAs($user->fresh())->post('/character', ['name' => 'Aria', 'gender' => 'male', 'birth_date' => '2000-01-01', 'class' => 'Warrior']);
        $this->acceptGoblinCave($user);
        $this->actingAs($user->fresh())->get('/quests/goblin-cave/play');

        $this->advance($user, 'Masuk ke dalam');
        $this->advance($user, 'Masuk lebih dalam');
        $this->advance($user, 'Terjang lurus ke lorong utama');

        $state = $story->currentState($user->fresh()->character);
        $start = $this->actingAs($user->fresh())
            ->postJson('/combat/start', ['node_id' => $state['node']['id']])
            ->assertOk()->json();

        $this->actingAs($user->fresh())->postJson('/combat/act', [
            'session_id' => $start['session_id'],
            'attack_kind' => 'skill',
            'attack_id' => 999999, // skill the character does not know
        ])->assertStatus(422);
    }
}
