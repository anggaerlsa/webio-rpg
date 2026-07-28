<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForumCategory;
use App\Services\RankService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kategori Balai Warta. Terkunci = hanya Dewa yang boleh membuka topik
 * (dipakai untuk kategori maklumat). `scope` belum diekspos — semua global.
 */
class ForumCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/forum/Index', [
            'categories' => ForumCategory::withCount('topics')
                ->orderBy('position')->orderBy('id')->get()
                ->map(fn (ForumCategory $c) => [
                    'id' => $c->id,
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'position' => $c->position,
                    'is_locked' => $c->is_locked,
                    'min_rank' => $c->min_rank,
                    'topics_count' => $c->topics_count,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/forum/Form', [
            'category' => null,
            'ranks' => RankService::LADDER,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = ForumCategory::create($this->validated($request));

        return redirect()->route('admin.forum-categories.index')
            ->with('success', "Kategori \"{$category->name}\" berhasil dibuat.");
    }

    public function edit(ForumCategory $category): Response
    {
        return Inertia::render('admin/forum/Form', [
            'category' => $category,
            'ranks' => RankService::LADDER,
        ]);
    }

    public function update(Request $request, ForumCategory $category): RedirectResponse
    {
        $category->update($this->validated($request, $category->id));

        return redirect()->route('admin.forum-categories.index')
            ->with('success', "Kategori \"{$category->name}\" berhasil diperbarui.");
    }

    public function destroy(ForumCategory $category): RedirectResponse
    {
        $name = $category->name;
        $category->delete(); // cascade: topik + pesan di dalamnya ikut terhapus

        return redirect()->route('admin.forum-categories.index')
            ->with('success', "Kategori \"{$name}\" dihapus beserta seluruh topiknya.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('forum_categories', 'slug')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_locked' => ['boolean'],
            'min_rank' => ['nullable', Rule::in(RankService::LADDER)],
        ], [
            'slug.required' => 'Slug wajib diisi.',
            'slug.unique' => 'Slug sudah dipakai kategori lain.',
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'name.required' => 'Nama wajib diisi.',
            'min_rank.in' => 'Rank tidak valid.',
        ]);

        $data['position'] = $data['position'] ?? 0;
        $data['is_locked'] = (bool) ($data['is_locked'] ?? false);

        return $data;
    }
}
