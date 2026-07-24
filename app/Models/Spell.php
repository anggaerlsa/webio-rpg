<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Spell extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'mana_cost' => 'integer',
            'power' => 'integer',
            'min_level' => 'integer',
            'is_default' => 'boolean',
            'effects' => 'array',
        ];
    }

    public function characters(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_spell')->withTimestamps();
    }
}
