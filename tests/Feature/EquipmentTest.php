<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Item;
use App\Models\Monster;
use App\Models\Quest;
use App\Models\QuestNode;
use App\Models\Skill;
use App\Models\User;
use App\Services\CombatService;
use App\Services\EquipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentTest extends TestCase
{
    use RefreshDatabase;

    private function equip(): EquipmentService
    {
        return app(EquipmentService::class);
    }

    private function character(array $overrides = []): Character
    {
        $user = User::factory()->create();

        return $user->characters()->create(array_merge([
            'name' => 'Hero', 'level' => 1, 'xp' => 0, 'hp' => 50, 'max_hp' => 50,
            'attack' => 10, 'defense' => 5, 'gold' => 0, 'is_alive' => true,
            'strength' => 1, 'agility' => 1, 'dexterity' => 1, 'intelligence' => 1, 'vitality' => 1, 'luck' => 1,
        ], $overrides));
    }

    private function gear(string $slug, string $type, array $stats, int $value = 50): Item
    {
        return Item::create(['slug' => $slug, 'name' => ucfirst($slug), 'type' => $type, 'stats' => $stats, 'value' => $value]);
    }

    public function test_equipping_marks_the_item_and_replaces_the_same_slot(): void
    {
        $char = $this->character();
        $sword = $this->gear('sword', 'weapon', ['attack' => 2]);
        $axe = $this->gear('axe', 'weapon', ['attack' => 4]);
        $char->items()->attach([$sword->id => ['quantity' => 1], $axe->id => ['quantity' => 1]]);

        $this->equip()->equip($char, $sword);
        $this->assertTrue((bool) $char->items()->where('item_id', $sword->id)->first()->pivot->equipped);

        // Memakai senjata kedua melepas yang pertama (satu slot).
        $this->equip()->equip($char->fresh(), $axe);
        $this->assertFalse((bool) $char->items()->where('item_id', $sword->id)->first()->pivot->equipped);
        $this->assertTrue((bool) $char->items()->where('item_id', $axe->id)->first()->pivot->equipped);
    }

    public function test_equip_is_blocked_below_required_level(): void
    {
        $char = $this->character(['level' => 1]);
        $axe = $this->gear('axe', 'weapon', ['attack' => 4, 'req_level' => 5]);
        $char->items()->attach($axe->id, ['quantity' => 1]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->equip()->equip($char, $axe);
    }

    public function test_bonuses_sum_across_slots_and_effective_stats_update(): void
    {
        $char = $this->character(['strength' => 1, 'attack' => 10, 'defense' => 5]);
        $sword = $this->gear('sword', 'weapon', ['attack' => 3, 'strength' => 2]);
        $armor = $this->gear('armor', 'armor', ['defense' => 4]);
        $ring = $this->gear('ring', 'accessory', ['strength' => 1, 'luck' => 2]);
        $char->items()->attach([$sword->id => ['quantity' => 1], $armor->id => ['quantity' => 1], $ring->id => ['quantity' => 1]]);

        $this->equip()->equip($char, $sword);
        $this->equip()->equip($char->fresh(), $armor);
        $this->equip()->equip($char->fresh(), $ring);

        $eff = $this->equip()->effective($char->fresh());
        $this->assertSame(13, $eff['attack']);    // 10 + 3
        $this->assertSame(9, $eff['defense']);     // 5 + 4
        $this->assertSame(4, $eff['strength']);    // 1 + 2 + 1
        $this->assertSame(3, $eff['luck']);        // 1 + 2
    }

    public function test_unequipping_removes_the_bonus(): void
    {
        $char = $this->character();
        $armor = $this->gear('armor', 'armor', ['defense' => 4]);
        $char->items()->attach($armor->id, ['quantity' => 1]);

        $this->equip()->equip($char, $armor);
        $this->assertSame(9, $this->equip()->effective($char->fresh())['defense']);

        $this->equip()->unequip($char->fresh(), $armor);
        $this->assertSame(5, $this->equip()->effective($char->fresh())['defense']); // kembali ke dasar
        $this->assertFalse((bool) $char->items()->where('item_id', $armor->id)->first()->pivot->equipped);
    }

    public function test_equipped_weapon_increases_physical_damage(): void
    {
        $char = $this->character(['sp' => 200, 'strength' => 1]);
        $skill = Skill::create(['slug' => 'pukul', 'name' => 'Pukul', 'type' => 'physical', 'power' => 50, 'is_default' => true]);
        $char->skills()->attach($skill->id);
        $sword = $this->gear('sword', 'weapon', ['attack' => 5]);
        $char->items()->attach($sword->id, ['quantity' => 1]);

        $quest = Quest::create(['slug' => 'q', 'title' => 'Q', 'min_level' => 1]);
        $monster = Monster::create(['slug' => 'd', 'name' => 'D', 'max_hp' => 999, 'attack' => 1, 'defense' => 0, 'xp_reward' => 1, 'gold_reward' => 1, 'loot' => []]);
        $node = QuestNode::create(['quest_id' => $quest->id, 'key' => 'f', 'type' => 'combat', 'monster_id' => $monster->id, 'payload' => ['on_win_node_key' => 'w', 'on_lose_node_key' => 'l']]);
        $char->saves()->create(['slot' => 1, 'quest_id' => $quest->id, 'current_node_key' => 'f', 'state' => []]);

        $combat = app(CombatService::class);
        $this->equip()->equip($char, $sword);

        $combat->start($char->fresh(), $node->load('monster'));
        $session = $char->activeCombat()->first();
        $res = $combat->act($session, 'skill', $skill->id);

        // floor(50 * 1) + bonus attack 5 = 55 (pertahanan monster 0).
        $this->assertSame(55, $res['used']['damage']);
    }

    public function test_equip_and_unequip_via_http(): void
    {
        $user = User::factory()->create();
        $char = $user->characters()->create([
            'name' => 'Hero', 'level' => 1, 'xp' => 0, 'hp' => 50, 'max_hp' => 50,
            'attack' => 10, 'defense' => 5, 'gold' => 0, 'is_alive' => true,
        ]);
        $sword = $this->gear('sword', 'weapon', ['attack' => 3]);
        $char->items()->attach($sword->id, ['quantity' => 1]);

        $this->actingAs($user)->post(route('character.equip'), ['item_id' => $sword->id])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertTrue((bool) $char->items()->where('item_id', $sword->id)->first()->pivot->equipped);

        $this->actingAs($user)->post(route('character.unequip'), ['item_id' => $sword->id])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertFalse((bool) $char->items()->where('item_id', $sword->id)->first()->pivot->equipped);
    }

    public function test_a_consumable_cannot_be_equipped(): void
    {
        $char = $this->character();
        $potion = $this->gear('potion', 'consumable', ['heal' => 30], 25);
        $char->items()->attach($potion->id, ['quantity' => 1]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->equip()->equip($char, $potion);
    }
}
