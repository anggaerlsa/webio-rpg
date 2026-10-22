<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Country;
use App\Services\TownService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Peta dunia bertingkat: peta benua (semua kerajaan) → peta kerajaan
 * (kota & benteng) → halaman kota. Pindah kota lewat sini.
 */
class MapController extends Controller
{
    public function __construct(private TownService $town) {}

    /** Peta benua: semua kerajaan + tanda kerajaan tempat karakter berada. */
    public function index(Request $request): Response|RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }
        $hereId = $character->city?->province?->country_id;

        return Inertia::render('Map/World', [
            'kingdoms' => Country::orderBy('name')->get()->map(fn (Country $c) => [
                'slug' => $c->slug,
                'name' => $c->name,
                'map_x' => $c->map_x,
                'map_y' => $c->map_y,
                'is_here' => $c->id === $hereId,
            ])->all(),
        ]);
    }

    /** Peta kerajaan: kota & benteng di dalamnya, lengkap dengan biaya pindah. */
    public function kingdom(Request $request, Country $country): Response|RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }
        $inCombat = $this->town->isInCombat($character);

        return Inertia::render('Map/Kingdom', [
            'kingdom' => [
                'slug' => $country->slug,
                'name' => $country->name,
                'description' => $country->description,
                'government_type' => $country->government_type,
                'map_image' => $country->map_image,
            ],
            'settlements' => $country->cities()->with('province')->orderBy('cities.name')->get()
                ->map(function (City $city) use ($character, $inCombat) {
                    $cost = $this->town->travelCost($character, $city);

                    return [
                        'slug' => $city->slug,
                        'name' => $city->name,
                        'type' => $city->type,
                        'type_label' => City::TYPES[$city->type] ?? $city->type,
                        'description' => $city->description,
                        'image' => $city->image,
                        'map_x' => $city->map_x,
                        'map_y' => $city->map_y,
                        'is_here' => $city->id === $character->city_id,
                        'travel_cost' => $cost,
                        'can_travel' => ! $inCombat && $character->gold >= $cost,
                    ];
                })->all(),
            'in_combat' => $inCombat,
            'gold' => $character->gold,
        ]);
    }

    /** Pindahkan karakter ke kota/benteng tujuan, lalu masuk ke kotanya. */
    public function travel(Request $request, City $city): RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }

        try {
            $this->town->travel($character, $city);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('town.show')->with('success', "Kamu tiba di {$city->name}.");
    }
}
