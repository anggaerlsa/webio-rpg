<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CombatSession;
use App\Models\Item;
use App\Models\Monster;
use App\Models\QuestNode;
use App\Models\Skill;
use App\Models\Spell;
use App\Services\Combat\AttackModule;
use App\Services\Combat\MagicalAttack;
use App\Services\Combat\PhysicalAttack;
use Illuminate\Support\Facades\DB;

/**
 * Turn-based action combat. Each turn the player picks an attack (a skill or
 * spell they know — everyone has "Pukul" by default) which deals its `power`
 * as damage to the monster; the monster then strikes back. All HP, damage and
 * rewards are computed and stored server-side.
 */
class CombatService
{
    // Efek atribut yang berlaku untuk SEMUA jalur serangan (tunable). Yang khas
    // per jalur (STR/INT, attack/magic_attack, defense/magic_defense) tinggal di
    // modulnya masing-masing: App\Services\Combat\{Physical,Magical}Attack.
    private const DODGE_PER_AGI = 0.01;  // +1% peluang menghindar per AGI
    private const CRIT_PER_DEX = 0.01;   // +1% peluang kritikal per DEX
    private const CRIT_PER_LUK = 0.005;  // +0.5% peluang kritikal per LUK
    private const CRIT_MULTIPLIER = 1.5; // kritikal = damage ×1.5
    private const GOLD_PER_LUK = 0.01;   // +1% emas per LUK

    public function __construct(
        private LevelService $levels,
        private StoryEngine $story,
        private EquipmentService $equipment,
        private PhysicalAttack $physical,
        private MagicalAttack $magical,
    ) {}

    /** Modul jalur serangan berdasarkan resource-nya ('sp' → fisik, 'mp' → sihir). */
    private function module(string $resource): AttackModule
    {
        return $resource === 'mp' ? $this->magical : $this->physical;
    }

    /** Poin atribut efektif: stat 1 = baseline (efek 0); tiap poin DI ATAS 1 baru berpengaruh. */
    private function bonusStat(int $value): int
    {
        return max(0, $value - 1);
    }

    /** Peluang menghindar serangan (0..1) dari AGI efektif (dasar + equipment). */
    private function dodgeChance(Character $c): float
    {
        return min(1.0, $this->bonusStat($this->equipment->effective($c)['agility']) * self::DODGE_PER_AGI);
    }

    /** Peluang kritikal (0..1) dari DEX + LUK efektif. */
    private function critChance(Character $c): float
    {
        $eff = $this->equipment->effective($c);

        return min(1.0, $this->bonusStat($eff['dexterity']) * self::CRIT_PER_DEX + $this->bonusStat($eff['luck']) * self::CRIT_PER_LUK);
    }

    /**
     * Damage serangan pemain ke monster: modul jalurnya menghitung damage mentah
     * (skala atribut + bonus perlengkapan jalur itu), lalu mungkin kritikal,
     * dikurangi pertahanan monster pada jalur yang sama. Minimal 1 bila kena.
     *
     * @return array{damage: int, crit: bool, kind: string}
     */
    private function attackDamage(Character $c, array $ability, Monster $monster): array
    {
        $module = $this->module($ability['resource']);

        $raw = $module->playerDamage($c, (int) $ability['power']);

        $crit = $this->rollChance($this->critChance($c));
        if ($crit) {
            $raw = (int) floor($raw * self::CRIT_MULTIPLIER);
        }

        return [
            'damage' => max(1, $raw - intdiv($module->monsterDefense($monster), 2)),
            'crit' => $crit,
            'kind' => $module->label(),
        ];
    }

    /**
     * Jalur serangan balik sebuah monster. Sihir hanya dipakai bila memang
     * disetel sebagai penyihir DAN punya kekuatan sihir — kalau tidak, jatuh ke
     * fisik supaya monster tanpa data sihir tidak jadi tak berbahaya.
     */
    private function monsterModule(Monster $monster): AttackModule
    {
        $isMagic = $monster->attack_kind === 'magic' && (int) $monster->magic_attack > 0;

        return $this->module($isMagic ? 'mp' : 'sp');
    }

    /**
     * Serangan balik monster ke pemain: dihitung modul sesuai jalur monster,
     * bisa dihindari (AGI), direduksi pertahanan jalur itu (fisik: defense+VIT,
     * sihir: magic_defense+INT).
     *
     * @return array{damage: int, dodged: bool, kind: string}
     */
    private function monsterCounter(Character $c, Monster $monster): array
    {
        $module = $this->monsterModule($monster);

        if ($this->rollChance($this->dodgeChance($c))) {
            return ['damage' => 0, 'dodged' => true, 'kind' => $module->label()];
        }

        return [
            'damage' => max(1, $module->monsterPower($monster) - intdiv($module->playerDefense($c), 2)),
            'dodged' => false,
            'kind' => $module->label(),
        ];
    }

