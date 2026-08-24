<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Monster;
use App\Models\Quest;
use App\Models\Skill;
use App\Models\Spell;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
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

    public function test_guests_are_redirected_from_admin(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_regular_players_are_forbidden(): void
    {
        $user = User::factory()->create(); // role defaults to "player"

        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs($user)->get('/admin/items')->assertForbidden();
        $this->actingAs($user)->get('/admin/spells')->assertForbidden();
    }

    public function test_superadmin_can_open_admin_pages(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/items')->assertOk();
        $this->actingAs($admin)->get('/admin/items/create')->assertOk();
        $this->actingAs($admin)->get('/admin/spells')->assertOk();
        $this->actingAs($admin)->get('/admin/spells/create')->assertOk();
    }

    public function test_superadmin_can_create_update_and_delete_an_item(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post('/admin/items', [
            'slug' => 'pedang-baja',
            'name' => 'Pedang Baja',
            'type' => 'weapon',
            'value' => 100,
            'stats' => '{"attack": 5}',
        ])->assertRedirect(route('admin.items.index'));

        $item = Item::where('slug', 'pedang-baja')->firstOrFail();
        $this->assertSame(['attack' => 5], $item->stats);

        $this->actingAs($admin)->put(route('admin.items.update', $item->id), [
            'slug' => 'pedang-baja',
            'name' => 'Pedang Baja +1',
            'type' => 'weapon',
            'value' => 150,
            'stats' => '{"attack": 8}',
        ])->assertRedirect(route('admin.items.index'));

        $this->assertSame('Pedang Baja +1', $item->fresh()->name);

        $this->actingAs($admin)->delete(route('admin.items.destroy', $item->id))
            ->assertRedirect(route('admin.items.index'));
        $this->assertDatabaseMissing('items', ['slug' => 'pedang-baja']);
    }

    public function test_superadmin_can_create_a_spell(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post('/admin/spells', [
            'slug' => 'bola-api',
            'name' => 'Bola Api',
            'element' => 'api',
            'power' => 20,
            'mana_cost' => 10,
            'min_level' => 1,
            'effects' => '{"type":"damage"}',
        ])->assertRedirect(route('admin.spells.index'));

        $spell = Spell::where('slug', 'bola-api')->firstOrFail();
        $this->assertSame('api', $spell->element);
        $this->assertSame(20, $spell->power);
        $this->assertSame(['type' => 'damage'], $spell->effects);
    }

    public function test_item_validation_rejects_bad_slug_and_invalid_json(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post('/admin/items', [
            'slug' => 'Bukan Slug!',
            'name' => 'X',
            'type' => 'misc',
        ])->assertSessionHasErrors('slug');

        $this->actingAs($admin)->post('/admin/items', [
            'slug' => 'slug-ok',
            'name' => 'X',
            'type' => 'misc',
            'stats' => '{rusak}',
        ])->assertSessionHasErrors('stats');
    }

    public function test_superadmin_can_create_a_monster(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post('/admin/monsters', [
            'slug' => 'naga-api',
            'name' => 'Naga Api',
            'max_hp' => 12,
            'attack' => 4,
            'defense' => 1,
            'magic_attack' => 9,
            'magic_defense' => 3,
            'attack_kind' => 'magic',
            'xp_reward' => 100,
            'gold_reward' => 40,
            'loot' => '[{"item_slug":"potion","chance":1,"qty":1}]',
        ])->assertRedirect(route('admin.monsters.index'));

        $monster = Monster::where('slug', 'naga-api')->firstOrFail();
        $this->assertSame(12, $monster->max_hp);
        $this->assertSame(9, $monster->magic_attack);
        $this->assertSame('magic', $monster->attack_kind);
        $this->assertSame([['item_slug' => 'potion', 'chance' => 1, 'qty' => 1]], $monster->loot);
    }

    public function test_monster_attack_kind_must_be_valid(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post('/admin/monsters', [
            'slug' => 'naga-palsu', 'name' => 'Naga Palsu', 'max_hp' => 5, 'attack' => 1,
            'attack_kind' => 'petir',
        ])->assertSessionHasErrors('attack_kind');
    }

    public function test_superadmin_can_create_a_skill(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post('/admin/skills', [
            'slug' => 'tebas',
            'name' => 'Tebas',
            'type' => 'physical',
            'power' => 4,
            'level_req' => 2,
            'is_default' => false,
        ])->assertRedirect(route('admin.skills.index'));

        $skill = Skill::where('slug', 'tebas')->firstOrFail();
        $this->assertSame(4, $skill->power);
        $this->assertFalse($skill->is_default);
    }

    public function test_superadmin_can_create_a_quest_then_a_node(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post('/admin/quests', [
            'slug' => 'hutan-gelap',
            'title' => 'Hutan Gelap',
            'min_level' => 1,
            'is_published' => true,
        ])->assertRedirect();

        $quest = Quest::where('slug', 'hutan-gelap')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.quests.nodes.store', $quest->id), [
            'key' => 'mulai',
            'type' => 'narrative',
            'title' => 'Awal',
            'body' => 'Kamu memasuki hutan.',
            'choices' => [
                ['label' => 'Maju', 'next_node_key' => 'lanjut'],
            ],
        ])->assertRedirect(route('admin.quests.edit', $quest->id));

        $node = $quest->nodes()->where('key', 'mulai')->firstOrFail();
        $this->assertSame('narrative', $node->type);
        $this->assertCount(1, $node->choices);
        $this->assertSame('Maju', $node->choices->first()->label);

        // Set the start node via a quest update.
        $this->actingAs($admin)->put(route('admin.quests.update', $quest->id), [
            'slug' => 'hutan-gelap', 'title' => 'Hutan Gelap', 'min_level' => 1, 'is_published' => true,
            'start_node' => 'mulai',
        ])->assertRedirect();

        $this->assertSame($node->id, $quest->fresh()->start_node_id);
    }

    public function test_magic_monster_form_requires_magic_attack(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post('/admin/monsters', [
            'slug' => 'penyihir-form', 'name' => 'Penyihir Form', 'max_hp' => 8, 'attack' => 2,
            'attack_kind' => 'magic', 'magic_attack' => 0,
        ])->assertSessionHasErrors('magic_attack');

        $this->assertDatabaseMissing('monsters', ['slug' => 'penyihir-form']);
    }
}
