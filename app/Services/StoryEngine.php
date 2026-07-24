<?php

namespace App\Services;

use App\Models\Character;
use App\Models\GameSave;
use App\Models\Item;
use App\Models\NodeChoice;
use App\Models\Quest;
use App\Models\QuestNode;
use App\Models\Skill;
use App\Models\Spell;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the branching story graph: where the player is, which choices are
 * available, and what happens when a choice is taken. All mutations are
 * server-side and persisted; the client only renders the returned state.
 */
class StoryEngine
{
    public function __construct(
        private LevelService $levels,
        private EquipmentService $equipment,
        private LearningService $learning,
    ) {}

    /** Find or create the save row for a character + slot. */
    public function save(Character $character, int $slot = 1): GameSave
    {
        return GameSave::firstOrCreate(
            ['character_id' => $character->id, 'slot' => $slot],
            ['state' => ['flags' => [], 'visited' => [], 'rewards_applied' => []]]
        );
    }

    /** Begin (or restart) a quest. Revives the character for a fresh attempt. */
    public function startQuest(Character $character, Quest $quest, int $slot = 1): GameSave
    {
        return DB::transaction(function () use ($character, $quest, $slot) {
            $save = $this->save($character, $slot);
            $startNode = $quest->startNode;

            // HP/SP/MP dibawa apa adanya antar quest — TIDAK di-reset penuh saat masuk.
            // Hanya karakter yang sudah tumbang yang dibangkitkan penuh (respawn) agar bisa mencoba lagi.
            if (! $character->is_alive || $character->hp <= 0) {
                $character->hp = $character->max_hp;
                $character->sp = $character->max_sp;
                $character->mp = $character->max_mp;
                $character->is_alive = true;
                $character->save();
            }

            $save->quest_id = $quest->id;
            $save->current_node_key = $startNode?->key;
            $save->state = [
                'flags' => [],
                'visited' => $startNode ? [$startNode->key] : [],
                'rewards_applied' => [],
            ];
            $save->last_played_at = now();
            $save->save();

            $this->onEnterNode($character, $save, $this->currentNode($save));

            return $save->refresh();
        });
    }

    /** The QuestNode the save currently points at (with choices + monster eager-loaded). */
    public function currentNode(GameSave $save): ?QuestNode
    {
        if (! $save->quest_id || ! $save->current_node_key) {
            return null;
        }

        return QuestNode::query()
            ->where('quest_id', $save->quest_id)
            ->where('key', $save->current_node_key)
            ->with(['choices', 'monster'])
            ->first();
    }

    /** Full client-facing state for the player's current position. */
    public function currentState(Character $character, int $slot = 1): array
    {
        $save = $this->save($character, $slot);
        $node = $this->currentNode($save);
        $quest = $save->quest_id ? Quest::find($save->quest_id) : null;

        return [
            'character' => $this->characterState($character->refresh()),
            'quest' => $quest ? [
                'id' => $quest->id,
                'slug' => $quest->slug,
                'title' => $quest->title,
            ] : null,
            'node' => $node ? $this->nodeState($node, $character, $save) : null,
            'save' => [
                'slot' => $save->slot,
                'current_node_key' => $save->current_node_key,
                'flags' => $save->state['flags'] ?? [],
            ],
        ];
    }

