<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CombatSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'monster_hp' => 'integer',
            'player_hp' => 'integer',
            'turn' => 'integer',
        ];
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(QuestNode::class, 'quest_node_id');
    }

    public function monster(): BelongsTo
    {
        return $this->belongsTo(Monster::class);
    }
}
