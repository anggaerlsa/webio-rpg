<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Village;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VillageController extends Controller
{
    public function create(City $city): Response
    {
        $city->load('province.country');

        return Inertia::render('admin/world/villages/Form', [
            'village' => null,
            'city' => $city,
        ]);
    }

    public function store(Request $request, City $city): RedirectResponse
    {
        $village = $city->villages()->create($this->validated($request));

        return redirect()->route('admin.world.cities.show', $city)
            ->with('success', "Desa \"{$village->name}\" ditambahkan.");
    }

    public function edit(Village $village): Response
    {
        $village->load('city.province.country');

        return Inertia::render('admin/world/villages/Form', [
            'village' => $village,
            'city' => $village->city,
        ]);
    }

    public function update(Request $request, Village $village): RedirectResponse
    {
        $village->update($this->validated($request, $village->id));

        return redirect()->route('admin.world.cities.show', $village->city_id)
            ->with('success', "Desa \"{$village->name}\" diperbarui.");
    }

    public function destroy(Village $village): RedirectResponse
    {
        $cityId = $village->city_id;
        $name = $village->name;
        $village->delete();

        return redirect()->route('admin.world.cities.show', $cityId)
            ->with('success', "Desa \"{$name}\" dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('villages', 'slug')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
        ], [
            'slug.required' => 'Slug wajib diisi.',
            'slug.unique' => 'Slug sudah dipakai desa lain.',
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'name.required' => 'Nama wajib diisi.',
        ]);
    }
}
