<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $guarded = [];

    /** Tipe permukiman: kota biasa atau benteng. */
    public const TYPES = ['city' => 'Kota', 'fortress' => 'Benteng'];

    protected $casts = ['map_x' => 'float', 'map_y' => 'float'];

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
