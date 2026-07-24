<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Quest;
use App\Models\RankRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sistem rank & misi guild. Rank naik (F→E→D→C→B→A→S) saat karakter
 * menyelesaikan sejumlah misi (ambang `rank_rules`, diatur Panel Dewa).
 * Misi = Quest dengan `affiliation` (guild) + `required_rank`. Satu misi aktif
 * per waktu: diambil di guild, hilang dari papan setelah selesai.
 */
class RankService
{
    /** Tangga rank dari terendah ke tertinggi. */
    public const LADDER = ['F', 'E', 'D', 'C', 'B', 'A', 'S'];

    /** Kategori place guild → affiliation misi yang ditawarkannya. */
    public const GUILD_AFFILIATION = [
        'adventurer_guild' => 'adventurer',
        'merchant_guild' => 'merchant',
    ];

    public function __construct(private StoryEngine $story) {}

    /** Posisi rank di tangga (0 = F). -1 bila tidak dikenal. */
    public function rankIndex(?string $rank): int
    {
        return array_search($rank ?? 'F', self::LADDER, true) ?: 0;
    }

    /** Rank berikutnya, atau null bila sudah tertinggi (S). */
    public function nextRank(?string $rank): ?string
    {
        $i = $this->rankIndex($rank);

        return self::LADDER[$i + 1] ?? null;
    }

    /** Misi yang dibutuhkan untuk naik DARI rank ini, atau null bila rank tertinggi. */
    public function missionsRequired(?string $rank): ?int
    {
        if ($this->nextRank($rank) === null) {
            return null;
        }

        return RankRule::where('rank', $rank ?? 'F')->value('missions_required');
    }

    /** Ringkasan progres rank untuk ditampilkan. */
    public function progress(Character $character): array
    {
        $rank = $character->rank ?? 'F';

        return [
            'current' => $rank,
            'next' => $this->nextRank($rank),
            'completed' => (int) $character->rank_progress,
            'required' => $this->missionsRequired($rank),
        ];
    }

    public function hasCompleted(Character $character, Quest $quest): bool
    {
        return $character->completedQuests()->whereKey($quest->id)->exists();
    }

    public function isActive(Character $character, Quest $quest): bool
    {
        return (int) $character->active_quest_id === (int) $quest->id;
    }

    /** Apakah affiliation karakter cocok dengan guild penyelenggara misi. */
    public function affiliationMatches(Character $character, Quest $quest): bool
    {
        return $quest->affiliation !== null && $quest->affiliation === $character->affiliation;
    }

    /**
     * Alasan kenapa karakter TIDAK bisa mengambil misi ini, atau null bila bisa.
     */
    public function acceptBlockReason(Character $character, Quest $quest): ?string
    {
        if (! $quest->is_published) {
            return 'Misi ini belum tersedia.';
        }
        if (! $character->affiliation) {
            return 'Bergabunglah dengan guild terlebih dahulu.';
        }
        if (! $this->affiliationMatches($character, $quest)) {
            return 'Misi ini bukan untuk guildmu.';
        }
        if ($this->rankIndex($character->rank) < $this->rankIndex($quest->required_rank ?? 'F')) {
            return "Misi ini butuh Rank {$quest->required_rank}.";
        }
        if ($character->level < $quest->min_level) {
            return "Level minimal {$quest->min_level}.";
        }
        if ($this->hasCompleted($character, $quest)) {
            return 'Misi ini sudah kamu selesaikan.';
        }
        if ($character->active_quest_id && ! $this->isActive($character, $quest)) {
            return 'Selesaikan dulu misimu yang sedang berjalan.';
        }

        return null;
    }

    public function canAccept(Character $character, Quest $quest): bool
    {
        return $this->acceptBlockReason($character, $quest) === null;
    }

    /** Ambil misi: jadikan misi aktif & mulai quest-nya. */
    public function accept(Character $character, Quest $quest): void
    {
        $reason = $this->acceptBlockReason($character, $quest);
        abort_unless($reason === null, 422, $reason);

        DB::transaction(function () use ($character, $quest) {
            $character->active_quest_id = $quest->id;
            $character->save();
            $this->story->startQuest($character, $quest);
        });
    }

    /**
     * Selesaikan misi aktif (dipanggil saat mencapai ending sukses). Mencatat
     * penyelesaian, menambah progres rank, menaikkan rank bila ambang tercapai,
     * lalu mengosongkan misi aktif. Idempoten — hanya menghitung sekali.
     *
     * @return array{recorded: bool, rank_up: bool, new_rank: ?string}
     */
    public function complete(Character $character, Quest $quest): array
    {
        // Hanya misi aktif yang belum tercatat yang dihitung.
        if (! $this->isActive($character, $quest) || $this->hasCompleted($character, $quest)) {
            return ['recorded' => false, 'rank_up' => false, 'new_rank' => null];
        }

        return DB::transaction(function () use ($character, $quest) {
            $character->completedQuests()->syncWithoutDetaching([
                $quest->id => ['completed_at' => now()],
            ]);

            $character->rank_progress = (int) $character->rank_progress + 1;
            $character->active_quest_id = null;

            $rankUp = false;
            $required = $this->missionsRequired($character->rank);
            if ($required !== null && $character->rank_progress >= $required) {
                $character->rank = $this->nextRank($character->rank);
                $character->rank_progress = 0;
                $rankUp = true;
            }

            $character->save();

            return ['recorded' => true, 'rank_up' => $rankUp, 'new_rank' => $rankUp ? $character->rank : null];
        });
    }

    /**
     * Misi yang tersedia di papan sebuah guild untuk karakter ini: terbit,
     * affiliation cocok, rank cukup, belum selesai. Misi aktif disertakan agar
     * bisa "Lanjutkan". Diurut required_rank lalu min_level.
     *
     * @return Collection<int, Quest>
     */
    public function availableMissions(Character $character, string $affiliation): Collection
    {
        $completedIds = $character->completedQuests()->pluck('quests.id')->all();
        $maxRank = $this->rankIndex($character->rank);

        return Quest::where('is_published', true)
            ->where('affiliation', $affiliation)
            ->whereNotIn('id', $completedIds)
            ->orderBy('min_level')
            ->orderBy('order')
            ->get()
            ->filter(fn (Quest $q) => $this->rankIndex($q->required_rank ?? 'F') <= $maxRank)
            ->values();
    }
}
