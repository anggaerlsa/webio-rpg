<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Monster;
use App\Models\NodeChoice;
use App\Models\Quest;
use App\Models\QuestNode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuestNodeController extends Controller
{
    private const TYPES = ['narrative', 'choice', 'combat', 'reward', 'ending'];

    public function create(Quest $quest): Response
    {
        return $this->form($quest, null);
    }

    public function store(Request $request, Quest $quest): RedirectResponse
    {
        $node = $this->save($request, $quest);

        return redirect()->route('admin.quests.edit', $quest)
            ->with('success', "Adegan \"{$node->key}\" ditambahkan.");
    }

    public function edit(Quest $quest, QuestNode $node): Response
    {
        abort_unless($node->quest_id === $quest->id, 404);

        return $this->form($quest, $node->load('choices'));
    }

    public function update(Request $request, Quest $quest, QuestNode $node): RedirectResponse
    {
        abort_unless($node->quest_id === $quest->id, 404);

        $this->save($request, $quest, $node);

        return redirect()->route('admin.quests.edit', $quest)
            ->with('success', "Adegan \"{$node->key}\" diperbarui.");
    }

    public function destroy(Quest $quest, QuestNode $node): RedirectResponse
    {
        abort_unless($node->quest_id === $quest->id, 404);

        $key = $node->key;
        if ($quest->start_node_id === $node->id) {
            $quest->update(['start_node_id' => null]);
        }
        $node->delete();

        return redirect()->route('admin.quests.edit', $quest)
            ->with('success', "Adegan \"{$key}\" dihapus.");
    }

    private function form(Quest $quest, ?QuestNode $node): Response
    {
        return Inertia::render('admin/nodes/Form', [
            'quest' => $quest->only('id', 'slug', 'title'),
            'node' => $node,
            'types' => self::TYPES,
            'monsters' => Monster::orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    private function save(Request $request, Quest $quest, ?QuestNode $node = null): QuestNode
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_-]+$/', Rule::unique('quest_nodes', 'key')->where('quest_id', $quest->id)->ignore($node?->id)],
            'type' => ['required', Rule::in(self::TYPES)],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'monster_id' => ['nullable', 'integer', Rule::exists('monsters', 'id')],
            'payload' => ['nullable', 'string', $this->jsonRule()],
            'choices' => ['array'],
            'choices.*.label' => ['required', 'string', 'max:255'],
            'choices.*.next_node_key' => ['nullable', 'string', 'max:80'],
            'choices.*.requirements' => ['nullable', 'string', $this->jsonRule()],
            'choices.*.effects' => ['nullable', 'string', $this->jsonRule()],
            'choices.*.is_auto' => ['boolean'],
        ], [
            'key.required' => 'Key wajib diisi.',
            'key.unique' => 'Key sudah dipakai di misi ini.',
            'key.regex' => 'Key hanya boleh huruf kecil, angka, garis bawah (_), dan tanda hubung (-).',
            'choices.*.label.required' => 'Label pilihan wajib diisi.',
        ]);

        $attributes = [
            'quest_id' => $quest->id,
            'key' => $data['key'],
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'image' => $data['image'] ?? null,
            'monster_id' => $data['monster_id'] ?? null,
            'payload' => ($data['payload'] ?? '') !== '' ? json_decode($data['payload'], true) : null,
        ];

        if ($node) {
            $node->update($attributes);
        } else {
            $node = QuestNode::create($attributes);
        }

        // Replace choices.
        $node->choices()->delete();
        foreach ($data['choices'] ?? [] as $i => $c) {
            NodeChoice::create([
                'quest_node_id' => $node->id,
                'label' => $c['label'],
                'next_node_key' => $c['next_node_key'] ?? null,
                'requirements' => ($c['requirements'] ?? '') !== '' ? json_decode($c['requirements'], true) : null,
                'effects' => ($c['effects'] ?? '') !== '' ? json_decode($c['effects'], true) : null,
                'is_auto' => $c['is_auto'] ?? false,
                'order' => $i,
            ]);
        }

        return $node;
    }

    private function jsonRule(): callable
    {
        return function (string $attr, ?string $value, callable $fail) {
            if ($value !== null && $value !== '' && json_decode($value) === null) {
                $fail('Format JSON tidak valid.');
            }
        };
    }
}
