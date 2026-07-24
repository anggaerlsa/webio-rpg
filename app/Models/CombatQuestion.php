<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CombatQuestion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_index' => 'integer',
            'player_damage_on_wrong' => 'integer',
            'monster_damage_on_correct' => 'integer',
            'difficulty' => 'integer',
            'order' => 'integer',
        ];
    }

    public function monster(): BelongsTo
    {
        return $this->belongsTo(Monster::class);
    }

    /**
     * Public-safe representation for the client (NEVER includes the answer key).
     *
     * @return array<string, mixed>
     */
    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'prompt' => $this->prompt,
            'options' => $this->options,
        ];
    }
}
