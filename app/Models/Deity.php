<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deity extends Model
{
    protected $guarded = [];

    /**
     * Jenis berkah yang dipahami kode. Tiap jenis punya logika permainannya
     * sendiri (lihat BlessingService); admin hanya memilih dari daftar ini.
     *
     * @var array<string, array{label: string, requires_gender: bool}>
     */
    public const BLESSINGS = [
        'affection_opposite' => ['label' => 'Afeksi lawan jenis ×2 (1 jam)', 'requires_gender' => true],
    ];

    /** Akun GM yang memerankan dewa ini (boleh kosong). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
