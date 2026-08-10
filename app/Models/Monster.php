<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Monster extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'max_hp' => 'integer',
            'attack' => 'integer',
            'defense' => 'integer',
            'xp_reward' => 'integer',
            'gold_reward' => 'integer',
            'loot' => 'array',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(CombatQuestion::class)->orderBy('order');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(QuestNode::class);
    }

    /**
     * Stat monster yang diturunkan dari level. Level 1 sengaja dipas ke
     * keseimbangan `tikus-raksasa` yang sudah terbukti. Field eksplisit di
     * konten selalu menimpa hasil rumus ini (lihat ImportGameContent::upsertMonster).
     *
     * ponytail: rumus HP menganggap kekuatan serang pemain naik seiring level,
     * padahal damage berasal dari `power` skill — pemain level 5 yang cuma
     * menguasai Pukul (power 1) butuh 11 giliran. Kurva skill = milestone M2.
     *
     * @return array<string, int>
     */
    public static function statsForLevel(int $level): array
    {
        $level = max(1, $level);

        return [
            'max_hp' => 3 + 2 * ($level - 1),
            'attack' => $level,
            'defense' => intdiv($level - 1, 2),
            'magic_attack' => 0,
            'magic_defense' => intdiv($level - 1, 2),
            'xp_reward' => 20 + 10 * $level,
            'gold_reward' => 5 + 5 * $level,
        ];
    }
}
