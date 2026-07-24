<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Monster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MonsterController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/monsters/Index', [
            'monsters' => Monster::orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/monsters/Form', ['monster' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $monster = Monster::create($this->validated($request));

        return redirect()->route('admin.monsters.index')
            ->with('success', "Monster \"{$monster->name}\" berhasil dibuat.");
    }

    public function edit(Monster $monster): Response
    {
        return Inertia::render('admin/monsters/Form', ['monster' => $monster]);
    }

    public function update(Request $request, Monster $monster): RedirectResponse
    {
        $monster->update($this->validated($request, $monster->id));

        return redirect()->route('admin.monsters.index')
            ->with('success', "Monster \"{$monster->name}\" berhasil diperbarui.");
    }

    public function destroy(Monster $monster): RedirectResponse
    {
        $name = $monster->name;
        $monster->delete();

        return redirect()->route('admin.monsters.index')
            ->with('success', "Monster \"{$name}\" dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('monsters', 'slug')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:120'],
            'image' => ['nullable', 'string', 'max:255'],
            'max_hp' => ['required', 'integer', 'min:1'],
            'attack' => ['required', 'integer', 'min:0'],
            'defense' => ['nullable', 'integer', 'min:0'],
            'xp_reward' => ['nullable', 'integer', 'min:0'],
            'gold_reward' => ['nullable', 'integer', 'min:0'],
            'loot' => ['nullable', 'string', function (string $attr, ?string $value, callable $fail) {
                if ($value !== null && $value !== '' && json_decode($value) === null) {
                    $fail('Format JSON pada Loot tidak valid.');
                }
            }],
        ], [
            'slug.required' => 'Slug wajib diisi.',
            'slug.unique' => 'Slug sudah dipakai monster lain.',
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'name.required' => 'Nama wajib diisi.',
            'max_hp.required' => 'HP maksimum wajib diisi.',
            'attack.required' => 'Serangan wajib diisi.',
        ]);

        $data['defense'] = $data['defense'] ?? 0;
        $data['xp_reward'] = $data['xp_reward'] ?? 0;
        $data['gold_reward'] = $data['gold_reward'] ?? 0;
        $data['loot'] = ($data['loot'] ?? '') !== '' ? json_decode($data['loot'], true) : null;

        return $data;
    }
}
