<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quest;
use App\Models\QuestNode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuestController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/quests/Index', [
            'quests' => Quest::withCount('nodes')->orderBy('order')->orderBy('title')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/quests/Form', ['quest' => null, 'nodes' => []]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        // No nodes exist yet, so start_node cannot resolve to an id on create.
        $quest = Quest::create(Arr::except($data, 'start_node'));

        return redirect()->route('admin.quests.index')
            ->with('success', "Misi \"{$quest->title}\" dibuat. Klik \"Ubah\" untuk menambahkan adegan.");
    }

    public function edit(Quest $quest): Response
    {
        return Inertia::render('admin/quests/Form', [
            'quest' => $quest,
            'nodes' => $quest->nodes()->orderBy('id')->get(['id', 'key', 'type', 'title']),
        ]);
    }

    public function update(Request $request, Quest $quest): RedirectResponse
    {
        $data = $this->validated($request, $quest->id);
        $quest->update(Arr::except($data, 'start_node'));

        // Resolve the chosen start-node key to its id.
        $quest->start_node_id = $data['start_node']
            ? QuestNode::where('quest_id', $quest->id)->where('key', $data['start_node'])->value('id')
            : null;
        $quest->save();

        return redirect()->route('admin.quests.edit', $quest)
            ->with('success', "Misi \"{$quest->title}\" diperbarui.");
    }

    public function destroy(Quest $quest): RedirectResponse
    {
        $title = $quest->title;
        $quest->delete(); // nodes & choices ikut terhapus (cascade)

        return redirect()->route('admin.quests.index')
            ->with('success', "Misi \"{$title}\" dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('quests', 'slug')->ignore($ignoreId)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'affiliation' => ['nullable', Rule::in(['adventurer', 'merchant'])],
            'required_rank' => ['nullable', Rule::in(\App\Services\RankService::LADDER)],
            'cover_image' => ['nullable', 'string', 'max:255'],
            'min_level' => ['nullable', 'integer', 'min:1'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['boolean'],
            'start_node' => ['nullable', 'string', 'max:80'],
        ], [
            'slug.required' => 'Slug wajib diisi.',
            'slug.unique' => 'Slug sudah dipakai misi lain.',
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'title.required' => 'Judul wajib diisi.',
        ]);

        $data['min_level'] = $data['min_level'] ?? 1;
        $data['order'] = $data['order'] ?? 0;
        $data['is_published'] = $data['is_published'] ?? true;

        return $data;
    }
}
