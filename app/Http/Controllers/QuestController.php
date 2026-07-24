<?php

namespace App\Http\Controllers;

use App\Models\NodeChoice;
use App\Models\Quest;
use App\Services\RankService;
use App\Services\StoryEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuestController extends Controller
{
    public function __construct(
        private StoryEngine $story,
        private RankService $ranks,
    ) {}

    /** Jurnal misi: misi aktif (lanjutkan), progres rank, dan riwayat misi selesai. */
    public function index(Request $request): Response|RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }

        $active = null;
        if ($character->active_quest_id && ($quest = $character->activeQuest)) {
            $save = $character->saves()->where('slot', 1)->first();
            $node = $save ? $this->story->currentNode($save) : null;
            $active = [
                'slug' => $quest->slug,
                'title' => $quest->title,
                'description' => $quest->description,
                // Misi aktif yang berakhir kalah → tawarkan ulangi (ending sukses sudah mengosongkan misi aktif).
                'failed' => $node && $node->type === 'ending',
            ];
        }

        $completed = $character->completedQuests()->orderByPivot('completed_at', 'desc')->get()
            ->map(fn (Quest $q) => [
                'slug' => $q->slug,
                'title' => $q->title,
                'completed_at' => $q->pivot->completed_at
                    ? \Illuminate\Support\Carbon::parse($q->pivot->completed_at)->locale('id')->isoFormat('D MMM YYYY')
                    : null,
            ])->values();

        return Inertia::render('Quests/Index', [
            'character' => $this->story->characterState($character),
            'rank' => $this->ranks->progress($character),
            'active' => $active,
            'completed' => $completed,
        ]);
    }

    public function play(Request $request, Quest $quest): Response|RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }

        $save = $character->saves()->where('slot', 1)->first();
        $node = $save ? $this->story->currentNode($save) : null;
        $onThisQuest = $save && (int) $save->quest_id === (int) $quest->id;

        if ($this->ranks->isActive($character, $quest)) {
            // Misi aktif: mulai bila belum, atau ulangi bila percobaan sebelumnya berakhir kalah (ending).
            if (! $onThisQuest || ! $save->current_node_key || ($node && $node->type === 'ending')) {
                $this->story->startQuest($character, $quest);
            }
        } elseif ($onThisQuest && $node && $node->type === 'ending' && $this->ranks->hasCompleted($character, $quest)) {
            // Misi baru saja diselesaikan — biarkan layar akhir tampil (jangan diulang/dialihkan).
        } else {
            // Misi lain harus diambil di guild dulu.
            return redirect()->route('quests.index')
                ->with('error', 'Itu bukan misi aktifmu. Ambil misi di guild kota.');
        }

        return Inertia::render('Quests/Play', [
            'state' => $this->story->currentState($character),
        ]);
    }

    /** Ulangi misi aktif dari awal (mis. setelah kalah). */
    public function start(Request $request, Quest $quest): RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }
        if (! $this->ranks->isActive($character, $quest)) {
            return redirect()->route('quests.index')
                ->with('error', 'Itu bukan misi aktifmu.');
        }

        $this->story->startQuest($character, $quest);

        return redirect()->route('quests.play', $quest);
    }

    public function choose(Request $request, Quest $quest): RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }

        $data = $request->validate([
            'choice_id' => ['required', 'integer'],
        ]);

        $choice = NodeChoice::with('node')->find($data['choice_id']);
        // The choice must belong to a node within THIS quest.
        if (! $choice || ! $choice->node || (int) $choice->node->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $this->story->choose($character, $choice);

        return redirect()->route('quests.play', $quest);
    }
}
