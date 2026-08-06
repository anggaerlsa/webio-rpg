<?php

namespace App\Services\Combat;

use App\Models\Character;
use App\Models\Monster;

/**
 * Jalur sihir: spell, memakai MP, diskala INT, diperkuat `magic_attack` dari
 * perlengkapan (tongkat/jimat), ditahan `magic_defense` + INT.
 *
 * INT sengaja berperan dua sisi — menyerang dan menangkal sihir — sebagai
 * cerminan VIT di jalur fisik.
 */
class MagicalAttack extends AttackModule
{
    /** +2% damage sihir per poin INT di atas baseline (tunable). */
    private const DAMAGE_PER_INT = 0.02;

    public function resource(): string
    {
        return 'mp';
    }

    public function label(): string
    {
        return 'sihir';
    }

    public function playerDamage(Character $character, int $power): int
    {
        $eff = $this->equipment->effective($character);
        $raw = (int) floor($power * (1 + $this->bonusStat($eff['intelligence']) * self::DAMAGE_PER_INT));

        return $raw + $this->gearBonus($eff['magic_attack'], (int) $character->magic_attack);
    }

    public function monsterDefense(Monster $monster): int
    {
        return (int) $monster->magic_defense;
    }

    public function monsterPower(Monster $monster): int
    {
        return (int) $monster->magic_attack;
    }

    public function playerDefense(Character $character): int
    {
        $eff = $this->equipment->effective($character);

        return $eff['magic_defense'] + $this->bonusStat($eff['intelligence']);
    }
}
