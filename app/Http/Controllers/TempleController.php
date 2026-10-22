<?php

namespace App\Http\Controllers;

use App\Models\Deity;
use App\Services\BlessingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Kuil: daftar dewa-dewi dan tempat pemain meminta berkah. */
class TempleController extends Controller
{
    public function __construct(private BlessingService $blessings) {}

    /** Halaman kuil: semua dewa (GM & pemberi berkah) + status berkah pemain. */
    public function index(Request $request): Response|RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }
        $active = $this->blessings->active($character);

        return Inertia::render('Temple/Index', [
            'deities' => Deity::orderBy('name')->get()->map(fn (Deity $d) => [
                'slug' => $d->slug,
                'name' => $d->name,
                'title' => $d->title,
                'domain' => $d->domain,
                'description' => $d->description,
                'image' => $d->image,
                'is_gm' => $d->user_id !== null,
                'blessing' => $d->blessing ? Deity::BLESSINGS[$d->blessing]['label'] ?? null : null,
                'needs_gender' => $d->blessing ? (Deity::BLESSINGS[$d->blessing]['requires_gender'] ?? false) : false,
            ])->all(),
            'active_blessing' => $active ? [
                'deity' => Deity::find($character->blessing_deity_id)?->name,
                'label' => Deity::BLESSINGS[$active]['label'] ?? $active,
                'until' => $character->blessing_until->toIso8601String(),
            ] : null,
            'cooldown_until' => $this->blessings->cooldownUntil($character)?->toIso8601String(),
            'has_gender' => (bool) $character->gender,
        ]);
    }

    /** Berdoa ke seorang dewa untuk meminta berkahnya. */
    public function pray(Request $request, Deity $deity): RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }

        try {
            $this->blessings->pray($character, $deity);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $deity->blessing_message ?: "{$deity->name} memberkatimu.");
    }
}
