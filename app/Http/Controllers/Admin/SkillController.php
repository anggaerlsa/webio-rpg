<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SkillController extends Controller
{
    private const TYPES = ['physical', 'magic', 'ranged'];

    public function index(): Response
    {
        return Inertia::render('admin/skills/Index', [
            'skills' => Skill::orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/skills/Form', ['skill' => null, 'types' => self::TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $skill = Skill::create($this->validated($request));

        return redirect()->route('admin.skills.index')
            ->with('success', "Skill \"{$skill->name}\" berhasil dibuat.");
    }

    public function edit(Skill $skill): Response
    {
        return Inertia::render('admin/skills/Form', ['skill' => $skill, 'types' => self::TYPES]);
    }

    public function update(Request $request, Skill $skill): RedirectResponse
    {
        $skill->update($this->validated($request, $skill->id));

        return redirect()->route('admin.skills.index')
            ->with('success', "Skill \"{$skill->name}\" berhasil diperbarui.");
    }

    public function destroy(Skill $skill): RedirectResponse
    {
        $name = $skill->name;
        $skill->delete();

        return redirect()->route('admin.skills.index')
            ->with('success', "Skill \"{$name}\" dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('skills', 'slug')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(self::TYPES)],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'power' => ['nullable', 'integer'],
            'stamina_cost' => ['nullable', 'integer', 'min:0'],
            'level_req' => ['nullable', 'integer', 'min:1'],
            'is_default' => ['boolean'],
            'effects' => ['nullable', 'string', function (string $attr, ?string $value, callable $fail) {
                if ($value !== null && $value !== '' && json_decode($value) === null) {
                    $fail('Format JSON pada Efek tidak valid.');
                }
            }],
        ], [
            'slug.required' => 'Slug wajib diisi.',
            'slug.unique' => 'Slug sudah dipakai skill lain.',
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'name.required' => 'Nama wajib diisi.',
        ]);

        $data['power'] = $data['power'] ?? 1;
        $data['stamina_cost'] = $data['stamina_cost'] ?? 0;
        $data['level_req'] = $data['level_req'] ?? 1;
        $data['is_default'] = $data['is_default'] ?? false;
        $data['effects'] = ($data['effects'] ?? '') !== '' ? json_decode($data['effects'], true) : null;

        return $data;
    }
}
