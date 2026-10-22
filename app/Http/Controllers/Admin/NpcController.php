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

        return Inertia::render('admin/world/npcs/Form', ['npc' => null, 'place' => $place, 'genders' => Npc::GENDERS, 'levels' => collect(Npc::LEVELS)->map(fn ($l) => $l['label'])]);
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

        return Inertia::render('admin/world/npcs/Form', ['npc' => $npc, 'place' => $npc->place, 'genders' => Npc::GENDERS, 'levels' => collect(Npc::LEVELS)->map(fn ($l) => $l['label'])]);
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
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('npcs', 'slug')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'string', 'max:80'],
            'gender' => ['required', Rule::in(array_keys(Npc::GENDERS))],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'greeting' => ['required', 'string', 'max:500'],
            'greeting_cold' => ['nullable', 'string', 'max:500'],
            'greeting_warm' => ['nullable', 'string', 'max:500'],
            'apology_reply' => ['nullable', 'string', 'max:500'],
            'refusal_message' => ['nullable', 'string', 'max:500'],
            'choices' => ['nullable', 'array', 'max:20'],
            'choices.*.label' => ['required', 'string', 'max:200'],
            'choices.*.reply' => ['required', 'string', 'max:1000'],
            'choices.*.affection' => ['required', 'integer', Rule::in(Npc::CHOICE_EFFECTS)],
            'choices.*.min_level' => ['nullable', Rule::in(array_keys(Npc::LEVELS))],
        ], [
            'slug.required' => 'Slug wajib diisi.',
            'slug.unique' => 'Slug sudah dipakai NPC lain.',
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'name.required' => 'Nama wajib diisi.',
            'role.required' => 'Peran wajib diisi.',
            'gender.in' => 'Jenis kelamin harus Pria atau Wanita.',
            'greeting.required' => 'Sapaan wajib diisi.',
            'choices.*.label.required' => 'Teks pilihan wajib diisi.',
            'choices.*.reply.required' => 'Jawaban NPC wajib diisi.',
            'choices.*.affection.in' => 'Efek afeksi hanya boleh -2, 0, atau +2.',
            'choices.*.min_level.in' => 'Level minimum tidak valid.',
        ]);

        // Simpan pilihan dalam bentuk tetap: hanya empat kunci ini, efek sebagai angka.
        $data['choices'] = collect($data['choices'] ?? [])->map(fn (array $c) => [
            'label' => $c['label'],
            'reply' => $c['reply'],
            'affection' => (int) $c['affection'],
            'min_level' => $c['min_level'] ?? null,
        ])->all();

        return $data;
    }
}
