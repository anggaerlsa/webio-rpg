<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Item;
use App\Models\Monster;
use App\Models\Quest;
use App\Models\QuestNode;
use App\Models\Skill;
use App\Models\Spell;
use App\Models\User;
use App\Services\CombatService;
use App\Services\StoryEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CombatServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{char: Character, node: QuestNode, skill: Skill}
     */
    private function scenario(array $charOverrides = [], array $monsterOverrides = [], int $skillPower = 1): array
    {
        $user = User::factory()->create();
        $char = $user->characters()->create(array_merge([
            'name' => 'Hero', 'level' => 1, 'xp' => 0, 'hp' => 50, 'max_hp' => 50,
            'attack' => 10, 'defense' => 5, 'gold' => 0, 'is_alive' => true,
            // Atribut RPG netral (1 = baseline, efek 0) agar formula dasar deterministik; tes efek stat meng-override.
            'strength' => 1, 'agility' => 1, 'dexterity' => 1, 'intelligence' => 1, 'vitality' => 1, 'luck' => 1,
        ], $charOverrides));

        $skill = Skill::create(['slug' => 'pukul', 'name' => 'Pukul', 'type' => 'physical', 'power' => $skillPower, 'is_default' => true]);
        $char->skills()->attach($skill->id);

        $quest = Quest::create(['slug' => 'test-quest', 'title' => 'Test', 'min_level' => 1, 'order' => 1]);
        $monster = Monster::create(array_merge([
            'slug' => 'dummy', 'name' => 'Dummy', 'max_hp' => 20, 'attack' => 8, 'defense' => 0,
            'xp_reward' => 30, 'gold_reward' => 10, 'loot' => [],
        ], $monsterOverrides));
        $node = QuestNode::create([
            'quest_id' => $quest->id, 'key' => 'fight', 'type' => 'combat',
            'monster_id' => $monster->id, 'payload' => ['on_win_node_key' => 'win', 'on_lose_node_key' => 'lose'],
        ]);
        QuestNode::create(['quest_id' => $quest->id, 'key' => 'win', 'type' => 'ending', 'payload' => ['result' => 'victory']]);
        QuestNode::create(['quest_id' => $quest->id, 'key' => 'lose', 'type' => 'ending', 'payload' => ['result' => 'defeat']]);
        $quest->update(['start_node_id' => $node->id]);

        $char->saves()->create([
            'slot' => 1, 'quest_id' => $quest->id, 'current_node_key' => 'fight',
            'state' => ['flags' => [], 'visited' => ['fight'], 'rewards_applied' => []],
        ]);

        return ['char' => $char, 'node' => $node->load('monster'), 'skill' => $skill];
    }

    public function test_start_lists_the_default_attack(): void
    {
        $s = $this->scenario();
        $combat = app(CombatService::class);

        $view = $combat->start($s['char'], $s['node']);

        $this->assertNotEmpty($view['attacks']);
        $this->assertSame('Pukul', $view['attacks'][0]['name']);
        $this->assertSame(20, $view['monster_hp']);
    }

    public function test_attacking_damages_the_monster_and_draws_a_counter(): void
    {
        $s = $this->scenario();
        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();

        $res = $combat->act($session, 'skill', $s['skill']->id);

        $this->assertSame('Pukul', $res['used']['name']);
        $this->assertSame(19, $res['monster_hp']);     // 20 - max(1, 1 - 0)
        $this->assertNotNull($res['counter']);          // monster survived and hit back
        $this->assertLessThan(50, $res['player_hp']);
        $this->assertSame('active', $res['status']);
    }

    public function test_win_grants_rewards_and_advances_the_save(): void
    {
        $s = $this->scenario(monsterOverrides: ['max_hp' => 1]);
        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();

        $res = $combat->act($session, 'skill', $s['skill']->id);

        $this->assertSame('won', $res['status']);
        $this->assertNull($res['counter']);             // monster died before hitting back
        $this->assertSame(30, $res['rewards']['xp']);
        $this->assertSame('win', $res['next_node']['node_key']);

        $char = $s['char']->fresh();
        $this->assertSame(10, $char->gold);
        $this->assertSame('win', $char->saves()->first()->current_node_key);
    }

    public function test_loss_zeroes_hp_and_routes_to_lose_node(): void
    {
        $s = $this->scenario(charOverrides: ['hp' => 1, 'defense' => 0], monsterOverrides: ['max_hp' => 99, 'attack' => 99]);
        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();

        $res = $combat->act($session, 'skill', $s['skill']->id);

        $this->assertSame('lost', $res['status']);
        $char = $s['char']->fresh();
        $this->assertSame(0, $char->hp);
        $this->assertFalse($char->is_alive);
        $this->assertSame('lose', $char->saves()->first()->current_node_key);
    }

    public function test_player_hp_persists_in_db_after_surviving_a_turn(): void
    {
        $s = $this->scenario(charOverrides: ['hp' => 50, 'defense' => 0], monsterOverrides: ['attack' => 8, 'max_hp' => 99]);
        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();

        $res = $combat->act($session, 'skill', $s['skill']->id);

        $this->assertSame(42, $res['player_hp']);          // 50 - max(1, 8 - 0) = 42
        $this->assertSame(42, $s['char']->fresh()->hp);    // last HP saved server-side

        // A new fight resumes from the reduced HP, not full.
        $resume = $combat->start($s['char']->fresh(), $s['node']);
        $this->assertSame(42, $resume['player_hp']);
    }

    public function test_physical_attack_consumes_sp(): void
    {
        $s = $this->scenario(charOverrides: ['sp' => 10]);
        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();

        $res = $combat->act($session, 'skill', $s['skill']->id);

        $this->assertSame('sp', $res['used']['resource']);
        $this->assertSame(1, $res['used']['cost']);          // Pukul power 1 -> cost 1 (auto from power)
        $this->assertFalse($res['used']['whiffed']);
        $this->assertGreaterThan(0, $res['used']['damage']);
        $this->assertSame(9, $res['player_sp']);             // 10 - 1
        $this->assertSame(9, $s['char']->fresh()->sp);       // persisted
    }

    public function test_attack_with_empty_sp_deals_zero_damage(): void
    {
        $s = $this->scenario(charOverrides: ['sp' => 0]);
        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();
        $monsterBefore = $session->monster_hp;

        $res = $combat->act($session, 'skill', $s['skill']->id);

        $this->assertTrue($res['used']['whiffed']);
        $this->assertSame(0, $res['used']['damage']);
        $this->assertSame($monsterBefore, $res['monster_hp']); // monster takes no damage
        $this->assertSame(0, $res['player_sp']);               // couldn't pay, stays 0
        $this->assertNotNull($res['counter']);                 // but the monster still hits back
        $this->assertSame('active', $res['status']);
    }

    public function test_spell_consumes_mp(): void
    {
        $s = $this->scenario(charOverrides: ['mp' => 30]);
        $spell = Spell::create(['slug' => 'bola-api', 'name' => 'Bola Api', 'element' => 'api', 'power' => 5, 'mana_cost' => 4, 'min_level' => 1]);
        $s['char']->spells()->attach($spell->id);

        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();

        $res = $combat->act($session, 'spell', $spell->id);

        $this->assertSame('mp', $res['used']['resource']);
        $this->assertSame(4, $res['used']['cost']);          // explicit mana_cost
        $this->assertSame(5, $res['used']['damage']);        // max(1, 5 - 0)
        $this->assertSame(26, $res['player_mp']);            // 30 - 4
    }

    public function test_starting_a_quest_keeps_a_living_characters_resources(): void
    {
        // Karakter hidup tapi terluka: HP/SP/MP dibawa apa adanya, tidak di-reset penuh.
        $s = $this->scenario(charOverrides: ['hp' => 5, 'sp' => 2, 'mp' => 3, 'max_sp' => 30, 'max_mp' => 40]);
        $quest = Quest::where('slug', 'test-quest')->firstOrFail();

        app(StoryEngine::class)->startQuest($s['char'], $quest);

        $char = $s['char']->fresh();
        $this->assertSame(5, $char->hp);  // tetap 5, bukan penuh
        $this->assertSame(2, $char->sp);
        $this->assertSame(3, $char->mp);
    }

    public function test_starting_a_quest_revives_a_downed_character(): void
    {
        // Karakter tumbang dibangkitkan penuh (respawn) agar bisa mencoba lagi.
        $s = $this->scenario(charOverrides: ['hp' => 0, 'is_alive' => false, 'sp' => 0, 'mp' => 0, 'max_sp' => 30, 'max_mp' => 40]);
        $quest = Quest::where('slug', 'test-quest')->firstOrFail();

        app(StoryEngine::class)->startQuest($s['char'], $quest);

        $char = $s['char']->fresh();
        $this->assertSame(50, $char->hp); // max_hp default 50
        $this->assertSame(30, $char->sp);
        $this->assertSame(40, $char->mp);
        $this->assertTrue($char->is_alive);
    }

    public function test_using_a_potion_in_combat_heals_and_costs_a_turn(): void
    {
        $s = $this->scenario(charOverrides: ['hp' => 20], monsterOverrides: ['attack' => 8, 'defense' => 0, 'max_hp' => 99]);
        $char = $s['char'];
        $potion = Item::create(['slug' => 'potion', 'name' => 'Health Potion', 'type' => 'consumable', 'stats' => ['heal' => 30], 'value' => 25]);
        $char->items()->attach($potion->id, ['quantity' => 2]);

        $combat = app(CombatService::class);
        $combat->start($char, $s['node']);
        $session = $char->activeCombat()->first();

        $res = $combat->useItem($session, $potion->id);

        $this->assertSame('item', $res['used']['kind']);
        $this->assertSame(30, $res['used']['heal']);       // 20 -> 50 (capped)
        $this->assertSame(44, $res['player_hp']);          // healed 50, then counter max(1, 8 - 2) = 6 -> 44
        $this->assertSame(99, $res['monster_hp']);         // drinking doesn't hurt the monster
        $this->assertSame('active', $res['status']);

        $char->refresh();
        $this->assertSame(44, $char->hp);                                  // persisted
        $this->assertSame(1, $char->items()->where('item_id', $potion->id)->first()->pivot->quantity); // one consumed
        $this->assertSame(1, $res['potions'][0]['quantity']);              // reflected in response
    }

    public function test_using_a_potion_you_do_not_have_is_rejected(): void
    {
        $s = $this->scenario(charOverrides: ['hp' => 10]);
        $potion = Item::create(['slug' => 'potion', 'name' => 'Health Potion', 'type' => 'consumable', 'stats' => ['heal' => 30]]);
        // intentionally not attached to the character
        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();

        $this->expectException(HttpException::class);
        $combat->useItem($session, $potion->id);
    }

    public function test_strength_scales_physical_damage(): void
    {
        $s = $this->scenario(charOverrides: ['strength' => 10, 'sp' => 200], monsterOverrides: ['max_hp' => 999, 'defense' => 0], skillPower: 50);
        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();

        $res = $combat->act($session, 'skill', $s['skill']->id);

        $this->assertFalse($res['used']['crit']);       // DEX & LUK baseline → tak kritikal
        $this->assertSame(59, $res['used']['damage']);  // floor(50 * (1 + (10-1)*0.02)) = 59
    }

    public function test_intelligence_scales_magic_damage(): void
    {
        $s = $this->scenario(charOverrides: ['intelligence' => 10], monsterOverrides: ['max_hp' => 999, 'defense' => 0]);
        $spell = Spell::create(['slug' => 'sihir-uji', 'name' => 'Sihir Uji', 'element' => 'api', 'power' => 50, 'mana_cost' => 1, 'min_level' => 1]);
        $s['char']->spells()->attach($spell->id);

        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();

        $res = $combat->act($session, 'spell', $spell->id);

        $this->assertFalse($res['used']['crit']);
        $this->assertSame(59, $res['used']['damage']);  // floor(50 * (1 + (10-1)*0.02)) = 59
    }

    public function test_high_agility_dodges_monster_attacks(): void
    {
        $s = $this->scenario(charOverrides: ['agility' => 101, 'hp' => 50], monsterOverrides: ['max_hp' => 999, 'attack' => 30]);
        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();

        $res = $combat->act($session, 'skill', $s['skill']->id);

        $this->assertTrue($res['counter']['dodged']);   // AGI 101 → (101-1)*1% = 100% dodge
        $this->assertSame(0, $res['counter']['damage']);
        $this->assertSame(50, $res['player_hp']);       // tak menerima damage
    }

    public function test_high_dexterity_always_crits(): void
    {
        $s = $this->scenario(charOverrides: ['dexterity' => 101], monsterOverrides: ['max_hp' => 999, 'defense' => 0], skillPower: 10);
        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();

        $res = $combat->act($session, 'skill', $s['skill']->id);

        $this->assertTrue($res['used']['crit']);        // DEX 101 → (101-1)*1% = 100% kritikal
        $this->assertSame(15, $res['used']['damage']);  // floor(10 * 1.5) = 15 (STR baseline)
    }

    public function test_vitality_reduces_damage_taken(): void
    {
        $s = $this->scenario(charOverrides: ['vitality' => 8, 'defense' => 0, 'agility' => 0], monsterOverrides: ['max_hp' => 999, 'attack' => 10]);
        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();

        $res = $combat->act($session, 'skill', $s['skill']->id);

        $this->assertFalse($res['counter']['dodged']);
        $this->assertSame(7, $res['counter']['damage']); // effDef = 0 + (8-1) = 7; max(1, 10 - floor(7/2)) = 7
    }

    public function test_luck_grants_bonus_gold(): void
    {
        $s = $this->scenario(charOverrides: ['luck' => 50], monsterOverrides: ['max_hp' => 1, 'gold_reward' => 10]);
        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();

        $res = $combat->act($session, 'skill', $s['skill']->id);

        $this->assertSame('won', $res['status']);
        $this->assertSame(14, $res['rewards']['gold']);  // 10 + floor(10 * (50-1) * 0.01) = 14
        $this->assertSame(14, $s['char']->fresh()->gold);
    }

    public function test_attacking_with_an_unknown_ability_is_rejected(): void
    {
        $s = $this->scenario();
        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();

        $this->expectException(HttpException::class);
        $combat->act($session, 'skill', 999999);
    }

    public function test_acting_after_combat_ends_is_rejected(): void
    {
        $s = $this->scenario(monsterOverrides: ['max_hp' => 1]);
        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();
        $combat->act($session, 'skill', $s['skill']->id); // win

        $this->expectException(HttpException::class);
        $combat->act($session->fresh(), 'skill', $s['skill']->id);
    }
}
