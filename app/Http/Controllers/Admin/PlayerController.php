<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    public function index(): Response
    {
        $players = User::with('character')->orderBy('name')->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'job' => $u->job,
            'character' => $u->character ? ['name' => $u->character->name, 'level' => $u->character->level] : null,
        ]);

        return Inertia::render('admin/players/Index', ['players' => $players]);
    }

    public function edit(Request $request, User $player): Response
    {
        $player->load('character');
        $c = $player->character;

        return Inertia::render('admin/players/Form', [
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
                'email' => $player->email,
                'role' => $player->role,
                'job' => $player->job,
                'is_self' => $player->id === $request->user()->id,
                'character' => $c ? [
                    'name' => $c->name,
                    'class' => $c->class,
                    'level' => $c->level, 'xp' => $c->xp, 'gold' => $c->gold,
                    'hp' => $c->hp, 'max_hp' => $c->max_hp,
                    'sp' => $c->sp, 'max_sp' => $c->max_sp,
                    'mp' => $c->mp, 'max_mp' => $c->max_mp,
                    'attack' => $c->attack, 'defense' => $c->defense,
                    'magic_attack' => $c->magic_attack, 'magic_defense' => $c->magic_defense,
                    'strength' => $c->strength, 'agility' => $c->agility, 'dexterity' => $c->dexterity,
                    'intelligence' => $c->intelligence, 'vitality' => $c->vitality, 'luck' => $c->luck,
                    'rank' => $c->rank,
                ] : null,
            ],
            'roles' => ['player', 'superadmin'],
        ]);
    }

    public function update(Request $request, User $player): RedirectResponse
    {
        $isSelf = $player->id === $request->user()->id;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($player->id)],
            'role' => ['required', Rule::in(['player', 'superadmin'])],
            'job' => ['nullable', 'string', 'max:60'],
            'password' => ['nullable', 'string', 'min:8'],
        ];

        // Field karakter hanya divalidasi bila pemain ini punya karakter.
        if ($player->character) {
            foreach (['level', 'xp', 'gold', 'hp', 'max_hp', 'sp', 'max_sp', 'mp', 'max_mp',
                'attack', 'defense', 'magic_attack', 'magic_defense',
                'strength', 'agility', 'dexterity', 'intelligence', 'vitality', 'luck'] as $field) {
                $rules["character.$field"] = ['required', 'integer', 'min:0'];
            }
            $rules['character.rank'] = ['nullable', 'string', 'max:5'];
        }

        $data = $request->validate($rules, [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email sudah dipakai akun lain.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ]);

        $player->name = $data['name'];
        $player->email = $data['email'];
        $player->job = $data['job'] ?? null;
        if (! $isSelf) {
            $player->role = $data['role']; // jangan biarkan dewa menurunkan perannya sendiri (terkunci)
        }
        if (! empty($data['password'])) {
            $player->password = Hash::make($data['password']);
        }
        $player->save();

        if ($player->character && isset($data['character'])) {
            $player->character->update($data['character']); // Character $guarded = [] → mass-assignable
        }

        return redirect()->route('admin.players.index')
            ->with('success', "Pemain \"{$player->name}\" diperbarui.");
    }

    public function destroy(Request $request, User $player): RedirectResponse
    {
        if ($player->id === $request->user()->id) {
            return redirect()->route('admin.players.index')
                ->with('error', 'Kamu tidak bisa menghapus akunmu sendiri.');
        }

        $name = $player->name;
        // Cascade DB: user → characters → game_saves, combat_sessions, character_items, character_skill, character_spell.
        $player->delete();

        return redirect()->route('admin.players.index')
            ->with('success', "Pemain \"{$name}\" beserta karakter & seluruh progresnya dihapus.");
    }
}
