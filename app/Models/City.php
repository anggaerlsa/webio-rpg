<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $guarded = [];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function villages(): HasMany
    {
        return $this->hasMany(Village::class);
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }
}
