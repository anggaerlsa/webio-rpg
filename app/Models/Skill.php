<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'power' => 'integer',
            'stamina_cost' => 'integer',
            'level_req' => 'integer',
            'is_default' => 'boolean',
            'effects' => 'array',
        ];
    }

    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_skill')->withTimestamps();
    }
}
