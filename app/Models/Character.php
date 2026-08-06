<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Character extends Model
{
    // Writes are server-controlled (services/seeders), never mass-assigned from raw request input.
    protected $guarded = [];

    // In-memory defaults for the resource pools so freshly-created models (before a
    // DB refresh) already carry them — mirrors the column defaults in the migration.
    protected $attributes = [
        'sp' => 30,
        'max_sp' => 30,
        'mp' => 30,
        'max_mp' => 30,
        'magic_attack' => 10,
        'magic_defense' => 5,
        'strength' => 1,
        'agility' => 1,
        'dexterity' => 1,
        'intelligence' => 1,
        'vitality' => 1,
        'luck' => 1,
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'xp' => 'integer',
            'hp' => 'integer',
            'max_hp' => 'integer',
            'sp' => 'integer',
            'max_sp' => 'integer',
            'mp' => 'integer',
            'max_mp' => 'integer',
            'strength' => 'integer',
            'agility' => 'integer',
            'dexterity' => 'integer',
            'intelligence' => 'integer',
            'vitality' => 'integer',
            'luck' => 'integer',
            'attack' => 'integer',
            'defense' => 'integer',
            'magic_attack' => 'integer',
            'magic_defense' => 'integer',
            'gold' => 'integer',
            'rank_progress' => 'integer',
            'attributes' => 'array',
            'is_alive' => 'boolean',
            'birth_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Kota asal / lokasi karakter saat ini (home city). */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Kota awal tempat karakter baru "lahir": ibukota tertua di dunia
     * (kota dengan id terkecil). Null bila dunia belum punya kota.
     */
    public static function startingCity(): ?City
    {
        return City::orderBy('id')->first();
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'character_items')
            ->withPivot(['quantity', 'equipped'])
            ->withTimestamps();
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'character_skill')->withTimestamps();
    }

    public function spells(): BelongsToMany
    {
        return $this->belongsToMany(Spell::class, 'character_spell')->withTimestamps();
    }

    public function saves(): HasMany
    {
        return $this->hasMany(GameSave::class);
    }

    /** Misi yang sedang diambil/dikerjakan (satu per waktu). */
    public function activeQuest(): BelongsTo
    {
        return $this->belongsTo(Quest::class, 'active_quest_id');
    }

    /** Misi yang sudah diselesaikan (pivot character_quest). */
    public function completedQuests(): BelongsToMany
    {
        return $this->belongsToMany(Quest::class)->withPivot('completed_at')->withTimestamps();
    }

    public function activeCombat(): HasOne
    {
        return $this->hasOne(CombatSession::class)->where('status', 'active');
    }

    /** Grant every "default" skill/spell (e.g. Pukul) to this character. */
    public function grantDefaultAbilities(): void
    {
        $this->skills()->syncWithoutDetaching(Skill::where('is_default', true)->pluck('id'));
        $this->spells()->syncWithoutDetaching(Spell::where('is_default', true)->pluck('id'));
    }
}
