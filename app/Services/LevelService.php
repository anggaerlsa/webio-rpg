<?php

namespace App\Services;

use App\Models\Character;

class LevelService
{
    /**
     * XP required to advance FROM the given level to the next one.
     * Tunable curve: floor(100 * level^1.5).
     */
    public function xpForLevel(int $level): int
    {
        return (int) floor(100 * ($level ** 1.5));
    }

    /**
     * Grant XP and apply any level-ups in-memory. The CALLER is responsible
     * for persisting the character (services wrap this in a transaction).
     *
     * @return array{leveled_up: bool, levels_gained: int, new_level: int}
     */
    public function grantXp(Character $character, int $amount): array
    {
        if ($amount <= 0) {
            return ['leveled_up' => false, 'levels_gained' => 0, 'new_level' => $character->level];
        }

        $character->xp += $amount;
        $levelsGained = 0;

        // Loop in case a single reward crosses multiple level thresholds.
        while ($character->xp >= $this->xpForLevel($character->level)) {
            $character->xp -= $this->xpForLevel($character->level);
            $character->level += 1;
            $levelsGained++;

            // Stat growth on level up.
            $character->max_hp += 10;
            $character->max_sp += 4;
            $character->max_mp += 4;
            $character->attack += 2;
            $character->defense += 1;

            // Atribut RPG naik tiap level (semua +1) — progresi terasa.
            $character->strength += 1;
            $character->agility += 1;
            $character->dexterity += 1;
            $character->intelligence += 1;
            $character->vitality += 1;
            $character->luck += 1;

            // Pulihkan penuh saat naik level.
            $character->hp = $character->max_hp;
            $character->sp = $character->max_sp;
            $character->mp = $character->max_mp;
            $character->is_alive = true;
        }

        return [
            'leveled_up' => $levelsGained > 0,
            'levels_gained' => $levelsGained,
            'new_level' => $character->level,
        ];
    }
}
