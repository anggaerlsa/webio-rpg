<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Npc extends Model
{
    protected $guarded = [];

    /** @var array<string, string> */
    public const GENDERS = ['male' => 'Pria', 'female' => 'Wanita'];

    /**
     * Level afeksi pemain ↔ NPC, urut dari terendah, dengan ambang minimumnya.
     *
     * @var array<string, array{label: string, min: int}>
     */
    public const LEVELS = [
        'hated' => ['label' => 'Dibenci', 'min' => -100],
        'disliked' => ['label' => 'Tak Disukai', 'min' => -49],
        'stranger' => ['label' => 'Asing', 'min' => -19],
        'acquaintance' => ['label' => 'Kenalan', 'min' => 20],
        'friend' => ['label' => 'Teman', 'min' => 40],
        'close' => ['label' => 'Akrab', 'min' => 60],
        'best' => ['label' => 'Sahabat', 'min' => 80],
    ];

    /** Efek afeksi yang boleh dipasang pada sebuah pilihan dialog. */
    public const CHOICE_EFFECTS = [-2, 0, 2];

    protected $casts = ['choices' => 'array'];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /** Apakah level `$current` sudah mencapai level `$required`. */
    public static function reaches(string $current, ?string $required): bool
    {
        if ($required === null) {
            return true;
        }
        $order = array_keys(self::LEVELS);

        return array_search($current, $order, true) >= array_search($required, $order, true);
    }
}
