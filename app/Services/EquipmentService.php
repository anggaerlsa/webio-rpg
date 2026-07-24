<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

/**
 * Sistem perlengkapan (equip). Senjata/zirah/aksesori yang dipakai menambah
 * stat karakter. Stat DASAR (kolom) tidak diubah — bonus dihitung saat baca
 * (lihat bonuses()/effective()), jadi melepas item selalu mengembalikan stat
 * persis. Status terpasang disimpan di pivot character_items.equipped.
 */
class EquipmentService
{
    /** Slot equip (kunci = tipe item) → label. Satu item per slot. */
    public const SLOTS = [
        'weapon' => 'Senjata',
        'armor' => 'Zirah',
        'accessory' => 'Aksesori',
    ];

    /** Stat yang bisa ditambah equipment (kunci stats item). */
    public const STAT_KEYS = [
        'attack', 'defense',
        'strength', 'agility', 'dexterity', 'intelligence', 'vitality', 'luck',
    ];

    /** Slot yang ditempati sebuah item (null bila tak bisa dipakai). */
    public function slotFor(Item $item): ?string
    {
        return array_key_exists($item->type, self::SLOTS) ? $item->type : null;
    }

    public function isEquippable(Item $item): bool
    {
        return $this->slotFor($item) !== null;
    }

    /** Level minimum untuk memakai item (stats.req_level, default 0). */
    public function reqLevel(Item $item): int
    {
        return max(0, (int) (($item->stats['req_level'] ?? 0)));
    }

    /** Bonus stat sebuah item bila dipakai (hanya kunci STAT_KEYS yang > 0). */
    public function itemBonuses(Item $item): array
    {
        $out = [];
        foreach (self::STAT_KEYS as $key) {
            $v = (int) (($item->stats[$key] ?? 0));
            if ($v !== 0) {
                $out[$key] = $v;
            }
        }

        return $out;
    }

    /** Item yang sedang dipakai karakter (pivot equipped = true). */
    public function equippedItems(Character $character)
    {
        $character->loadMissing('items');

        return $character->items->filter(fn (Item $i) => (bool) $i->pivot->equipped);
    }

    /** Peta slot → item terpasang (atau null). */
    public function equippedBySlot(Character $character): array
    {
        $map = array_fill_keys(array_keys(self::SLOTS), null);
        foreach ($this->equippedItems($character) as $item) {
            $slot = $this->slotFor($item);
            if ($slot) {
                $map[$slot] = $item;
            }
        }

        return $map;
    }

    /** Total bonus stat dari semua item terpasang (semua STAT_KEYS, default 0). */
    public function bonuses(Character $character): array
    {
        $totals = array_fill_keys(self::STAT_KEYS, 0);
        foreach ($this->equippedItems($character) as $item) {
            foreach ($this->itemBonuses($item) as $key => $v) {
                $totals[$key] += $v;
            }
        }

        return $totals;
    }

    /** Stat efektif = stat dasar + bonus equipment. */
    public function effective(Character $character): array
    {
        $b = $this->bonuses($character);

        return [
            'attack' => (int) $character->attack + $b['attack'],
            'defense' => (int) $character->defense + $b['defense'],
            'strength' => (int) $character->strength + $b['strength'],
            'agility' => (int) $character->agility + $b['agility'],
            'dexterity' => (int) $character->dexterity + $b['dexterity'],
            'intelligence' => (int) $character->intelligence + $b['intelligence'],
            'vitality' => (int) $character->vitality + $b['vitality'],
            'luck' => (int) $character->luck + $b['luck'],
        ];
    }

    /**
     * Pakai sebuah item. Melepas dulu item lain di slot yang sama (satu item per
     * slot). Server-authoritative; divalidasi kepemilikan, kelayakan & level.
     */
    public function equip(Character $character, Item $item): void
    {
        $slot = $this->slotFor($item);
        abort_unless($slot, 422, 'Item itu tidak bisa dipakai.');

        $owned = $character->items()->where('item_id', $item->id)->first();
        abort_unless($owned && $owned->pivot->quantity > 0, 422, 'Kamu tidak memiliki item itu.');

        $req = $this->reqLevel($item);
        abort_if($character->level < $req, 422, "Butuh level {$req} untuk memakai {$item->name}.");

        DB::transaction(function () use ($character, $item, $slot) {
            // Lepas item lain di slot yang sama.
            foreach ($this->equippedBySlot($character) as $s => $equipped) {
                if ($s === $slot && $equipped && (int) $equipped->id !== (int) $item->id) {
                    $character->items()->updateExistingPivot($equipped->id, ['equipped' => false]);
                }
            }
            $character->items()->updateExistingPivot($item->id, ['equipped' => true]);
            $character->load('items');
        });
    }

    /** Lepas sebuah item yang sedang dipakai. */
    public function unequip(Character $character, Item $item): void
    {
        $owned = $character->items()->where('item_id', $item->id)->first();
        abort_unless($owned, 422, 'Kamu tidak memiliki item itu.');

        $character->items()->updateExistingPivot($item->id, ['equipped' => false]);
        $character->load('items');
    }
}
