<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Deity;
use Illuminate\Support\Carbon;

/**
 * Berkah dari kuil: berdoa ke dewa pemberi berkah memberi satu berkah aktif
 * selama DURATION_MINUTES; doa berikutnya (ke dewa mana pun) baru bisa
 * setelah COOLDOWN_HOURS.
 */
class BlessingService
{
    public const DURATION_MINUTES = 60;

    public const COOLDOWN_HOURS = 24;

    /** Berkah yang sedang aktif (null bila tak ada atau sudah habis). */
    public function active(Character $character): ?string
    {
        return $character->blessing && $character->blessing_until?->isFuture() ? $character->blessing : null;
    }

    /** Kapan karakter boleh berdoa lagi (null = sekarang boleh). */
    public function cooldownUntil(Character $character): ?Carbon
    {
        $next = $character->last_prayed_at?->copy()->addHours(self::COOLDOWN_HOURS);

        return $next && $next->isFuture() ? $next : null;
    }

    /** Berdoa ke seorang dewa. Ditolak (422) tanpa memakai cooldown bila tak memenuhi syarat. */
    public function pray(Character $character, Deity $deity): void
    {
        abort_unless($deity->blessing && isset(Deity::BLESSINGS[$deity->blessing]), 422, "{$deity->name} tidak menurunkan berkah.");
        abort_if(Deity::BLESSINGS[$deity->blessing]['requires_gender'] && ! $character->gender, 422,
            "{$deity->name} menatapmu lembut, tapi berkahnya belum bisa menyentuhmu. (Karaktermu belum memiliki jenis kelamin.)");
        if ($next = $this->cooldownUntil($character)) {
            abort(422, 'Para dewa hanya mendengar satu doa setiap 24 jam. Kau bisa berdoa lagi '.$next->diffForHumans().'.');
        }

        $character->blessing = $deity->blessing;
        $character->blessing_deity_id = $deity->id;
        $character->blessing_until = now()->addMinutes(self::DURATION_MINUTES);
        $character->last_prayed_at = now();
        $character->save();
    }
}
