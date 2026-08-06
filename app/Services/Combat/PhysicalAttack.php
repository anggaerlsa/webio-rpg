<?php

namespace App\Services\Combat;

use App\Models\Character;
use App\Models\Monster;

/**
 * Jalur fisik: skill, memakai SP, diskala STR, diperkuat `attack` dari senjata,
 * ditahan `defense` + VIT.
 */
class PhysicalAttack extends AttackModule
{
    /** +2% damage fisik per poin STR di atas baseline (tunable). */
    private const DAMAGE_PER_STR = 0.02;

    public function resource(): string
    {
        return 'sp';
    }

    public function label(): string
    {
        return 'fisik';
    }

    public function playerDamage(Character $character, int $power): int
    {
        $eff = $this->equipment->effective($character);
        $raw = (int) floor($power * (1 + $this->bonusStat($eff['strength']) * self::DAMAGE_PER_STR));

        return $raw + $this->gearBonus($eff['attack'], (int) $character->attack);
    }

    public function monsterDefense(Monster $monster): int
    {
        return (int) $monster->defense;
    }

    public function monsterPower(Monster $monster): int
    {
        return (int) $monster->attack;
    }

    public function playerDefense(Character $character): int
    {
        $eff = $this->equipment->effective($character);

        return $eff['defense'] + $this->bonusStat($eff['vitality']);
    }
}
