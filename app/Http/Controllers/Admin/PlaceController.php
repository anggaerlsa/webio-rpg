<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlaceController extends Controller
{
    public function create(City $city): Response
    {
        $city->load('province.country');

        return Inertia::render('admin/world/places/Form', [
            'place' => null,
            'city' => $city,
            'categories' => Place::CATEGORIES,
        ]);
    }

    public function store(Request $request, City $city): RedirectResponse
    {
        $place = $city->places()->create($this->validated($request));

        return redirect()->route('admin.world.cities.show', $city)
            ->with('success', "Tempat \"{$place->name}\" ditambahkan.");
    }

    public function edit(Place $place): Response
    {
        $place->load('city.province.country');

        return Inertia::render('admin/world/places/Form', [
            'place' => $place,
            'city' => $place->city,
            'categories' => Place::CATEGORIES,
        ]);
    }

    public function update(Request $request, Place $place): RedirectResponse
    {
        $place->update($this->validated($request, $place->id));

        return redirect()->route('admin.world.cities.show', $place->city_id)
            ->with('success', "Tempat \"{$place->name}\" diperbarui.");
    }

    public function destroy(Place $place): RedirectResponse
    {
        $cityId = $place->city_id;
        $name = $place->name;
        $place->delete();

        return redirect()->route('admin.world.cities.show', $cityId)
            ->with('success', "Tempat \"{$name}\" dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(array_keys(Place::CATEGORIES))],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('places', 'slug')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
        ], [
            'category.required' => 'Kategori wajib dipilih.',
            'category.in' => 'Kategori tempat tidak valid.',
            'slug.required' => 'Slug wajib diisi.',
            'slug.unique' => 'Slug sudah dipakai tempat lain.',
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'name.required' => 'Nama wajib diisi.',
        ]);
    }
}
