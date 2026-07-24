<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Place extends Model
{
    protected $guarded = [];

    /**
     * Kategori tempat yang tersedia (kunci tetap => label tampil).
     * Kunci dipakai logika game (mis. afiliasi guild); nama tiap tempat boleh custom.
     * Tambah kategori baru cukup dengan menambah satu baris di sini.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'adventurer_guild' => 'Guild Petualang',
        'merchant_guild' => 'Guild Merchant',
        'city_hall' => 'City Hall',
        'market' => 'Pasar',
        'blacksmith' => 'Blacksmith',
        'potion_shop' => 'Potion Shop',
        'magic_shop' => 'Toko Sihir',
        'inn' => 'Penginapan',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
