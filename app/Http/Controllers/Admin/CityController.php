<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Place;
use App\Models\Province;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CityController extends Controller
{
    public function create(Province $province): Response
    {
        $province->load('country');

        return Inertia::render('admin/world/cities/Form', [
            'city' => null,
            'province' => $province,
        ]);
    }

    public function store(Request $request, Province $province): RedirectResponse
    {
        $city = $province->cities()->create($this->validated($request));

        return redirect()->route('admin.world.cities.show', $city)
            ->with('success', "Kota \"{$city->name}\" dibuat.");
    }

    public function show(City $city): Response
    {
        $city->load('province.country');

        return Inertia::render('admin/world/cities/Show', [
            'city' => $city,
            'villages' => $city->villages()->orderBy('name')->get(),
            'places' => $city->places()->orderBy('category')->orderBy('name')->get(),
            'placeCategories' => Place::CATEGORIES,
        ]);
    }

    public function edit(City $city): Response
    {
        $city->load('province.country');

        return Inertia::render('admin/world/cities/Form', [
            'city' => $city,
            'province' => $city->province,
        ]);
    }

    public function update(Request $request, City $city): RedirectResponse
    {
        $city->update($this->validated($request, $city->id));

        return redirect()->route('admin.world.cities.show', $city)
            ->with('success', "Kota \"{$city->name}\" diperbarui.");
    }

    public function destroy(City $city): RedirectResponse
    {
        $provinceId = $city->province_id;
        $name = $city->name;
        $city->delete(); // desa & tempat ikut terhapus (cascade)

        return redirect()->route('admin.world.provinces.show', $provinceId)
            ->with('success', "Kota \"{$name}\" dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('cities', 'slug')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
        ], [
            'slug.required' => 'Slug wajib diisi.',
            'slug.unique' => 'Slug sudah dipakai kota lain.',
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'name.required' => 'Nama wajib diisi.',
        ]);
    }
}
