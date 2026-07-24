<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Province;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProvinceController extends Controller
{
    public function create(Country $country): Response
    {
        return Inertia::render('admin/world/provinces/Form', [
            'province' => null,
            'country' => $country,
        ]);
    }

    public function store(Request $request, Country $country): RedirectResponse
    {
        $province = $country->provinces()->create($this->validated($request));

        return redirect()->route('admin.world.provinces.show', $province)
            ->with('success', "Provinsi \"{$province->name}\" dibuat.");
    }

    public function show(Province $province): Response
    {
        $province->load('country');

        return Inertia::render('admin/world/provinces/Show', [
            'province' => $province,
            'cities' => $province->cities()->withCount(['villages', 'places'])->orderBy('name')->get(),
        ]);
    }

    public function edit(Province $province): Response
    {
        $province->load('country');

        return Inertia::render('admin/world/provinces/Form', [
            'province' => $province,
            'country' => $province->country,
        ]);
    }

    public function update(Request $request, Province $province): RedirectResponse
    {
        $province->update($this->validated($request, $province->id));

        return redirect()->route('admin.world.provinces.show', $province)
            ->with('success', "Provinsi \"{$province->name}\" diperbarui.");
    }

    public function destroy(Province $province): RedirectResponse
    {
        $countryId = $province->country_id;
        $name = $province->name;
        $province->delete(); // kota, desa, tempat ikut terhapus (cascade)

        return redirect()->route('admin.world.countries.show', $countryId)
            ->with('success', "Provinsi \"{$name}\" dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('provinces', 'slug')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
        ], [
            'slug.required' => 'Slug wajib diisi.',
            'slug.unique' => 'Slug sudah dipakai provinsi lain.',
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'name.required' => 'Nama wajib diisi.',
        ]);
    }
}
