<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Npc;
use App\Models\Place;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Lottery;

/**
 * Afeksi pemain ↔ NPC. Naik/turun lewat pilihan dialog (sekali per pilihan
 * per hari, dibatasi ±DAILY_CAP per hari), menentukan level (Dibenci …
 * Sahabat) yang membuka dialog, mengubah harga, dan bisa membuat pedagang
 * menolak melayani.
 */
class AffectionService
{
    /** Kenaikan untuk obrolan pertama dengan seorang NPC dalam sehari. */
    public const FIRST_TALK = 1;

    /** Kenaikan dari "Minta maaf" (sekali per hari, hanya di level Tak Disukai). */
    public const APOLOGY = 2;

    /** Batas total kenaikan (dan penurunan) per NPC per hari. */
    public const DAILY_CAP = 5;

    /** Reputasi kerajaan menyumbang afeksi ke semua NPC-nya: reputasi ÷ 4, maks +25. */
    public const REPUTATION_DIVISOR = 4;

    public const REPUTATION_BONUS_MAX = 25;

    /** Reputasi dari setiap misi guild yang selesai, di kerajaan tempat karakter berada. */
    public const REPUTATION_PER_MISSION = 5;

    public const REPUTATION_MAX = 100;

    /** Perubahan harga (persen) di toko/penginapan menurut sikap pemiliknya. */
    public const PRICE_MODIFIERS = ['hated' => 10, 'disliked' => 10, 'friend' => -5, 'close' => -10, 'best' => -15];

    /** Peluang pedagang yang membencimu menolak melayani, per kunjungan. */
    public const REFUSAL_ODDS = [1, 2];

    /** Afeksi pribadi (tanpa bonus reputasi). */
    public function personal(Character $character, Npc $npc): int
    {
        return (int) DB::table('character_npc')
            ->where('character_id', $character->id)
            ->where('npc_id', $npc->id)
            ->value('affection');
    }

    /** Reputasi pemain di sebuah kerajaan (0…100). */
    public function reputation(Character $character, ?int $countryId): int
    {
        return (int) DB::table('character_reputations')
            ->where('character_id', $character->id)
            ->where('country_id', $countryId)
            ->value('reputation');
    }

    /** Tambah reputasi di kerajaan tempat karakter sedang berada (maks REPUTATION_MAX). */
    public function addReputation(Character $character, int $amount): void
    {
        $countryId = $character->city?->province?->country_id;
        if (! $countryId) {
            return;
        }

        $key = ['character_id' => $character->id, 'country_id' => $countryId];
        DB::table('character_reputations')->updateOrInsert($key, [
            'reputation' => min(self::REPUTATION_MAX, $this->reputation($character, $countryId) + $amount),
            'updated_at' => now(),
        ]);
    }

    /** Afeksi efektif: afeksi pribadi + bonus reputasi kerajaan tempat NPC tinggal. */
    public function effective(Character $character, Npc $npc): int
    {
        $countryId = $npc->place?->city?->province?->country_id;
        $bonus = min(self::REPUTATION_BONUS_MAX, intdiv($this->reputation($character, $countryId), self::REPUTATION_DIVISOR));

        return max(-100, min(100, $this->personal($character, $npc) + $bonus));
    }

    /** Kunci level (Npc::LEVELS) dari afeksi efektif. */
    public function level(Character $character, Npc $npc): string
    {
        $value = $this->effective($character, $npc);
        foreach (array_reverse(Npc::LEVELS, true) as $key => $level) {
            if ($value >= $level['min']) {
                return $key;
            }
        }

        return 'hated';
    }

    /** Pemilik tempat: NPC pertama di sana (penentu harga & penolakan). */
    private function owner(Place $place): ?Npc
    {
        return $place->npcs->first();
    }

    /** Perubahan harga (persen) di tempat ini untuk pemain ini; 0 bila tak ada pemilik. */
    public function priceModifier(Character $character, Place $place): int
    {
        $owner = $this->owner($place);

        return $owner ? (self::PRICE_MODIFIERS[$this->level($character, $owner)] ?? 0) : 0;
    }

