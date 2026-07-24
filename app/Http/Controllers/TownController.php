<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Item;
use App\Models\Place;
use App\Models\Quest;
use App\Services\RankService;
use App\Services\StoryEngine;
use App\Services\TownService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Layer kota untuk pemain: melihat kota asal (home city) dan berinteraksi
 * dengan Tempat di dalamnya — Penginapan (istirahat), toko (Potion Shop,
 * Blacksmith), City Hall & Guild (info). Lihat App\Services\TownService.
 */
class TownController extends Controller
{
    public function __construct(
        private StoryEngine $story,
        private TownService $town,
        private RankService $ranks,
    ) {}

    /** Halaman kota: kota asal karakter + daftar Tempat di dalamnya. */
    public function show(Request $request): Response|RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }

        $city = $this->resolveCity($character);

        return Inertia::render('Town/Show', [
            'character' => $this->story->characterState($character),
            'city' => $city ? $this->cityPayload($city) : null,
            'places' => $city
                ? $city->places()->orderBy('category')->get()->map(fn (Place $p) => $this->placePayload($p))->all()
                : [],
            'rest_cost' => $this->town->restCost($character),
        ]);
    }

    /** Halaman sebuah Tempat (dispatch tampilan menurut kategori). */
    public function place(Request $request, Place $place): Response|RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }
        $this->authorizePlace($character, $place);

        $data = [
            'character' => $this->story->characterState($character),
            'place' => $this->placePayload($place->loadMissing('city')),
        ];

        if ($place->category === 'inn') {
            $data['rest_cost'] = $this->town->restCost($character);
            $data['is_fully_rested'] = $this->town->isFullyRested($character);
        }

        if ($this->town->isShop($place)) {
            $data['stock'] = $this->town->stock($place)->map(fn (Item $i) => $this->itemPayload($i))->all();
            $data['sellable'] = $character->items
                ->filter(fn (Item $i) => $this->town->sells($place, $i))
                ->map(fn (Item $i) => array_merge($this->itemPayload($i), [
                    'quantity' => $i->pivot->quantity,
                    'equipped' => (bool) $i->pivot->equipped,
                    'sell_price' => $this->town->sellPrice($i),
                ]))->values()->all();
        }

        if (isset(RankService::GUILD_AFFILIATION[$place->category])) {
            $affiliation = RankService::GUILD_AFFILIATION[$place->category];
            $data['rank'] = $this->ranks->progress($character);
            $data['can_accept'] = $character->affiliation === $affiliation && ! $character->active_quest_id;
            $data['active_mission'] = $character->active_quest_id && $character->activeQuest ? [
                'slug' => $character->activeQuest->slug,
                'title' => $character->activeQuest->title,
            ] : null;
            $data['missions'] = $this->ranks->availableMissions($character, $affiliation)
                ->map(fn (Quest $q) => [
                    'slug' => $q->slug,
                    'title' => $q->title,
                    'description' => $q->description,
                    'min_level' => $q->min_level,
                    'required_rank' => $q->required_rank ?? 'F',
                    'is_active' => $this->ranks->isActive($character, $q),
                    'block_reason' => $this->ranks->acceptBlockReason($character, $q),
                ])->all();
        }

        return Inertia::render('Town/Place', $data);
    }

    /** Ambil sebuah misi dari papan guild → jadi misi aktif & langsung dimainkan. */
    public function acceptMission(Request $request, Place $place): RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }
        $this->authorizePlace($character, $place);

        $affiliation = RankService::GUILD_AFFILIATION[$place->category] ?? null;
        abort_unless($affiliation, 422, 'Tempat ini bukan guild.');

        $data = $request->validate(['quest_slug' => ['required', 'string']]);
        $quest = Quest::where('slug', $data['quest_slug'])->where('affiliation', $affiliation)->first();
        if (! $quest) {
            return back()->with('error', 'Misi tidak ditemukan di guild ini.');
        }

        try {
            $this->ranks->accept($character, $quest);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('quests.play', $quest->slug)
            ->with('success', "Kamu mengambil misi \"{$quest->title}\". Selamat berjuang!");
    }

    /** Istirahat di penginapan. */
    public function rest(Request $request, Place $place): RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }
        $this->authorizePlace($character, $place);

        try {
            $result = $this->town->rest($character, $place);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        $r = $result['restored'];
        $parts = array_filter([
            $r['hp'] > 0 ? "{$r['hp']} HP" : null,
            $r['sp'] > 0 ? "{$r['sp']} SP" : null,
            $r['mp'] > 0 ? "{$r['mp']} MP" : null,
        ]);
        $summary = $parts ? implode(', ', $parts) : 'tenagamu';

        return back()->with('success', "Kamu beristirahat ({$result['cost']} emas) dan memulihkan {$summary}.");
    }

    /** Beli item dari toko. */
    public function buy(Request $request, Place $place): RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }
        $this->authorizePlace($character, $place);

        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);
        $item = Item::find($data['item_id']);
        if (! $item) {
            return back()->with('error', 'Item tidak ditemukan.');
        }

        try {
            $result = $this->town->buy($character, $place, $item, $data['qty'] ?? 1);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Kamu membeli {$result['qty']}× {$item->name} seharga {$result['total']} emas.");
    }

    /** Jual item ke toko. */
    public function sell(Request $request, Place $place): RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }
        $this->authorizePlace($character, $place);

        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);
        $item = Item::find($data['item_id']);
        if (! $item) {
            return back()->with('error', 'Item tidak ditemukan.');
        }

        try {
            $result = $this->town->sell($character, $place, $item, $data['qty'] ?? 1);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Kamu menjual {$result['qty']}× {$item->name} seharga {$result['total']} emas.");
    }

    /** Pastikan Tempat berada di kota karakter (kalau tidak, 403). */
    private function authorizePlace(Character $character, Place $place): void
    {
        $city = $this->resolveCity($character);
        abort_unless($city && (int) $place->city_id === (int) $city->id, 403, 'Tempat itu tidak ada di kotamu.');
    }

    /** Kota karakter saat ini; assign kota awal secara lazy bila belum punya. */
    private function resolveCity(Character $character): ?\App\Models\City
    {
        if (! $character->city_id) {
            $start = Character::startingCity();
            if ($start) {
                $character->city_id = $start->id;
                $character->save();
            }
        }

        return $character->city()->with('province.country')->first();
    }

    /** @return array<string, mixed> */
    private function cityPayload(\App\Models\City $city): array
    {
        $city->loadMissing('province.country');

        return [
            'slug' => $city->slug,
            'name' => $city->name,
            'description' => $city->description,
            'province' => $city->province?->name,
            'country' => $city->province?->country?->name,
        ];
    }

    /** @return array<string, mixed> */
    private function placePayload(Place $place): array
    {
        return [
            'slug' => $place->slug,
            'name' => $place->name,
            'category' => $place->category,
            'category_label' => Place::CATEGORIES[$place->category] ?? $place->category,
            'description' => $place->description,
            'is_shop' => $this->town->isShop($place),
            'is_guild' => isset(RankService::GUILD_AFFILIATION[$place->category]),
            'city' => $place->relationLoaded('city') && $place->city ? [
                'slug' => $place->city->slug,
                'name' => $place->city->name,
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function itemPayload(Item $item): array
    {
        return [
            'id' => $item->id,
            'slug' => $item->slug,
            'name' => $item->name,
            'type' => $item->type,
            'description' => $item->description,
            'image' => $item->image,
            'value' => (int) $item->value,
            'heal' => $this->story->healAmount($item),
            'restore_sp' => $this->story->spRestoreAmount($item),
            'restore_mp' => $this->story->mpRestoreAmount($item),
        ];
    }
}
