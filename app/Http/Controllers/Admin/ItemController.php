<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    private const TYPES = ['weapon', 'armor', 'accessory', 'consumable', 'book', 'key', 'misc'];

    public function index(): Response
    {
        return Inertia::render('admin/items/Index', [
            'items' => Item::orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/items/Form', [
            'item' => null,
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $item = Item::create($this->validated($request));

        return redirect()->route('admin.items.index')
            ->with('success', "Item \"{$item->name}\" berhasil dibuat.");
    }

    public function edit(Item $item): Response
    {
        return Inertia::render('admin/items/Form', [
            'item' => $item,
            'types' => self::TYPES,
        ]);
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $item->update($this->validated($request, $item->id));

        return redirect()->route('admin.items.index')
            ->with('success', "Item \"{$item->name}\" berhasil diperbarui.");
    }

    public function destroy(Item $item): RedirectResponse
    {
        $name = $item->name;
        $item->delete();

        return redirect()->route('admin.items.index')
            ->with('success', "Item \"{$name}\" dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('items', 'slug')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(self::TYPES)],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'value' => ['nullable', 'integer', 'min:0'],
            'stats' => ['nullable', 'string', function (string $attr, ?string $value, callable $fail) {
                if ($value !== null && $value !== '' && json_decode($value) === null) {
                    $fail('Format JSON pada Statistik tidak valid.');
                }
            }],
        ], [
            'slug.required' => 'Slug wajib diisi.',
            'slug.unique' => 'Slug sudah dipakai item lain.',
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'name.required' => 'Nama wajib diisi.',
            'type.in' => 'Tipe tidak valid.',
        ]);

        $data['value'] = $data['value'] ?? 0;
        $data['stats'] = ($data['stats'] ?? '') !== '' ? json_decode($data['stats'], true) : null;

        return $data;
    }
}
