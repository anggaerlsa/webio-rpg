<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeityController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/deities/Index', [
            'deities' => Deity::with('user:id,name,email')->orderBy('name')->get(),
            'blessings' => collect(Deity::BLESSINGS)->map(fn ($b) => $b['label']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/deities/Form', $this->formProps(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $deity = Deity::create($this->validated($request));

        return redirect()->route('admin.deities.index')->with('success', "Dewa \"{$deity->name}\" ditambahkan ke kuil.");
    }

    public function edit(Deity $deity): Response
    {
        return Inertia::render('admin/deities/Form', $this->formProps($deity));
    }

    public function update(Request $request, Deity $deity): RedirectResponse
    {
        $deity->update($this->validated($request, $deity->id));

        return redirect()->route('admin.deities.index')->with('success', "Dewa \"{$deity->name}\" diperbarui.");
    }

    public function destroy(Deity $deity): RedirectResponse
    {
        $name = $deity->name;
        $deity->delete();

        return redirect()->route('admin.deities.index')->with('success', "Dewa \"{$name}\" dihapus dari kuil.");
    }

    /** @return array<string, mixed> */
    private function formProps(?Deity $deity): array
    {
        return [
            'deity' => $deity,
            'blessings' => collect(Deity::BLESSINGS)->map(fn ($b) => $b['label']),
            // Calon pemeran: akun GM (superadmin).
            'gms' => User::where('role', 'superadmin')->orderBy('name')->get(['id', 'name', 'email']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('deities', 'slug')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:120'],
            'domain' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'blessing' => ['nullable', Rule::in(array_keys(Deity::BLESSINGS))],
            'blessing_message' => ['nullable', 'string', 'max:500'],
        ], [
            'slug.required' => 'Slug wajib diisi.',
            'slug.unique' => 'Slug sudah dipakai dewa lain.',
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'name.required' => 'Nama wajib diisi.',
            'title.required' => 'Gelar wajib diisi.',
            'user_id.exists' => 'Akun GM tidak ditemukan.',
            'blessing.in' => 'Jenis berkah tidak dikenal.',
        ]);
    }
}