    /** Harga setelah sikap pemilik diperhitungkan (minimal 1). */
    public function adjust(int $price, int $percent): int
    {
        return max(1, (int) round($price * (100 + $percent) / 100));
    }

    /**
     * Pedagang yang membencimu kadang menolak melayani. Hanya NPC yang punya
     * pesan menolak (pedagang/penginapan); guild & istana tak pernah menolak.
     */
    public function refusal(Character $character, Place $place): ?string
    {
        $owner = $this->owner($place);
        if (! $owner?->refusal_message || $this->level($character, $owner) !== 'hated') {
            return null;
        }

        return Lottery::odds(...self::REFUSAL_ODDS)->choose() ? $owner->refusal_message : null;
    }

    /** Sapaan menurut sikap NPC: ketus (Tak Disukai/Dibenci), hangat (Teman ke atas), selain itu netral. */
    public function greeting(Npc $npc, string $level): string
    {
        return match (true) {
            in_array($level, ['disliked', 'hated'], true) => $npc->greeting_cold ?? $npc->greeting,
            Npc::reaches($level, 'friend') => $npc->greeting_warm ?? $npc->greeting,
            default => $npc->greeting,
        };
    }

    /**
     * Pemain memilih sebuah pilihan dialog.
     *
     * @return array{reply: string, delta: int}
     */
    public function talk(Character $character, Npc $npc, int|string $choice): array
    {
        $level = $this->level($character, $npc);

        if ($choice === 'apology') {
            // Hanya NPC yang "Tak Disukai" mau menerima maaf; yang membenci sudah menutup hati.
            abort_unless($level === 'disliked', 422, 'Tak ada yang perlu dimaafkan saat ini.');
            $reply = $npc->apology_reply ?? '...';
            $effects = ['apology' => self::APOLOGY];
        } else {
            abort_if(in_array($level, ['disliked', 'hated'], true), 422, "{$npc->name} tak mau mengobrol denganmu.");
            $picked = $npc->choices[$choice] ?? null;
            abort_unless($picked, 404, 'Pilihan tidak ada.');
            abort_unless(Npc::reaches($level, $picked['min_level'] ?? null), 403, 'Pilihan ini masih terkunci.');
            $reply = $picked['reply'];
            $effects = ['first' => self::FIRST_TALK, "choice:{$choice}" => (int) $picked['affection']];
        }

        $bond = $this->bond($character, $npc);
        $delta = 0;
        foreach ($effects as $key => $effect) {
            if (in_array($key, $bond->used_today, true)) {
                continue;
            }
            $bond->used_today[] = $key;
            if ($effect > 0) {
                $effect = min($effect, self::DAILY_CAP - $bond->gained_today);
                $bond->gained_today += $effect;
            } elseif ($effect < 0) {
                $effect = -min(-$effect, self::DAILY_CAP - $bond->lost_today);
                $bond->lost_today -= $effect;
            }
            $delta += $effect;
        }
        $bond->affection = max(-100, min(100, $bond->affection + $delta));
        $this->save($bond);

        return ['reply' => $reply, 'delta' => $delta];
    }

    /** Catatan hubungan hari ini; catatan harian direset saat hari berganti. */
    private function bond(Character $character, Npc $npc): object
    {
        $bond = DB::table('character_npc')->where('character_id', $character->id)->where('npc_id', $npc->id)->first()
            ?? (object) ['id' => null, 'character_id' => $character->id, 'npc_id' => $npc->id, 'affection' => 0, 'day' => null];

        $today = now()->toDateString();
        if ($bond->day !== $today) {
            $bond->day = $today;
            $bond->gained_today = 0;
            $bond->lost_today = 0;
            $bond->used_today = [];
        } else {
            $bond->used_today = json_decode($bond->used_today ?? '[]', true);
        }

        return $bond;
    }

    private function save(object $bond): void
    {
        $values = [
            'affection' => $bond->affection,
            'day' => $bond->day,
            'gained_today' => $bond->gained_today,
            'lost_today' => $bond->lost_today,
            'used_today' => json_encode($bond->used_today),
            'updated_at' => now(),
        ];
        DB::table('character_npc')->updateOrInsert(['character_id' => $bond->character_id, 'npc_id' => $bond->npc_id], $values);
    }
}
