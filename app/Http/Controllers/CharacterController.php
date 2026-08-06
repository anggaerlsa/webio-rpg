<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\EquipmentService;
use App\Services\LearningService;
use App\Services\StoryEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Inertia\Inertia;
use Inertia\Response;

class CharacterController extends Controller
{
    /**
     * Hadiah pembuka untuk karakter baru: satu senjata latihan sesuai class,
     * langsung dipakai. Senjata latihan lain (busur/belati/tombak) dijual murah
     * di Blacksmith.
     *
     * @var array<string, string>
     */
    private const STARTER_WEAPON = [
        'Warrior' => 'pedang-latihan',
        'Mage' => 'tongkat-latihan',
    ];

    public function __construct(
        private StoryEngine $story,
        private EquipmentService $equipment,
        private LearningService $learning,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()->character) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Character/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:30'],
            'gender' => ['required', 'in:male,female'],
            'birth_date' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'class' => ['required', 'in:Warrior,Mage'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'name.min' => 'Nama minimal 2 karakter.',
            'name.max' => 'Nama maksimal 30 karakter.',
            'gender.required' => 'Pilih gender.',
            'gender.in' => 'Gender tidak valid.',
            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'birth_date.before' => 'Tanggal lahir harus di masa lalu.',
            'class.required' => 'Pilih class.',
            'class.in' => 'Class hanya Warrior atau Mage.',
        ]);

        // 1 akun = 1 karakter.
        if ($request->user()->character) {
            return redirect()->route('dashboard');
        }

        $character = $request->user()->characters()->create([
            'name' => $data['name'],
            'gender' => $data['gender'],
            'birth_date' => $data['birth_date'],
            'class' => $data['class'],
            'level' => 1,
            'xp' => 0,
            'hp' => 50,
            'max_hp' => 50,
            'sp' => 30,
            'max_sp' => 30,
            'mp' => 30,
            'max_mp' => 30,
            'strength' => 1,
            'agility' => 1,
            'dexterity' => 1,
            'intelligence' => 1,
            'vitality' => 1,
            'luck' => 1,
            'attack' => 10,
            'defense' => 5,
            'magic_attack' => 10,
            'magic_defense' => 5,
            'gold' => 0,
            'city_id' => \App\Models\Character::startingCity()?->id, // kota asal
            'is_alive' => true,
        ]);

        // Everyone starts knowing the default abilities (e.g. Pukul).
        $character->grantDefaultAbilities();
        $this->giveStarterWeapon($character, $data['class']);

        // Ke dashboard untuk onboarding pemilihan afiliasi.
        return redirect()->route('dashboard');
    }

    /**
     * Beri senjata latihan sesuai class lalu pasangkan. Item yang belum diimpor
     * (atau syarat levelnya diubah admin) tidak boleh menggagalkan pembuatan
     * karakter — hadiahnya sekadar bonus pembuka.
     */
    private function giveStarterWeapon(\App\Models\Character $character, string $class): void
    {
        $slug = self::STARTER_WEAPON[$class] ?? null;
        $item = $slug ? Item::where('slug', $slug)->first() : null;
        if (! $item) {
            return;
        }

        $this->story->giveItem($character, $slug);

        try {
            $this->equipment->equip($character, $item);
        } catch (HttpException) {
            // biarkan tersimpan di inventaris saja
        }
    }

    public function show(Request $request): Response|RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }

        return Inertia::render('Character/Sheet', [
            'character' => $this->story->characterState($character),
        ]);
    }

    /** Pakai potion dari inventaris untuk memulihkan HP (di luar pertarungan). */
    public function useItem(Request $request): RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }

        $data = $request->validate(['item_id' => ['required', 'integer']]);
        $item = Item::find($data['item_id']);
        if (! $item) {
            return back()->with('error', 'Item tidak ditemukan.');
        }

        try {
            $restored = $this->story->useConsumable($character, $item);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        $parts = [];
        if ($restored['hp'] > 0) {
            $parts[] = "{$restored['hp']} HP";
        }
        if ($restored['sp'] > 0) {
            $parts[] = "{$restored['sp']} SP";
        }
        if ($restored['mp'] > 0) {
            $parts[] = "{$restored['mp']} MP";
        }
        $summary = $parts ? implode(', ', $parts) : 'tidak ada';

        return back()->with('success', "Kamu memakai {$item->name} dan memulihkan {$summary}.");
    }

    /** Pakai (equip) sebuah senjata/zirah/aksesori. */
    public function equip(Request $request): RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }

        $data = $request->validate(['item_id' => ['required', 'integer']]);
        $item = Item::find($data['item_id']);
        if (! $item) {
            return back()->with('error', 'Item tidak ditemukan.');
        }

        try {
            $this->equipment->equip($character, $item);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$item->name} dipakai.");
    }

    /** Lepas sebuah item yang sedang dipakai. */
    public function unequip(Request $request): RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }

        $data = $request->validate(['item_id' => ['required', 'integer']]);
        $item = Item::find($data['item_id']);
        if (! $item) {
            return back()->with('error', 'Item tidak ditemukan.');
        }

        try {
            $this->equipment->unequip($character, $item);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$item->name} dilepas.");
    }

    /** Baca sebuah buku untuk mempelajari skill/sihir di dalamnya. */
    public function learn(Request $request): RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }

        $data = $request->validate(['item_id' => ['required', 'integer']]);
        $item = Item::find($data['item_id']);
        if (! $item) {
            return back()->with('error', 'Item tidak ditemukan.');
        }

        try {
            $learned = $this->learning->learn($character, $item);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        $kind = $learned['kind'] === 'spell' ? 'sihir' : 'skill';

        return back()->with('success', "Kamu mempelajari {$kind} \"{$learned['name']}\"!");
    }

    /** Onboarding: pilih afiliasi guild → ubah job, set rank F, beri kartu tanda. */
    public function chooseAffiliation(Request $request): RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }
        if ($character->affiliation) {
            return redirect()->route('dashboard'); // sudah memilih
        }

        $data = $request->validate([
            'affiliation' => ['required', 'in:adventurer,merchant'],
        ], [
            'affiliation.required' => 'Pilih afiliasi.',
            'affiliation.in' => 'Afiliasi tidak valid.',
        ]);

        $character->affiliation = $data['affiliation'];
        $character->rank = 'F';
        $character->save();

        // Job mengikuti afiliasi (jangan timpa gelar superadmin).
        $user = $request->user();
        if (! $user->isSuperadmin()) {
            $user->job = $data['affiliation']; // adventurer | merchant
            $user->save();
        }

        // Beri kartu tanda sesuai guild.
        $this->story->giveItem(
            $character,
            $data['affiliation'] === 'adventurer' ? 'kartu-tanda-petualang' : 'kartu-tanda-dagang',
        );

        $guild = $data['affiliation'] === 'adventurer' ? 'Guild Petualang' : 'Guild Merchant';

        return redirect()->route('dashboard')
            ->with('success', "Selamat bergabung dengan {$guild}! Kamu kini Rank F.");
    }
}
