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
}