    /** Serialize a node for the client, including each choice's locked state. */
    public function nodeState(QuestNode $node, Character $character, GameSave $save): array
    {
        return [
            'id' => $node->id,
            'key' => $node->key,
            'type' => $node->type,
            'title' => $node->title,
            'body' => $node->body,
            'image' => $node->image,
            'monster_id' => $node->monster_id,
            'payload' => $node->payload,
            'choices' => $node->choices->map(function (NodeChoice $c) use ($character, $save) {
                $met = $this->meetsRequirements($c->requirements, $character, $save);

                return [
                    'id' => $c->id,
                    'label' => $c->label,
                    'is_auto' => $c->is_auto,
                    'locked' => ! $met,
                    'hint' => $met ? null : $this->lockHint($c->requirements),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Apply a player's choice: validate, run effects, advance to the next node.
     * Returns the new client state.
     */
    public function choose(Character $character, NodeChoice $choice, int $slot = 1): array
    {
        return DB::transaction(function () use ($character, $choice, $slot) {
            $save = $this->save($character, $slot);
            $node = $this->currentNode($save);

            if (! $node || (int) $choice->quest_node_id !== (int) $node->id) {
                abort(422, 'That choice does not belong to your current scene.');
            }
            if (! $this->meetsRequirements($choice->requirements, $character, $save)) {
                abort(422, 'You do not meet the requirements for that choice.');
            }

            $this->applyEffects($choice->effects, $character, $save);

            $state = $save->state ?? ['flags' => [], 'visited' => [], 'rewards_applied' => []];
            $save->current_node_key = $choice->next_node_key;
            if ($choice->next_node_key) {
                $state['visited'][] = $choice->next_node_key;
            }
            $save->state = $state;
            $save->last_played_at = now();

            $character->save();
            $save->save();

            // Resolve the landing node (apply reward payloads once, etc.).
            $this->onEnterNode($character, $save, $this->currentNode($save));

            return $this->currentState($character, $slot);
        });
    }

    /** Side effects when the player lands on a node (reward payloads applied once). */
    public function onEnterNode(Character $character, GameSave $save, ?QuestNode $node): void
    {
        if (! $node) {
            return;
        }

        // Mencapai ending sukses → selesaikan misi aktif (catat, progres rank).
        // Hindari circular dependency dengan resolve RankService secara lazy.
        if ($node->type === 'ending' && ($node->payload['result'] ?? 'victory') !== 'defeat') {
            $quest = $save->quest_id ? Quest::find($save->quest_id) : null;
            if ($quest) {
                app(\App\Services\RankService::class)->complete($character, $quest);
            }
        }

        if ($node->type !== 'reward') {
            return;
        }

        $state = $save->state ?? [];
        $applied = $state['rewards_applied'] ?? [];
        if (in_array($node->key, $applied, true)) {
            return;
        }

        $payload = $node->payload ?? [];
        if (! empty($payload['xp'])) {
            $this->levels->grantXp($character, (int) $payload['xp']);
        }
        if (! empty($payload['gold'])) {
            $character->gold += (int) $payload['gold'];
        }
        foreach (($payload['item_slugs'] ?? []) as $slug) {
            $this->giveItem($character, $slug);
        }

        $applied[] = $node->key;
        $state['rewards_applied'] = $applied;
        $save->state = $state;

        $character->save();
        $save->save();
    }

    /** @param array<string, mixed>|null $req */
    public function meetsRequirements(?array $req, Character $character, GameSave $save): bool
    {
        if (empty($req)) {
            return true;
        }
        if (isset($req['min_level']) && $character->level < (int) $req['min_level']) {
            return false;
        }
        if (isset($req['min_gold']) && $character->gold < (int) $req['min_gold']) {
            return false;
        }
        if (isset($req['has_item']) && ! $this->hasItem($character, $req['has_item'])) {
            return false;
        }
        if (isset($req['flag'])) {
            $flags = $save->state['flags'] ?? [];
            if (empty($flags[$req['flag']])) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed>|null $effects */
    public function applyEffects(?array $effects, Character $character, GameSave $save): void
    {
        if (empty($effects)) {
            return;
        }

        if (isset($effects['hp'])) {
            $character->hp = max(0, min($character->max_hp, $character->hp + (int) $effects['hp']));
            $character->is_alive = $character->hp > 0;
        }
        if (isset($effects['gold'])) {
            $character->gold = max(0, $character->gold + (int) $effects['gold']);
        }
        if (! empty($effects['xp'])) {
            $this->levels->grantXp($character, (int) $effects['xp']);
        }
        if (! empty($effects['give_item'])) {
            $this->giveItem($character, $effects['give_item']);
        }
        if (! empty($effects['take_item'])) {
            $this->takeItem($character, $effects['take_item']);
        }
        if (! empty($effects['flags']) && is_array($effects['flags'])) {
            $state = $save->state ?? [];
            $state['flags'] = array_merge($state['flags'] ?? [], $effects['flags']);
            $save->state = $state;
        }
    }

    public function giveItem(Character $character, string $slug, int $qty = 1): void
    {
        $item = Item::where('slug', $slug)->first();
        if (! $item) {
            return;
        }
        $existing = $character->items()->where('item_id', $item->id)->first();
        if ($existing) {
            $character->items()->updateExistingPivot($item->id, [
                'quantity' => $existing->pivot->quantity + $qty,
            ]);
        } else {
            $character->items()->attach($item->id, ['quantity' => $qty]);
        }
    }

    /** HP yang dipulihkan sebuah consumable (0 jika bukan potion HP). */
    public function healAmount(Item $item): int
    {
        return $this->restoreFor($item, 'heal');
    }

    /** SP (stamina) yang dipulihkan sebuah consumable. */
    public function spRestoreAmount(Item $item): int
    {
        return $this->restoreFor($item, 'restore_sp');
    }

    /** MP (mana) yang dipulihkan sebuah consumable. */
    public function mpRestoreAmount(Item $item): int
    {
        return $this->restoreFor($item, 'restore_mp');
    }

    /** Nilai sebuah field restore pada consumable (0 untuk item non-consumable). */
    private function restoreFor(Item $item, string $key): int
    {
        if ($item->type !== 'consumable') {
            return 0;
        }

        return max(0, (int) (($item->stats[$key] ?? 0)));
    }

    /** Apakah item ini sebuah consumable yang memulihkan HP/SP/MP. */
    public function isUsableConsumable(Item $item): bool
    {
        return $this->healAmount($item) > 0
            || $this->spRestoreAmount($item) > 0
            || $this->mpRestoreAmount($item) > 0;
    }

    /**
     * Pakai sebuah consumable untuk memulihkan HP/SP/MP karakter (di luar
     * pertarungan). Mengembalikan jumlah tiap pool yang benar-benar dipulihkan
     * (['hp' => x, 'sp' => y, 'mp' => z]). Server-authoritative & ter-persist;
     * tiap pool di-cap pada maksimumnya, stok item berkurang satu. Ditolak bila
     * item tidak bisa memulihkan apa pun (semua pool relevan sudah penuh).
     *
     * @return array{hp: int, sp: int, mp: int}
     */
    public function useConsumable(Character $character, Item $item): array
    {
        abort_unless($this->isUsableConsumable($item), 422, "{$item->name} tidak bisa dipakai.");

        $owned = $character->items()->where('item_id', $item->id)->first();
        abort_unless($owned && $owned->pivot->quantity > 0, 422, 'Kamu tidak memiliki item itu.');

        $hp = $this->healAmount($item);
        $sp = $this->spRestoreAmount($item);
        $mp = $this->mpRestoreAmount($item);

        // Tak ada efek bila semua pool yang bisa dipulihkan item ini sudah penuh.
        $wouldDoNothing = ($hp <= 0 || $character->hp >= $character->max_hp)
            && ($sp <= 0 || $character->sp >= $character->max_sp)
            && ($mp <= 0 || $character->mp >= $character->max_mp);
        abort_if($wouldDoNothing, 422, "{$item->name} tidak memulihkan apa pun saat ini.");

        return DB::transaction(function () use ($character, $item, $hp, $sp, $mp) {
            $restored = ['hp' => 0, 'sp' => 0, 'mp' => 0];

            if ($hp > 0) {
                $before = $character->hp;
                $character->hp = min($character->max_hp, $character->hp + $hp);
                $character->is_alive = true; // potion HP bisa membangkitkan dari tumbang
                $restored['hp'] = $character->hp - $before;
            }
            if ($sp > 0) {
                $before = $character->sp;
                $character->sp = min($character->max_sp, $character->sp + $sp);
                $restored['sp'] = $character->sp - $before;
            }
            if ($mp > 0) {
                $before = $character->mp;
                $character->mp = min($character->max_mp, $character->mp + $mp);
                $restored['mp'] = $character->mp - $before;
            }

            $character->save();
            $this->takeItem($character, $item->slug, 1);

            return $restored;
        });
    }

    public function takeItem(Character $character, string $slug, int $qty = 1): void
    {
        $item = Item::where('slug', $slug)->first();
        if (! $item) {
            return;
        }
        $existing = $character->items()->where('item_id', $item->id)->first();
        if (! $existing) {
            return;
        }
        $remaining = $existing->pivot->quantity - $qty;
        if ($remaining > 0) {
            $character->items()->updateExistingPivot($item->id, ['quantity' => $remaining]);
        } else {
            $character->items()->detach($item->id);
        }
    }

    /** @return array<string, mixed> */
    public function characterState(Character $character): array
    {
        $character->loadMissing(['items', 'user', 'skills', 'spells']);

        return [
            'id' => $character->id,
            'name' => $character->name,
            'class' => $character->class,
            'gender' => $character->gender,
            'birth_date' => $character->birth_date?->toDateString(),
            'age' => $character->birth_date?->age,
            'affiliation' => $character->affiliation,
            'rank' => $character->rank,
            'rank_progress' => (int) $character->rank_progress,
            'job' => $character->user?->job,
            'level' => $character->level,
            'xp' => $character->xp,
            'xp_to_next' => $this->levels->xpForLevel($character->level),
            'hp' => $character->hp,
            'max_hp' => $character->max_hp,
            'sp' => $character->sp,
            'max_sp' => $character->max_sp,
            'mp' => $character->mp,
            'max_mp' => $character->max_mp,
            'strength' => $character->strength,
            'agility' => $character->agility,
            'dexterity' => $character->dexterity,
            'intelligence' => $character->intelligence,
            'vitality' => $character->vitality,
            'luck' => $character->luck,
            'attack' => $character->attack,
            'defense' => $character->defense,
            'gold' => $character->gold,
            'is_alive' => $character->is_alive,
            'avatar' => $character->avatar,
            'effective' => $this->equipment->effective($character),  // stat dasar + bonus equipment
            'equip_bonuses' => $this->equipment->bonuses($character), // delta dari equipment saja
            'equipment' => collect($this->equipment->equippedBySlot($character))
                ->map(fn (?Item $i) => $i ? ['id' => $i->id, 'name' => $i->name, 'slug' => $i->slug] : null)
                ->all(),
            'skills' => $character->skills->map(fn (Skill $s) => [
                'id' => $s->id, 'name' => $s->name, 'power' => (int) $s->power, 'type' => $s->type,
            ])->values()->all(),
            'spells' => $character->spells->map(fn (Spell $s) => [
                'id' => $s->id, 'name' => $s->name, 'power' => (int) $s->power, 'element' => $s->element,
            ])->values()->all(),
            'items' => $character->items->map(fn (Item $i) => [
                'id' => $i->id,
                'slug' => $i->slug,
                'name' => $i->name,
                'type' => $i->type,
                'image' => $i->image,
                'value' => (int) $i->value,
                'quantity' => $i->pivot->quantity,
                'equipped' => (bool) $i->pivot->equipped,
                'heal' => $this->healAmount($i),         // > 0 → potion HP
                'restore_sp' => $this->spRestoreAmount($i), // > 0 → potion SP
                'restore_mp' => $this->mpRestoreAmount($i), // > 0 → potion MP
                'slot' => $this->equipment->slotFor($i),    // weapon/armor/accessory atau null
                'equip_bonuses' => $this->equipment->itemBonuses($i),
                'req_level' => $this->equipment->reqLevel($i),
                'book' => $this->bookInfo($i, $character), // null kecuali buku pengajar skill/sihir
            ])->values()->all(),
        ];
    }

    /** Metadata buku untuk klien (skill/sihir yang diajarkan + status), null bila bukan buku. */
    private function bookInfo(Item $item, Character $character): ?array
    {
        $t = $this->learning->teaches($item);
        if (! $t) {
            return null;
        }

        return [
            'kind' => $t['kind'],          // 'skill' | 'spell'
            'teaches' => $t['name'],       // nama ability
            'req_level' => $t['level'],    // level minimal untuk belajar
            'known' => $this->learning->alreadyKnows($character, $item),
        ];
    }

    private function hasItem(Character $character, string $slug): bool
    {
        return $character->items()->where('slug', $slug)->exists();
    }

    /** @param array<string, mixed>|null $req */
    private function lockHint(?array $req): ?string
    {
        if (empty($req)) {
            return null;
        }
        $parts = [];
        if (isset($req['min_level'])) {
            $parts[] = "Requires level {$req['min_level']}";
        }
        if (isset($req['min_gold'])) {
            $parts[] = "Requires {$req['min_gold']} gold";
        }
        if (isset($req['has_item'])) {
            $parts[] = "Requires {$req['has_item']}";
        }
        if (isset($req['flag']) && empty($parts)) {
            $parts[] = 'Locked';
        }

        return $parts ? implode(', ', $parts) : null;
    }
}
