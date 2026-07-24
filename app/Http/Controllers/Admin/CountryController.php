<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CountryController extends Controller
{
    public function index(): Response
    {
        $countries = Country::orderBy('name')->get()->map(fn (Country $c) => [
            'id' => $c->id,
            'slug' => $c->slug,
            'name' => $c->name,
            'government_type' => $c->government_type,
            'ruler_title' => $c->ruler_title,
            'ruler_name' => $c->ruler_name,
            'dominant_race' => $c->dominant_race,
            'capital' => $c->capitalCity()?->name,
        ]);

        return Inertia::render('admin/world/countries/Index', ['countries' => $countries]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/world/countries/Form', ['country' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $request->validate(
            ['capital_name' => ['nullable', 'string', 'max:120']],
            ['capital_name.max' => 'Nama ibukota maksimal 120 karakter.'],
        );
        $capitalName = trim((string) $request->input('capital_name')) ?: $data['name'];

        $country = DB::transaction(function () use ($data, $capitalName) {
            $country = Country::create($data);

            // Untuk permulaan: tiap negara otomatis punya satu Ibukota.
            // Provinsi dibuat sebagai wadah implisit — struktur DB tetap utuh,
            // tapi tidak ditonjolkan di UI sampai kelak butuh banyak kota/provinsi.
            $province = $country->provinces()->create([
                'name' => $country->name,
                'slug' => $this->uniqueSlug('provinces', $country->slug.'-wil'),
            ]);
            $province->cities()->create([
                'name' => $capitalName,
                'slug' => $this->uniqueSlug('cities', Str::slug($capitalName) ?: $country->slug),
            ]);

            return $country;
        });

        $capital = $country->capitalCity();

        return redirect()->route('admin.world.cities.show', $capital)
            ->with('success', "Negara \"{$country->name}\" dibuat dengan ibukota \"{$capital->name}\". Tambahkan tempat (Guild, Pasar, Penginapan, dll.) di sini.");
    }

    public function show(Country $country): Response
    {
        $capital = $country->capitalCity();
        $capital?->loadCount(['places', 'villages']);

        return Inertia::render('admin/world/countries/Show', [
            'country' => $country,
            'capital' => $capital ? [
                'id' => $capital->id,
                'name' => $capital->name,
                'slug' => $capital->slug,
                'places_count' => $capital->places_count,
                'villages_count' => $capital->villages_count,
            ] : null,
        ]);
    }

    public function edit(Country $country): Response
    {
        return Inertia::render('admin/world/countries/Form', ['country' => $country]);
    }

    public function update(Request $request, Country $country): RedirectResponse
    {
        $country->update($this->validated($request, $country->id));

        return redirect()->route('admin.world.countries.show', $country)
            ->with('success', "Negara \"{$country->name}\" diperbarui.");
    }

    public function destroy(Country $country): RedirectResponse
    {
        $name = $country->name;
        $country->delete(); // provinsi, kota, desa, tempat ikut terhapus (cascade)

        return redirect()->route('admin.world.countries.index')
            ->with('success', "Negara \"{$name}\" dihapus beserta seluruh wilayah di dalamnya.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('countries', 'slug')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:120'],
            'government_type' => ['nullable', 'string', 'max:80'],
            'ideology' => ['nullable', 'string', 'max:80'],
            'ruler_title' => ['nullable', 'string', 'max:80'],
            'ruler_name' => ['nullable', 'string', 'max:120'],
            'dominant_race' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
        ], [
            'slug.required' => 'Slug wajib diisi.',
            'slug.unique' => 'Slug sudah dipakai negara lain.',
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'name.required' => 'Nama wajib diisi.',
        ]);
    }

    /** Hasilkan slug unik pada $table dengan menambah sufiks angka bila bentrok. */
    private function uniqueSlug(string $table, string $base): string
    {
        $base = $base ?: 'lokasi';
        $slug = $base;
        $i = 2;
        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
