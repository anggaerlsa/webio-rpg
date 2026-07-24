<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Country extends Model
{
    protected $guarded = [];

    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }

    /** Semua kota di negara ini (lewat provinsi). */
    public function cities(): HasManyThrough
    {
        return $this->hasManyThrough(City::class, Province::class);
    }

    /**
     * Ibukota = kota pertama negara ini. Untuk permulaan, tiap negara dibuat
     * dengan tepat satu kota (lihat CountryController@store).
     */
    public function capitalCity(): ?City
    {
        return $this->cities()->orderBy('cities.id')->first();
    }
}
