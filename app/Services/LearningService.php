<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use App\Models\Skill;
use App\Models\Spell;
use Illuminate\Support\Facades\DB;

/**
 * Mempelajari skill/sihir dari BUKU. Buku = item `type=book` yang field stats-nya
 * menunjuk ability yang diajarkan: `teaches_skill` atau `teaches_spell` (slug).
 * Membaca buku menambah skill/sihir ke karakter lalu memakai (menghabiskan) buku.
 * Server-authoritative.
 */
class LearningService
{
    /**
     * Ability yang diajarkan sebuah buku, atau null bila bukan buku pengajar.
     *
     * @return array{kind: 'skill'|'spell', slug: string, model: Skill|Spell, name: string, level: int}|null
     */
    public function teaches(Item $item): ?array
    {
        if ($item->type !== 'book' || ! is_array($item->stats)) {
            return null;
        }

        if (! empty($item->stats['teaches_skill'])) {
            $skill = Skill::where('slug', $item->stats['teaches_skill'])->first();
            if ($skill) {
                return ['kind' => 'skill', 'slug' => $skill->slug, 'model' => $skill, 'name' => $skill->name, 'level' => (int) $skill->level_req];
            }
        }
        if (! empty($item->stats['teaches_spell'])) {
            $spell = Spell::where('slug', $item->stats['teaches_spell'])->first();
            if ($spell) {
                return ['kind' => 'spell', 'slug' => $spell->slug, 'model' => $spell, 'name' => $spell->name, 'level' => (int) $spell->min_level];
            }
        }

        return null;
    }

    public function isBook(Item $item): bool
    {
        return $this->teaches($item) !== null;
    }

    /** Apakah karakter sudah menguasai ability yang diajarkan buku ini. */
    public function alreadyKnows(Character $character, Item $item): bool
    {
        $t = $this->teaches($item);
        if (! $t) {
            return false;
        }

        return $t['kind'] === 'skill'
            ? $character->skills()->whereKey($t['model']->id)->exists()
            : $character->spells()->whereKey($t['model']->id)->exists();
    }

    /**
     * Baca buku → pelajari ability-nya. Ditolak bila bukan buku, level kurang,
     * atau sudah dikuasai. Menghabiskan satu buku. Mengembalikan info ability.
     *
     * @return array{kind: string, name: string}
     */
    public function learn(Character $character, Item $item): array
    {
        $t = $this->teaches($item);
        abort_unless($t, 422, 'Item itu bukan buku yang bisa dipelajari.');

        $owned = $character->items()->where('item_id', $item->id)->first();
        abort_unless($owned && $owned->pivot->quantity > 0, 422, 'Kamu tidak memiliki buku itu.');
        abort_if($this->alreadyKnows($character, $item), 422, "Kamu sudah menguasai {$t['name']}.");
        abort_if($character->level < $t['level'], 422, "Butuh level {$t['level']} untuk mempelajari {$t['name']}.");

        return DB::transaction(function () use ($character, $item, $t) {
            if ($t['kind'] === 'skill') {
                $character->skills()->syncWithoutDetaching([$t['model']->id]);
            } else {
                $character->spells()->syncWithoutDetaching([$t['model']->id]);
            }

            app(StoryEngine::class)->takeItem($character, $item->slug, 1);

            return ['kind' => $t['kind'], 'name' => $t['name']];
        });
    }
}
