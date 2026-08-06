<?php

namespace App\Services\Combat;

use App\Models\Character;
use App\Models\Monster;
use App\Services\EquipmentService;

/**
 * Satu jalur serangan (fisik atau sihir). Tiap jalur menentukan sendiri:
 * resource yang dipakai (SP/MP), atribut yang menskala damage, stat serangan &
 * pertahanan mana yang dibaca — baik milik pemain maupun monster.
 *
 * CombatService hanya memilih modul lalu memakainya; rumusnya tinggal di sini
 * supaya menambah jalur baru (mis. serangan suci) tidak menyentuh mesin combat.
 */
abstract class AttackModule
{
    public function __construct(protected EquipmentService $equipment) {}

    /** Kolom resource karakter yang dipotong tiap serangan: 'sp' atau 'mp'. */
    abstract public function resource(): string;

    /** Label singkat untuk log & UI ("fisik" / "sihir"). */
    abstract public function label(): string;

    /** Damage mentah pemain (sebelum kritikal & pertahanan monster). */
    abstract public function playerDamage(Character $character, int $power): int;

    /** Pertahanan monster terhadap jalur ini. */
    abstract public function monsterDefense(Monster $monster): int;

    /** Kekuatan serangan monster pada jalur ini. */
    abstract public function monsterPower(Monster $monster): int;

    /** Pertahanan pemain terhadap serangan monster pada jalur ini. */
    abstract public function playerDefense(Character $character): int;

    /** Poin atribut efektif: stat 1 = baseline (efek 0); hanya poin DI ATAS 1 berpengaruh. */
    protected function bonusStat(int $value): int
    {
        return max(0, $value - 1);
    }

    /**
     * Porsi bonus perlengkapan pada sebuah stat serangan: hanya selisih di atas
     * stat dasar yang ditambahkan, supaya keseimbangan berbasis `power` terjaga.
     */
    protected function gearBonus(int $effective, int $base): int
    {
        return max(0, $effective - $base);
    }
}
