<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Npc extends Model
{
    protected $guarded = [];

    /** @var array<string, string> */
    public const GENDERS = ['male' => 'Pria', 'female' => 'Wanita'];

    protected $casts = ['lines' => 'array'];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