    /** Lempar dadu peluang. 0 = tak pernah, ≥1 = selalu (mempermudah uji & kasus ekstrem). */
    private function rollChance(float $probability): bool
    {
        if ($probability <= 0) {
            return false;
        }
        if ($probability >= 1) {
            return true;
        }

        return (random_int(1, 1_000_000) / 1_000_000) <= $probability;
    }

    /** Start a fresh combat for a combat node, or resume the active one for that node. */
    public function start(Character $character, QuestNode $node, int $slot = 1): array
    {
        if ($node->type !== 'combat' || ! $node->monster_id) {
            abort(422, 'Adegan ini bukan pertarungan.');
        }
        if (! $character->is_alive || $character->hp <= 0) {
            abort(422, 'Karaktermu telah tumbang dan tak bisa bertarung.');
        }

        $character->grantDefaultAbilities(); // pastikan minimal punya "Pukul"

        $session = CombatSession::query()
            ->where('character_id', $character->id)
            ->where('quest_node_id', $node->id)
            ->where('status', 'active')
            ->first();

        if (! $session) {
            CombatSession::query()
                ->where('character_id', $character->id)
                ->where('status', 'active')
                ->delete();

            $monster = $node->monster;
            $session = CombatSession::create([
                'character_id' => $character->id,
                'quest_node_id' => $node->id,
                'monster_id' => $monster->id,
                'monster_hp' => $monster->max_hp,
                'player_hp' => $character->hp,
                'asked_question_ids' => [],
                'status' => 'active',
                'turn' => 0,
            ]);
        }

        return $this->view($session->refresh(), $character);
    }

    /**
     * Resolve one combat turn: the player uses an attack, then the monster
     * strikes back (unless it died).
     *
     * @return array<string, mixed>
     */
    public function act(CombatSession $session, string $kind, int $abilityId, int $slot = 1): array
    {
        return DB::transaction(function () use ($session, $kind, $abilityId, $slot) {
            if ($session->status !== 'active') {
                abort(422, 'Pertarungan ini sudah selesai.');
            }

            $character = $session->character;
            $monster = $session->monster;
            $ability = $this->resolveAbility($character, $kind, $abilityId);

            // 1) Pemain menyerang — bayar SP (fisik) / MP (sihir) lebih dulu.
            //    Resource kurang → serangan DITOLAK; giliran tidak terbuang.
            $resource = $ability['resource']; // 'sp' | 'mp'
            $cost = $ability['cost'];
            $have = (int) $character->{$resource};
            abort_if(
                $have < $cost,
                422,
                strtoupper($resource)." tidak cukup untuk {$ability['name']} — butuh {$cost}, punya {$have}.",
            );
            $character->{$resource} = $have - $cost;

            // Damage dihitung modul jalurnya + kemungkinan kritikal (DEX/LUK).
            $hit = $this->attackDamage($character, $ability, $monster);
            $dmgToMonster = $hit['damage'];
            $session->monster_hp = max(0, $session->monster_hp - $dmgToMonster);
            $session->turn += 1;

            $result = [
                'used' => [
                    'kind' => $kind, 'id' => $ability['id'], 'name' => $ability['name'],
                    'damage' => $dmgToMonster, 'cost' => $cost, 'resource' => $resource,
                    'attack_kind' => $hit['kind'], 'crit' => $hit['crit'],
                ],
                'counter' => null,
            ];

            $extra = [];
            if ($session->monster_hp <= 0) {
                $extra = $this->resolveWin($session, $character, $slot);
            } else {
                // 2) Monster strikes back (bisa dihindari berkat AGI, direduksi VIT).
                $counter = $this->monsterCounter($character, $monster);
                $session->player_hp = max(0, $session->player_hp - $counter['damage']);
                $result['counter'] = $counter;

                if ($session->player_hp <= 0) {
                    $extra = $this->resolveLoss($session, $character, $slot);
                } else {
                    $character->hp = $session->player_hp; // persist mid-fight HP
                    $character->save();
                    $session->save();
                }
            }

            return array_merge([
                'session_id' => $session->id,
                'monster_hp' => $session->monster_hp,
                'max_monster_hp' => $monster->max_hp,
                'player_hp' => $session->player_hp,
                'max_player_hp' => $character->max_hp,
                'player_sp' => $character->sp,
                'max_sp' => $character->max_sp,
                'player_mp' => $character->mp,
                'max_mp' => $character->max_mp,
                'status' => $session->status,
                'turn' => $session->turn,
                'attacks' => $this->availableAttacks($character),
                'potions' => $this->availablePotions($character),
            ], $result, $extra);
        });
    }

