<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Npc;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NpcController extends Controller
{
    public function index(Place $place): Response
    {
        $place->load('city.province.country', 'npcs');

        return Inertia::render('admin/world/npcs/Index', ['place' => $place]);
    }

    public function create(Place $place): Response
    {
        $place->load('city.province.country');

        return Inertia::render('admin/world/npcs/Form', ['npc' => null, 'place' => $place, 'genders' => Npc::GENDERS]);
    }

    public function store(Request $request, Place $place): RedirectResponse
    {
        $npc = $place->npcs()->create($this->validated($request));

        return redirect()->route('admin.world.places.npcs.index', $place)
            ->with('success', "NPC \"{$npc->name}\" ditambahkan.");
    }

    public function edit(Npc $npc): Response
    {
        $npc->load('place.city.province.country');

        return Inertia::render('admin/world/npcs/Form', ['npc' => $npc, 'place' => $npc->place, 'genders' => Npc::GENDERS]);
    }

    public function update(Request $request, Npc $npc): RedirectResponse
    {
        $npc->update($this->validated($request, $npc->id));

        return redirect()->route('admin.world.places.npcs.index', $npc->place_id)
            ->with('success', "NPC \"{$npc->name}\" diperbarui.");
    }

    public function destroy(Npc $npc): RedirectResponse
    {
        $placeId = $npc->place_id;
        $name = $npc->name;
        $npc->delete();

        return redirect()->route('admin.world.places.npcs.index', $placeId)
            ->with('success', "NPC \"{$name}\" dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('npcs', 'slug')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'string', 'max:80'],
            'gender' => ['required', Rule::in(array_keys(Npc::GENDERS))],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'greeting' => ['required', 'string', 'max:500'],
            'lines' => ['nullable', 'array', 'max:20'],
            'lines.*' => ['required', 'string', 'max:500'],
        ], [
            'slug.required' => 'Slug wajib diisi.',
            'slug.unique' => 'Slug sudah dipakai NPC lain.',
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'name.required' => 'Nama wajib diisi.',
            'role.required' => 'Peran wajib diisi.',
            'gender.in' => 'Jenis kelamin harus Pria atau Wanita.',
            'greeting.required' => 'Sapaan wajib diisi.',
            'lines.*.required' => 'Baris dialog tidak boleh kosong.',
            'lines.*.string' => 'Baris dialog harus berupa teks.',
        ]);
    }
}
