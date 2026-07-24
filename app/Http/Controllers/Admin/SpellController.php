<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Spell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SpellController extends Controller
{
    private const ELEMENTS = ['api', 'air', 'angin', 'tanah', 'cahaya', 'kegelapan', 'arcane'];

    public function index(): Response
    {
        return Inertia::render('admin/spells/Index', [
            'spells' => Spell::orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/spells/Form', [
            'spell' => null,
            'elements' => self::ELEMENTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $spell = Spell::create($this->validated($request));

        return redirect()->route('admin.spells.index')
            ->with('success', "Sihir \"{$spell->name}\" berhasil dibuat.");
    }

    public function edit(Spell $spell): Response
    {
        return Inertia::render('admin/spells/Form', [
            'spell' => $spell,
            'elements' => self::ELEMENTS,
        ]);
    }

    public function update(Request $request, Spell $spell): RedirectResponse
    {
        $spell->update($this->validated($request, $spell->id));

        return redirect()->route('admin.spells.index')
            ->with('success', "Sihir \"{$spell->name}\" berhasil diperbarui.");
    }

    public function destroy(Spell $spell): RedirectResponse
    {
        $name = $spell->name;
        $spell->delete();

        return redirect()->route('admin.spells.index')
            ->with('success', "Sihir \"{$name}\" dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('spells', 'slug')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:120'],
            'element' => ['required', Rule::in(self::ELEMENTS)],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'mana_cost' => ['nullable', 'integer', 'min:0'],
            'power' => ['nullable', 'integer'],
            'min_level' => ['nullable', 'integer', 'min:1'],
            'effects' => ['nullable', 'string', function (string $attr, ?string $value, callable $fail) {
                if ($value !== null && $value !== '' && json_decode($value) === null) {
                    $fail('Format JSON pada Efek tidak valid.');
                }
            }],
        ], [
            'slug.required' => 'Slug wajib diisi.',
            'slug.unique' => 'Slug sudah dipakai sihir lain.',
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'name.required' => 'Nama wajib diisi.',
            'element.in' => 'Elemen tidak valid.',
        ]);

        $data['mana_cost'] = $data['mana_cost'] ?? 0;
        $data['power'] = $data['power'] ?? 0;
        $data['min_level'] = $data['min_level'] ?? 1;
        $data['effects'] = ($data['effects'] ?? '') !== '' ? json_decode($data['effects'], true) : null;

        return $data;
    }
}