    /**
     * Use a healing potion mid-fight. Drinking costs the player's turn, so the
     * monster still strikes back afterwards. HP, item stock and result are all
     * computed and persisted server-side.
     *
     * @return array<string, mixed>
     */
    public function useItem(CombatSession $session, int $itemId, int $slot = 1): array
    {
        return DB::transaction(function () use ($session, $itemId, $slot) {
            if ($session->status !== 'active') {
                abort(422, 'Pertarungan ini sudah selesai.');
            }

            $character = $session->character;
            $monster = $session->monster;

            $item = Item::find($itemId);
            abort_if(! $item || ! $this->story->isUsableConsumable($item), 422, 'Item itu tidak bisa dipakai.');

            $owned = $character->items()->where('item_id', $item->id)->first();
            abort_unless($owned && $owned->pivot->quantity > 0, 422, 'Kamu tidak memiliki item itu.');

            // 1) Drink: restore HP/SP/MP (each capped at its max) and consume one.
            //    This is the player's action — the monster still strikes back.
            $heal = $this->story->healAmount($item);
            $spGain = $this->story->spRestoreAmount($item);
            $mpGain = $this->story->mpRestoreAmount($item);

            $before = $session->player_hp;
            $session->player_hp = min($character->max_hp, $session->player_hp + $heal);
            $healed = $session->player_hp - $before;
            if ($spGain > 0) {
                $character->sp = min($character->max_sp, $character->sp + $spGain);
            }
            if ($mpGain > 0) {
                $character->mp = min($character->max_mp, $character->mp + $mpGain);
            }
            $this->story->takeItem($character, $item->slug, 1);
            $session->turn += 1;

            $result = [
                'used' => ['kind' => 'item', 'id' => $item->id, 'name' => $item->name, 'heal' => $healed],
                'counter' => null,
            ];

            // 2) Monster strikes back (bisa dihindari berkat AGI, direduksi VIT).
            $counter = $this->monsterCounter($character, $monster);
            $session->player_hp = max(0, $session->player_hp - $counter['damage']);
            $result['counter'] = $counter;

            $extra = [];
            if ($session->player_hp <= 0) {
                $extra = $this->resolveLoss($session, $character, $slot);
            } else {
                $character->hp = $session->player_hp; // persist mid-fight HP
                $character->save();
                $session->save();
            }

            return array_merge([
                'session_id' => $session->id,
                'monster_hp' => $session->monster_hp,
                'max_monster_hp' => $monster->max_hp,
                'player_hp' => $session->player_hp,
                'max_player_hp' => $character->max_hp,
                'player_sp' => $character->sp,
                'max_sp' => $character->max_sp,
                'player_mp' => $character->mp,
                'max_mp' => $character->max_mp,
                'status' => $session->status,
                'turn' => $session->turn,
                'attacks' => $this->availableAttacks($character),
                'potions' => $this->availablePotions($character),
            ], $result, $extra);
        });
    }

    /** Sanitized snapshot of an active/finished session (used for start + resume). */
    public function view(CombatSession $session, Character $character): array
    {
        $monster = $session->monster;

        return [
            'session_id' => $session->id,
            'status' => $session->status,
            'turn' => $session->turn,
            'monster' => ['name' => $monster->name, 'image' => $monster->image],
            'monster_hp' => $session->monster_hp,
            'max_monster_hp' => $monster->max_hp,
            'player_hp' => $session->player_hp,
            'max_player_hp' => $character->max_hp,
            'player_sp' => $character->sp,
            'max_sp' => $character->max_sp,
            'player_mp' => $character->mp,
            'max_mp' => $character->max_mp,
            'attacks' => $this->availableAttacks($character),
            'potions' => $this->availablePotions($character),
        ];
    }

    /**
     * Healing potions the character can drink this fight (consumables with
     * heal > 0 still in stock).
     *
     * @return array<int, array<string, mixed>>
     */
    public function availablePotions(Character $character): array
    {
        $character->loadMissing('items');

        return $character->items
            ->filter(fn (Item $i) => $this->story->isUsableConsumable($i) && $i->pivot->quantity > 0)
            ->map(fn (Item $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'heal' => $this->story->healAmount($i),
                'restore_sp' => $this->story->spRestoreAmount($i),
                'restore_mp' => $this->story->mpRestoreAmount($i),
                'quantity' => $i->pivot->quantity,
            ])->values()->all();
    }

    /**
     * The attacks the character can use this fight: known skills + known spells.
     *
     * @return array<int, array<string, mixed>>
     */
    public function availableAttacks(Character $character): array
    {
        $character->loadMissing(['skills', 'spells']);

        $skills = $character->skills->map(fn (Skill $s) => [
            'kind' => 'skill', 'id' => $s->id, 'name' => $s->name, 'power' => $s->power, 'type' => $s->type,
            'cost' => $this->skillCost($s), 'resource' => 'sp',
        ]);
        $spells = $character->spells->map(fn (Spell $s) => [
            'kind' => 'spell', 'id' => $s->id, 'name' => $s->name, 'power' => $s->power, 'type' => $s->element,
            'cost' => $this->spellCost($s), 'resource' => 'mp',
        ]);

        return $skills->concat($spells)->values()->all();
    }

    /** Stamina cost of a physical skill — its explicit cost, or its power if unset (cost scales with power). */
    private function skillCost(Skill $skill): int
    {
        return $skill->stamina_cost > 0 ? (int) $skill->stamina_cost : max(0, (int) $skill->power);
    }

    /** Mana cost of a spell — its explicit cost, or its power if unset. */
    private function spellCost(Spell $spell): int
    {
        return $spell->mana_cost > 0 ? (int) $spell->mana_cost : max(0, (int) $spell->power);
    }

    /** @return array{id: int, name: string, power: int, cost: int, resource: string} */
    private function resolveAbility(Character $character, string $kind, int $abilityId): array
    {
        if ($kind === 'skill') {
            $skill = $character->skills()->whereKey($abilityId)->first();
            abort_unless($skill, 422, 'Kamu belum menguasai skill itu.');

            return ['id' => $skill->id, 'name' => $skill->name, 'power' => (int) $skill->power, 'cost' => $this->skillCost($skill), 'resource' => 'sp'];
        }

        if ($kind === 'spell') {
            $spell = $character->spells()->whereKey($abilityId)->first();
            abort_unless($spell, 422, 'Kamu belum menguasai sihir itu.');

            return ['id' => $spell->id, 'name' => $spell->name, 'power' => (int) $spell->power, 'cost' => $this->spellCost($spell), 'resource' => 'mp'];
        }

        abort(422, 'Jenis serangan tidak valid.');
    }

    /** @return array<string, mixed> */
    private function resolveWin(CombatSession $session, Character $character, int $slot): array
    {
        $monster = $session->monster;
        $session->status = 'won';

        $character->hp = max(1, $session->player_hp); // survive the fight
        $character->is_alive = true;

        $xp = $this->levels->grantXp($character, $monster->xp_reward);
        // Bonus emas dari LUK efektif (poin di atas 1).
        $gold = $monster->gold_reward + (int) floor($monster->gold_reward * $this->bonusStat($this->equipment->effective($character)['luck']) * self::GOLD_PER_LUK);
        $character->gold += $gold;
        $loot = $this->rollLoot($monster, $character);

        $character->save();
        $session->save();

        $nextNode = $this->advanceStory($session, $character, 'on_win_node_key', $slot);

        return [
            'rewards' => [
                'xp' => $monster->xp_reward,
                'gold' => $gold,
                'items' => $loot,
                'leveled_up' => $xp['leveled_up'],
                'new_level' => $xp['new_level'],
            ],
            'next_node' => $nextNode,
        ];
    }

    /** @return array<string, mixed> */
    private function resolveLoss(CombatSession $session, Character $character, int $slot): array
    {
        $session->status = 'lost';
        $character->hp = 0;
        $character->is_alive = false;
        $character->save();
        $session->save();

        $nextNode = $this->advanceStory($session, $character, 'on_lose_node_key', $slot);

        return ['next_node' => $nextNode];
    }

    /** Advance the story save to the win/lose node defined on the combat node's payload. */
    private function advanceStory(CombatSession $session, Character $character, string $payloadKey, int $slot): ?array
    {
        $node = $session->node;
        $targetKey = $node->payload[$payloadKey] ?? null;
        if (! $targetKey) {
            return null;
        }

        $save = $this->story->save($character, $slot);
        $save->current_node_key = $targetKey;
        $state = $save->state ?? [];
        $state['visited'][] = $targetKey;
        $save->state = $state;
        $save->last_played_at = now();
        $save->save();

        $this->story->onEnterNode($character, $save, $this->story->currentNode($save));

        return ['quest_id' => $save->quest_id, 'node_key' => $targetKey];
    }

    /**
     * Roll a monster's loot table and grant winning drops.
     *
     * @return array<int, array{slug: string, qty: int}>
     */
    private function rollLoot(Monster $monster, Character $character): array
    {
        $given = [];
        foreach (($monster->loot ?? []) as $entry) {
            $chance = (float) ($entry['chance'] ?? 1);
            if ((random_int(0, 1000) / 1000) <= $chance) {
                $slug = $entry['item_slug'] ?? null;
                $qty = (int) ($entry['qty'] ?? 1);
                if ($slug) {
                    $this->story->giveItem($character, $slug, $qty);
                    $given[] = ['slug' => $slug, 'qty' => $qty];
                }
            }
        }

        return $given;
    }
}
