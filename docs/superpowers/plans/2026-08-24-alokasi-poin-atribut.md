# Alokasi Poin Atribut — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Naik level berhenti menaikkan keenam atribut RPG otomatis; sebagai gantinya pemain menerima 5 poin per level yang ia alokasikan sendiri, dan bisa diatur ulang berbayar di guild.

**Architecture:** Tanpa service baru. `LevelService` (pemilik hubungan level↔stat) mendapat `POINTS_PER_LEVEL`, `poolFor()`, `allocate()`, `resetAllocation()`. `TownService` (pemilik aksi di Tempat yang menagih emas — pola `restCost`/`rest`) mendapat `respecCost()`, `needsRespec()`, `respec()` yang memanggil `resetAllocation()`. Semua mutasi server-authoritative; klien hanya mengirim payload poin.

**Tech Stack:** Laravel 12 · PHP 8.2 · Inertia 2 + Vue 3 + Tailwind 3 · PHPUnit (SQLite in-memory) · MySQL/MariaDB untuk runtime

**Spec:** `docs/superpowers/specs/2026-08-24-alokasi-poin-atribut-design.md`

## Global Constraints

- Logika game **server-authoritative**: seluruh validasi & mutasi stat di PHP; klien hanya mengirim payload, tidak pernah mengirim hasil.
- Atribut **berbasis 1**: nilai 1 = baseline (efek 0). `CombatService::bonusStat` = `max(0, stat − 1)`. Aturan ini tidak disentuh.
- Enam atribut yang dialokasikan: `strength`, `agility`, `dexterity`, `intelligence`, `vitality`, `luck`. **Tidak** termasuk HP/SP/MP/attack/defense/magic_attack/magic_defense — semuanya tetap auto-growth.
- `POINTS_PER_LEVEL = 5`. Pool seumur hidup: `pool(L) = 5 × (L − 1)`.
- Biaya respec: `20 × level`. Tempat yang menerima respec: kategori `adventurer_guild` dan `merchant_guild` (dua-duanya, tanpa gate afiliasi).
- **Tanpa cap** per atribut. **Poin boleh ditumpuk** lintas level.
- Service melempar `abort_unless`/`abort_if` dengan status **422** dan pesan **Bahasa Indonesia**; controller menangkap `HttpException` lalu `back()->with('error', ...)`. Ini pola repo yang sudah ada (`TownController@rest`, `CharacterController@learn`) — jangan menyimpang.
- Test berjalan di **SQLite in-memory** (`RefreshDatabase`), tidak menyentuh database `webio`. Perintah: `php artisan test`.
- Tiap test perbaikan harus **dibuktikan gagal** dulu (lepas implementasinya, jalankan, pastikan merah, pulihkan) — kebiasaan repo ini. Test yang cuma menjaga perilaku lama ditandai eksplisit sebagai penjaga regresi.
- Komentar kode dalam Bahasa Indonesia, mengikuti kepadatan file yang disentuh. Jangan menambah komentar untuk hal yang sudah jelas dari kodenya.
- Jangan membuat file dokumentasi baru. Satu-satunya dokumen yang diperbarui: `GAME.md` (Task 9).

## Ringkasan file

| File | Tanggung jawab | Task |
|---|---|---|
| `database/migrations/2026_08_24_110001_add_pending_stat_points_to_characters_table.php` | Kolom baru + backfill karakter lama (reset atribut, kembalikan pool) | 1 |
| `app/Models/Character.php` | Default in-memory + cast integer kolom baru | 1 |
| `app/Services/LevelService.php` | `POINTS_PER_LEVEL`, `ATTRIBUTES`, `poolFor()`, `grantXp()` (berubah), `allocate()`, `resetAllocation()` | 2, 3, 6 |
| `app/Http/Controllers/CharacterController.php` | `allocate()` — terima payload, panggil service, flash | 3 |
| `routes/web.php` | Rute `character.allocate` & `town.respec` | 3, 6 |
| `app/Services/StoryEngine.php` | `characterState()` menyertakan `pending_stat_points` | 4 |
| `resources/js/types/game.ts` | Field `pending_stat_points` di `CharacterState` | 4 |
| `resources/js/components/game/CharacterStats.vue` | Draft `+`/`−` lokal + Simpan/Batal, emit `allocate` | 4 |
| `resources/js/pages/Character/Sheet.vue` | Menangkap emit `allocate` → POST | 4 |
| `app/Services/CombatService.php` | `stat_points_gained` di payload reward | 5 |
| `resources/js/components/game/CombatView.vue` | Baris "Naik level!" menyebut poin atribut | 5 |
| `app/Services/TownService.php` | `respecCost()`, `needsRespec()`, `respec()` | 6 |
| `app/Http/Controllers/TownController.php` | `respec()` + payload `respec_cost`/`can_respec` di panel guild | 6, 7 |
| `resources/js/pages/Town/Place.vue` | Panel "Latih Ulang Atribut" di guild | 7 |
| `app/Http/Controllers/Admin/PlayerController.php` | `pending_stat_points` di `edit()` payload & `update()` rules | 8 |
| `resources/js/pages/admin/players/Form.vue` | Field `pending_stat_points` di grup Progres | 8 |
| `tests/Feature/StatAllocationTest.php` | **Baru** — seluruh test fitur ini (level, alokasi, respec) | 2, 3, 6 |
| `GAME.md` | Dokumentasi perilaku baru | 9 |

**Catatan penyimpangan dari spec (disengaja, sudah disetujui saat review plan):** payload alokasi **dibungkus di bawah kunci `points`** — `{ points: { strength: 2, luck: 1 } }` — bukan enam kunci di level teratas. Alasan: kunci teratas bercampur dengan kunci framework (`_method`, `_token`), sehingga aturan "kunci tak dikenal → tolak" jadi tidak bisa ditegakkan tanpa daftar pengecualian. Membungkusnya membuat aturan itu bersih.

---

### Task 1: Kolom `pending_stat_points` + migrasi backfill

**Files:**
- Create: `database/migrations/2026_08_24_110001_add_pending_stat_points_to_characters_table.php`
- Modify: `app/Models/Character.php` (blok `$attributes` baris ~18-31, blok `casts()` baris ~33-58)
- Test: `tests/Feature/StatAllocationTest.php` (buat file)

**Interfaces:**
- Consumes: —
- Produces: kolom `characters.pending_stat_points` (unsigned integer, default 0), dicast `integer` di model dan tersedia sebagai `$character->pending_stat_points` bahkan pada model yang belum di-refresh dari DB.

**Penting:** backfill migrasi ini **tidak** tercakup test otomatis — `RefreshDatabase` menjalankan migrasi di database kosong, jadi tidak ada baris untuk di-backfill. Verifikasi backfill dilakukan manual di MySQL pada **Task 9**. Test di task ini hanya membuktikan kolom + cast ada.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/StatAllocationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StatAllocationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Character}
     */
    private function player(array $overrides = []): array
    {
        $user = User::factory()->create();
        $char = $user->characters()->create(array_merge([
            'name' => 'Hero', 'level' => 1, 'xp' => 0,
            'hp' => 50, 'max_hp' => 50, 'sp' => 30, 'max_sp' => 30, 'mp' => 30, 'max_mp' => 30,
            'attack' => 10, 'defense' => 5, 'gold' => 100, 'is_alive' => true,
        ], $overrides));

        return [$user, $char];
    }

    public function test_characters_have_a_pending_stat_points_column_defaulting_to_zero(): void
    {
        $this->assertTrue(Schema::hasColumn('characters', 'pending_stat_points'));

        [, $char] = $this->player();

        $this->assertSame(0, $char->fresh()->pending_stat_points);
        $this->assertSame(0, (new Character)->pending_stat_points); // default in-memory
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```bash
php artisan test --filter=test_characters_have_a_pending_stat_points_column_defaulting_to_zero
```

Harapan: FAIL — `assertTrue(Schema::hasColumn(...))` merah karena kolomnya belum ada.

- [ ] **Step 3: Tulis migrasinya**

Buat `database/migrations/2026_08_24_110001_add_pending_stat_points_to_characters_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Naik level tidak lagi menaikkan keenam atribut otomatis — pemain menerima poin
// yang ia alokasikan sendiri. Karakter lama direset ke baseline 1 dan menerima
// SELURUH pool seumur hidupnya (5 per level) untuk dialokasikan ulang.
//
// Angka 5 di sini SENGAJA hardcoded, bukan LevelService::POINTS_PER_LEVEL:
// migrasi harus mereproduksi sejarah walau konstanta itu kelak diubah.
return new class extends Migration
{
    private const POINTS_PER_LEVEL = 5;

    /** @var list<string> */
    private const ATTRIBUTES = ['strength', 'agility', 'dexterity', 'intelligence', 'vitality', 'luck'];

    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('pending_stat_points')->default(0)->after('luck');
        });

        // Iterasi di PHP, bukan GREATEST()/MAX() di SQL — fungsi itu berbeda antara
        // MySQL & SQLite, dan tabel characters kecil.
        DB::table('characters')->select('id', 'level')->orderBy('id')->chunk(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('characters')->where('id', $row->id)->update(array_merge(
                    array_fill_keys(self::ATTRIBUTES, 1),
                    ['pending_stat_points' => self::POINTS_PER_LEVEL * max(0, (int) $row->level - 1)],
                ));
            }
        });
    }

    public function down(): void
    {
        // Rumus lama: atribut mulai dari 1 dan +1 tiap level → nilainya = level.
        DB::table('characters')->select('id', 'level')->orderBy('id')->chunk(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('characters')->where('id', $row->id)
                    ->update(array_fill_keys(self::ATTRIBUTES, max(1, (int) $row->level)));
            }
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('pending_stat_points');
        });
    }
};
```

- [ ] **Step 4: Tambahkan default & cast di model**

Di `app/Models/Character.php`, dalam blok `protected $attributes`, tambahkan setelah `'luck' => 1,`:

```php
        'pending_stat_points' => 0,
```

Dalam `casts()`, tambahkan setelah `'luck' => 'integer',`:

```php
            'pending_stat_points' => 'integer',
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

```bash
php artisan test --filter=test_characters_have_a_pending_stat_points_column_defaulting_to_zero
```

Harapan: PASS (1 test, 3 assertions).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_24_110001_add_pending_stat_points_to_characters_table.php app/Models/Character.php tests/Feature/StatAllocationTest.php
git commit -m "Kolom pending_stat_points + backfill karakter lama"
```

---

### Task 2: Naik level memberi poin, bukan menaikkan atribut

**Files:**
- Modify: `app/Services/LevelService.php` (kelas penuh — tambah konstanta & `poolFor()`, ubah `grantXp()`)
- Test: `tests/Feature/StatAllocationTest.php`

**Interfaces:**
- Consumes: `characters.pending_stat_points` (Task 1).
- Produces:
  - `LevelService::POINTS_PER_LEVEL` — `public const int` = 5
  - `LevelService::ATTRIBUTES` — `public const array` = `['strength','agility','dexterity','intelligence','vitality','luck']`
  - `LevelService::poolFor(int $level): int` — `POINTS_PER_LEVEL * max(0, $level - 1)`
  - `grantXp()` tetap mengembalikan `array{leveled_up: bool, levels_gained: int, new_level: int}` (bentuk tidak berubah — Task 5 memakai `levels_gained`).

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/StatAllocationTest.php` (di dalam kelas):

```php
    public function test_leveling_up_grants_points_instead_of_raising_attributes(): void
    {
        [, $char] = $this->player(['level' => 1, 'xp' => 0]);
        $levels = app(\App\Services\LevelService::class);

        $result = $levels->grantXp($char, $levels->xpForLevel(1)); // tepat cukup untuk 1 level
        $char->save();

        $this->assertTrue($result['leveled_up']);
        $this->assertSame(2, $result['new_level']);
        $this->assertSame(5, $char->pending_stat_points);

        // Atribut TIDAK boleh ikut naik.
        foreach (\App\Services\LevelService::ATTRIBUTES as $attr) {
            $this->assertSame(1, $char->{$attr}, "Atribut {$attr} seharusnya tetap 1.");
        }
    }

    public function test_multiple_levels_from_one_reward_grant_points_per_level(): void
    {
        [, $char] = $this->player(['level' => 1, 'xp' => 0]);
        $levels = app(\App\Services\LevelService::class);

        // Cukup untuk melewati ambang level 1 dan level 2 sekaligus.
        $amount = $levels->xpForLevel(1) + $levels->xpForLevel(2);
        $result = $levels->grantXp($char, $amount);

        $this->assertSame(2, $result['levels_gained']);
        $this->assertSame(10, $char->pending_stat_points);
    }

    /** Penjaga regresi: pertumbuhan pool & stat tempur TIDAK dihapus. */
    public function test_leveling_up_still_grows_pools_and_combat_stats(): void
    {
        [, $char] = $this->player(['level' => 1, 'xp' => 0, 'max_hp' => 50, 'attack' => 10, 'defense' => 5]);
        $levels = app(\App\Services\LevelService::class);

        $levels->grantXp($char, $levels->xpForLevel(1));

        $this->assertSame(60, $char->max_hp);
        $this->assertSame(34, $char->max_sp);
        $this->assertSame(34, $char->max_mp);
        $this->assertSame(12, $char->attack);
        $this->assertSame(6, $char->defense);
        $this->assertSame(60, $char->hp); // pulih penuh saat naik level
    }

    public function test_pool_for_level_is_five_per_level_above_one(): void
    {
        $levels = app(\App\Services\LevelService::class);

        $this->assertSame(0, $levels->poolFor(1));
        $this->assertSame(5, $levels->poolFor(2));
        $this->assertSame(45, $levels->poolFor(10));
        $this->assertSame(0, $levels->poolFor(0)); // tidak pernah negatif
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```bash
php artisan test --filter=StatAllocationTest
```

Harapan: FAIL — `poolFor()` dan konstanta `ATTRIBUTES` belum ada (Error: undefined method/constant), dan `pending_stat_points` masih 0 setelah naik level.

- [ ] **Step 3: Ubah `LevelService`**

Ganti isi `app/Services/LevelService.php` menjadi:

```php
<?php

namespace App\Services;

use App\Models\Character;

class LevelService
{
    /** Poin atribut yang diterima pemain tiap kali naik level. */
    public const POINTS_PER_LEVEL = 5;

    /** Atribut RPG yang dialokasikan pemain (bukan yang auto-growth). */
    public const ATTRIBUTES = ['strength', 'agility', 'dexterity', 'intelligence', 'vitality', 'luck'];

    /**
     * XP required to advance FROM the given level to the next one.
     * Tunable curve: floor(100 * level^1.5).
     */
    public function xpForLevel(int $level): int
    {
        return (int) floor(100 * ($level ** 1.5));
    }

    /** Total poin yang PERNAH diterima karakter sampai level ini. */
    public function poolFor(int $level): int
    {
        return self::POINTS_PER_LEVEL * max(0, $level - 1);
    }

    /**
     * Grant XP and apply any level-ups in-memory. The CALLER is responsible
     * for persisting the character (services wrap this in a transaction).
     *
     * @return array{leveled_up: bool, levels_gained: int, new_level: int}
     */
    public function grantXp(Character $character, int $amount): array
    {
        if ($amount <= 0) {
            return ['leveled_up' => false, 'levels_gained' => 0, 'new_level' => $character->level];
        }

        $character->xp += $amount;
        $levelsGained = 0;

        // Loop in case a single reward crosses multiple level thresholds.
        while ($character->xp >= $this->xpForLevel($character->level)) {
            $character->xp -= $this->xpForLevel($character->level);
            $character->level += 1;
            $levelsGained++;

            // Stat growth on level up.
            $character->max_hp += 10;
            $character->max_sp += 4;
            $character->max_mp += 4;
            $character->attack += 2;
            $character->defense += 1;
            // Jalur sihir tumbuh sejajar jalur fisik.
            $character->magic_attack += 2;
            $character->magic_defense += 1;

            // Atribut RPG TIDAK naik otomatis — pemain mengalokasikan poinnya sendiri.
            $character->pending_stat_points += self::POINTS_PER_LEVEL;

            // Pulihkan penuh saat naik level.
            $character->hp = $character->max_hp;
            $character->sp = $character->max_sp;
            $character->mp = $character->max_mp;
            $character->is_alive = true;
        }

        return [
            'leveled_up' => $levelsGained > 0,
            'levels_gained' => $levelsGained,
            'new_level' => $character->level,
        ];
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

```bash
php artisan test --filter=StatAllocationTest
```

Harapan: PASS (5 test).

- [ ] **Step 5: Jalankan seluruh test — cari yang patah karena kurva berubah**

```bash
php artisan test
```

Harapan: kemungkinan ada test lama yang menganggap atribut naik saat level-up (kandidat: `CombatServiceTest`, `GameFlowTest`, `StoryEngineTest`). Untuk **tiap** kegagalan: baca test itu, putuskan apakah ekspektasinya memang sudah kedaluwarsa (perbaiki angkanya) atau justru menemukan bug (perbaiki kodenya). **Jangan** melemahkan assertion supaya lolos. Kalau tidak ada yang gagal, catat itu di pesan commit.

- [ ] **Step 6: Buktikan test menangkap regresi**

Kembalikan sementara enam baris `$character->{atribut} += 1;` di dalam loop `grantXp()` (biarkan baris `pending_stat_points` tetap ada), lalu:

```bash
php artisan test --filter=test_leveling_up_grants_points_instead_of_raising_attributes
```

Harapan: FAIL dengan pesan "Atribut strength seharusnya tetap 1." Hapus kembali keenam baris itu dan pastikan test hijau lagi.

- [ ] **Step 7: Commit**

```bash
git add app/Services/LevelService.php tests/Feature/StatAllocationTest.php
git commit -m "Naik level memberi 5 poin atribut, bukan menaikkan keenam atribut"
```

---

### Task 3: `allocate()` + rute & controller

**Files:**
- Modify: `app/Services/LevelService.php` (tambah `allocate()`)
- Modify: `app/Http/Controllers/CharacterController.php` (constructor + method `allocate()`)
- Modify: `routes/web.php` (setelah baris `character/learn`, ~baris 44)
- Test: `tests/Feature/StatAllocationTest.php`

**Interfaces:**
- Consumes: `LevelService::ATTRIBUTES`, `LevelService::POINTS_PER_LEVEL` (Task 2).
- Produces:
  - `LevelService::allocate(Character $character, array $points): void` — melempar `HttpException` 422 bila tidak sah; mengubah model **dan menyimpannya** (dibungkus `DB::transaction`).
  - Rute bernama `character.allocate` → `POST /character/allocate`, payload `{ points: { <attr>: int, ... } }`, respons redirect back + flash.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/StatAllocationTest.php`:

```php
    public function test_allocating_points_raises_attributes_and_spends_the_pool(): void
    {
        [$user, $char] = $this->player(['level' => 3, 'pending_stat_points' => 10]);

        $this->actingAs($user)
            ->post(route('character.allocate'), ['points' => ['strength' => 4, 'luck' => 2]])
            ->assertRedirect()->assertSessionHas('success');

        $char->refresh();
        $this->assertSame(5, $char->strength); // 1 + 4
        $this->assertSame(3, $char->luck);     // 1 + 2
        $this->assertSame(1, $char->agility);  // tak disentuh
        $this->assertSame(4, $char->pending_stat_points); // 10 - 6
    }

    public function test_allocating_more_than_available_is_rejected_without_changes(): void
    {
        [$user, $char] = $this->player(['pending_stat_points' => 3]);

        $this->actingAs($user)
            ->post(route('character.allocate'), ['points' => ['strength' => 4]])
            ->assertRedirect()->assertSessionHas('error');

        $char->refresh();
        $this->assertSame(1, $char->strength);
        $this->assertSame(3, $char->pending_stat_points);
    }

    public function test_allocating_zero_points_is_rejected(): void
    {
        [$user, $char] = $this->player(['pending_stat_points' => 5]);

        $this->actingAs($user)
            ->post(route('character.allocate'), ['points' => ['strength' => 0, 'luck' => 0]])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(5, $char->fresh()->pending_stat_points);
    }

    public function test_allocating_with_no_pending_points_is_rejected(): void
    {
        [$user, $char] = $this->player(['pending_stat_points' => 0]);

        $this->actingAs($user)
            ->post(route('character.allocate'), ['points' => ['strength' => 1]])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(1, $char->fresh()->strength);
    }

    public function test_allocating_an_unknown_attribute_is_rejected(): void
    {
        [$user, $char] = $this->player(['pending_stat_points' => 5]);

        $this->actingAs($user)
            ->post(route('character.allocate'), ['points' => ['max_hp' => 1]])
            ->assertRedirect()->assertSessionHas('error');

        $char->refresh();
        $this->assertSame(50, $char->max_hp);
        $this->assertSame(5, $char->pending_stat_points);
    }

    public function test_allocating_a_negative_amount_is_rejected(): void
    {
        [$user, $char] = $this->player(['pending_stat_points' => 5]);

        $this->actingAs($user)
            ->post(route('character.allocate'), ['points' => ['strength' => 3, 'luck' => -1]])
            ->assertRedirect()->assertSessionHas('error');

        $char->refresh();
        $this->assertSame(1, $char->strength);
        $this->assertSame(5, $char->pending_stat_points);
    }

    public function test_omitted_attributes_are_treated_as_zero(): void
    {
        [$user, $char] = $this->player(['pending_stat_points' => 5]);

        $this->actingAs($user)
            ->post(route('character.allocate'), ['points' => ['vitality' => 5]])
            ->assertRedirect()->assertSessionHas('success');

        $char->refresh();
        $this->assertSame(6, $char->vitality);
        $this->assertSame(0, $char->pending_stat_points);
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```bash
php artisan test --filter=StatAllocationTest
```

Harapan: FAIL — `route('character.allocate')` melempar karena rutenya belum terdaftar.

- [ ] **Step 3: Tambahkan `allocate()` ke `LevelService`**

Tambahkan `use Illuminate\Support\Facades\DB;` di bagian import `app/Services/LevelService.php`, lalu tambahkan method ini setelah `poolFor()`:

```php
    /**
     * Alokasikan poin ke atribut. Kunci yang tidak ada dianggap 0; kunci di luar
     * ATTRIBUTES ditolak. Total harus antara 1 dan poin yang tersedia.
     *
     * @param  array<string, mixed>  $points
     */
    public function allocate(Character $character, array $points): void
    {
        $unknown = array_diff(array_keys($points), self::ATTRIBUTES);
        abort_unless($unknown === [], 422, 'Atribut tidak dikenal: '.implode(', ', $unknown).'.');

        $clean = [];
        foreach (self::ATTRIBUTES as $attr) {
            $value = $points[$attr] ?? 0;
            // Lewat HTTP nilainya datang sebagai string; filter_var menolak "abc",
            // "1.5", dan "" sekaligus, lalu cek < 0 menutup nilai negatif.
            $int = filter_var($value, FILTER_VALIDATE_INT);
            abort_if($int === false, 422, "Nilai untuk {$attr} harus bilangan bulat.");
            abort_if($int < 0, 422, "Nilai untuk {$attr} tidak boleh negatif.");
            $clean[$attr] = $int;
        }

        $total = array_sum($clean);
        abort_if($total < 1, 422, 'Tidak ada poin yang dialokasikan.');
        abort_if($total > $character->pending_stat_points, 422, "Poinmu tidak cukup (tersedia {$character->pending_stat_points}, diminta {$total}).");

        DB::transaction(function () use ($character, $clean, $total) {
            foreach ($clean as $attr => $value) {
                $character->{$attr} += $value;
            }
            $character->pending_stat_points -= $total;
            $character->save();
        });
    }
```

- [ ] **Step 4: Tambahkan controller & rute**

Di `app/Http/Controllers/CharacterController.php`, tambahkan `use App\Services\LevelService;` di bagian import, lalu tambahkan parameter di constructor:

```php
    public function __construct(
        private StoryEngine $story,
        private EquipmentService $equipment,
        private LearningService $learning,
        private LevelService $levels,
    ) {}
```

Tambahkan method ini setelah `learn()`:

```php
    /** Alokasikan poin atribut hasil naik level. */
    public function allocate(Request $request): RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }

        $data = $request->validate(['points' => ['required', 'array']]);

        try {
            $this->levels->allocate($character, $data['points']);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        $total = array_sum(array_map('intval', $data['points']));

        return back()->with('success', "{$total} poin atribut dialokasikan.");
    }
```

Di `routes/web.php`, tambahkan setelah baris `character/learn`:

```php
    Route::post('character/allocate', [CharacterController::class, 'allocate'])->name('character.allocate');
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

```bash
php artisan test --filter=StatAllocationTest
```

Harapan: PASS (12 test).

- [ ] **Step 6: Buktikan test menangkap regresi**

Ganti sementara `abort_if($total > $character->pending_stat_points, ...)` menjadi `abort_if(false, ...)`, lalu:

```bash
php artisan test --filter=test_allocating_more_than_available_is_rejected_without_changes
```

Harapan: FAIL. Pulihkan baris aslinya dan pastikan hijau lagi.

- [ ] **Step 7: Commit**

```bash
git add app/Services/LevelService.php app/Http/Controllers/CharacterController.php routes/web.php tests/Feature/StatAllocationTest.php
git commit -m "Alokasi poin atribut: service, rute, dan controller"
```

---

### Task 4: UI alokasi di halaman Karakter

**Files:**
- Modify: `app/Services/StoryEngine.php` (`characterState()`, setelah `'luck' => $character->luck,`)
- Modify: `resources/js/types/game.ts` (interface `CharacterState`, setelah `luck: number;`)
- Modify: `resources/js/components/game/CharacterStats.vue` (blok `<script setup>` dan bagian atas `<template>`)
- Modify: `resources/js/pages/Character/Sheet.vue` (fungsi baru + prop/handler di `<CharacterStats>`)
- Test: manual (tidak ada test frontend di repo ini) + `php artisan test` untuk memastikan payload tidak memecahkan test yang ada

**Interfaces:**
- Consumes: rute `character.allocate` (Task 3), kolom `pending_stat_points` (Task 1).
- Produces:
  - `CharacterState.pending_stat_points: number` — dipakai Task 7 (tidak) dan Task 8 (tidak); hanya komponen ini.
  - `CharacterStats.vue` sekarang menerima prop opsional `disabled?: boolean` dan meng-emit `allocate` dengan payload `Record<string, number>` (hanya atribut bernilai > 0).

- [ ] **Step 1: Sertakan field di payload dan tipe**

Di `app/Services/StoryEngine.php`, dalam `characterState()`, tambahkan setelah `'luck' => $character->luck,`:

```php
            'pending_stat_points' => (int) $character->pending_stat_points,
```

Di `resources/js/types/game.ts`, dalam `interface CharacterState`, tambahkan setelah `luck: number;`:

```ts
    pending_stat_points: number;
```

- [ ] **Step 2: Tulis ulang `<script setup>` di `CharacterStats.vue`**

Ganti seluruh blok `<script setup lang="ts">` … `</script>` dengan:

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { CharacterState } from '@/types/game';
import { Brain, Clover, Minus, Plus, Shield, Sword, Target, Wand2, Wind } from 'lucide-vue-next';
import { computed, ref, watch, type Component } from 'vue';

const props = defineProps<{ character: CharacterState; disabled?: boolean }>();
const emit = defineEmits<{ (e: 'allocate', points: Record<string, number>): void }>();

// Konstanta efek HARUS sinkron dengan App\Services\CombatService (PHP).
const PHYS_PER_STR = 2; // % dmg fisik per STR
const MAGIC_PER_INT = 2; // % dmg sihir per INT
const DODGE_PER_AGI = 1; // % hindar per AGI
const CRIT_PER_DEX = 1; // % kritikal per DEX
const CRIT_PER_LUK = 0.5; // % kritikal per LUK
const GOLD_PER_LUK = 1; // % emas per LUK

const c = computed(() => props.character);
// Pakai stat efektif (dasar + perlengkapan) bila tersedia.
const eff = computed(() => c.value.effective);
const gear = computed(() => c.value.equip_bonuses);

// Stat 1 = baseline → efek 0; hanya poin DI ATAS 1 yang berpengaruh.
const bonus = (v: number) => Math.max(0, v - 1);

type AttrKey = 'strength' | 'agility' | 'dexterity' | 'intelligence' | 'vitality' | 'luck';

const ATTRS: { attr: AttrKey; abbr: string; name: string; icon: Component; effect: (v: number) => string }[] = [
    { attr: 'strength', abbr: 'STR', name: 'Kekuatan', icon: Sword, effect: (v) => `+${bonus(v) * PHYS_PER_STR}% dmg fisik` },
    { attr: 'agility', abbr: 'AGI', name: 'Kelincahan', icon: Wind, effect: (v) => `${Math.min(100, bonus(v) * DODGE_PER_AGI)}% hindar` },
    { attr: 'dexterity', abbr: 'DEX', name: 'Ketangkasan', icon: Target, effect: (v) => `+${bonus(v) * CRIT_PER_DEX}% kritikal` },
    { attr: 'intelligence', abbr: 'INT', name: 'Kecerdasan', icon: Brain, effect: (v) => `+${bonus(v) * MAGIC_PER_INT}% dmg sihir · +${bonus(v)} pert. sihir` },
    { attr: 'vitality', abbr: 'VIT', name: 'Ketahanan', icon: Shield, effect: (v) => `+${bonus(v)} pertahanan` },
    { attr: 'luck', abbr: 'LUK', name: 'Keberuntungan', icon: Clover, effect: (v) => `+${bonus(v) * GOLD_PER_LUK}% emas` },
];

const blankDraft = (): Record<AttrKey, number> => ({
    strength: 0, agility: 0, dexterity: 0, intelligence: 0, vitality: 0, luck: 0,
});

// Draft LOKAL: pemain menyusun alokasi dulu, baru dikirim sekali lewat Simpan.
const draft = ref(blankDraft());
const drafted = computed(() => Object.values(draft.value).reduce((a, b) => a + b, 0));
const available = computed(() => c.value.pending_stat_points ?? 0);
const remaining = computed(() => available.value - drafted.value);

// Props diganti Inertia setelah alokasi tersimpan → draft dibuang. Kalau
// requestnya gagal, pending tidak berubah dan draft pemain tetap utuh.
watch(available, () => (draft.value = blankDraft()));

const stats = computed(() =>
    ATTRS.map((a) => {
        const value = eff.value[a.attr] + draft.value[a.attr];

        return { ...a, value, gear: gear.value[a.attr], draft: draft.value[a.attr], effect: a.effect(value) };
    }),
);

const critChance = computed(() =>
    Math.min(
        100,
        bonus(eff.value.dexterity + draft.value.dexterity) * CRIT_PER_DEX + bonus(eff.value.luck + draft.value.luck) * CRIT_PER_LUK,
    ),
);
const dodgeChance = computed(() => Math.min(100, bonus(eff.value.agility + draft.value.agility) * DODGE_PER_AGI));

function add(attr: AttrKey) {
    if (remaining.value > 0) draft.value[attr] += 1;
}
function remove(attr: AttrKey) {
    if (draft.value[attr] > 0) draft.value[attr] -= 1;
}
function save() {
    if (drafted.value < 1 || props.disabled) return;
    const points = Object.fromEntries(Object.entries(draft.value).filter(([, v]) => v > 0));
    emit('allocate', points);
}
</script>
```

- [ ] **Step 3: Ubah `<template>` di `CharacterStats.vue`**

Ganti baris judul panel:

```vue
        <h2 class="mb-3 font-display text-lg font-semibold">Atribut</h2>
```

menjadi header dengan indikator poin:

```vue
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-display text-lg font-semibold">Atribut</h2>
            <span v-if="available > 0" class="rounded-full border border-amber-500/40 bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-600">
                Poin tersedia: {{ remaining }}<span v-if="drafted"> / {{ available }}</span>
            </span>
        </div>
```

Di dalam `v-for="s in stats"`, ganti seluruh elemen `<div class="min-w-0">…</div>` (blok yang memuat abbr/value/effect) dengan dua elemen berikut — kolom teks yang diperlebar plus kolom tombol di sebelahnya:

```vue
                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline gap-1.5">
                        <span class="font-display text-sm font-bold">{{ s.abbr }}</span>
                        <span class="text-lg font-bold tabular-nums">{{ s.value }}</span>
                        <span v-if="s.gear" class="text-xs font-semibold text-emerald-600">(+{{ s.gear }})</span>
                        <span v-if="s.draft" class="text-xs font-semibold text-amber-600">(+{{ s.draft }} baru)</span>
                    </div>
                    <div class="truncate text-xs text-muted-foreground">{{ s.effect }}</div>
                </div>
                <div v-if="available > 0" class="flex shrink-0 flex-col gap-1">
                    <button
                        type="button"
                        class="flex h-6 w-6 items-center justify-center rounded border text-muted-foreground transition hover:bg-accent disabled:opacity-30"
                        :disabled="remaining < 1 || disabled"
                        :aria-label="`Tambah ${s.name}`"
                        @click="add(s.attr)"
                    >
                        <Plus class="h-3.5 w-3.5" />
                    </button>
                    <button
                        type="button"
                        class="flex h-6 w-6 items-center justify-center rounded border text-muted-foreground transition hover:bg-accent disabled:opacity-30"
                        :disabled="s.draft < 1 || disabled"
                        :aria-label="`Kurangi ${s.name}`"
                        @click="remove(s.attr)"
                    >
                        <Minus class="h-3.5 w-3.5" />
                    </button>
                </div>
```

Ganti juga `:key="s.key"` menjadi `:key="s.attr"` pada elemen `v-for` (kunci `key` sudah tidak ada di objek stats).

Tambahkan baris aksi tepat setelah `</div>` penutup grid atribut, sebelum blok "Dua jalur serangan":

```vue
        <div v-if="drafted > 0" class="mt-3 flex flex-wrap items-center gap-3 border-t pt-3">
            <Button size="sm" :disabled="disabled" @click="save">Simpan {{ drafted }} poin</Button>
            <Button size="sm" variant="outline" :disabled="disabled" @click="draft = blankDraft()">Batal</Button>
            <span class="text-xs text-muted-foreground">Alokasi permanen sampai kamu berlatih ulang di guild.</span>
        </div>
```

Catatan: `blankDraft` harus dipanggil dari template, jadi biarkan ia sebagai fungsi di `<script setup>` (sudah begitu di Step 2).

- [ ] **Step 4: Sambungkan di `Sheet.vue`**

Di `resources/js/pages/Character/Sheet.vue`, tambahkan fungsi setelah `learnItem`:

```ts
function allocatePoints(points: Record<string, number>) {
    if (processing.value) return;
    processing.value = true;
    router.post(route('character.allocate'), { points }, { preserveScroll: true, onFinish: () => (processing.value = false) });
}
```

Ganti pemakaian komponennya:

```vue
            <CharacterStats :character="character" :disabled="processing" @allocate="allocatePoints" />
```

- [ ] **Step 5: Build & lint**

```bash
npm run build
```

Harapan: build sukses tanpa error TypeScript.

```bash
npm run lint
```

Harapan: bersih (atau hanya auto-fix yang diterapkan).

- [ ] **Step 6: Jalankan seluruh test PHP**

```bash
php artisan test
```

Harapan: semua hijau — payload karakter dipakai banyak test, jadi ini menangkap kesalahan bentuk payload.

- [ ] **Step 7: Verifikasi di browser**

Jalankan server dev lewat entri launch.json `webio-8001` (port 8000 dipakai project lain), lalu:

1. Login sebagai `player1@webio.test` / `player123`.
2. Buka `/character`. Kalau `pending_stat_points` masih 0, setel dulu lewat Panel Dewa atau tinker:
   `php artisan tinker --execute="\App\Models\Character::first()->update(['pending_stat_points' => 10]);"`
3. Pastikan: badge "Poin tersedia: 10" muncul; `+` menaikkan angka atribut & pratinjau efeknya berubah; tombol `+` mati setelah 10 poin terpakai; "Batal" mengembalikan semuanya; "Simpan" menyimpan dan badge hilang saat poin habis.
4. Cek tampilan **gelap** dan lebar **mobile** (375px) — panel tidak boleh meluber.

- [ ] **Step 8: Commit**

```bash
git add app/Services/StoryEngine.php resources/js/types/game.ts resources/js/components/game/CharacterStats.vue resources/js/pages/Character/Sheet.vue
git commit -m "UI alokasi poin atribut di halaman Karakter"
```

---

### Task 5: Umpan balik poin saat naik level di combat

**Files:**
- Modify: `app/Services/CombatService.php` (`resolveWin()`, blok `return ['rewards' => ...]` sekitar baris 456-464)
- Modify: `resources/js/components/game/CombatView.vue` (interface reward ~baris 18-26, baris tampilan ~361)
- Test: `tests/Feature/CombatServiceTest.php`

**Interfaces:**
- Consumes: `grantXp()` yang mengembalikan `levels_gained` (Task 2), `LevelService::POINTS_PER_LEVEL`.
- Produces: field `rewards.stat_points_gained: int` pada respons JSON combat.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/CombatServiceTest.php`, setelah `test_win_grants_rewards_and_advances_the_save()`. Memakai helper `scenario()` yang sudah ada di kelas itu — monster ber-HP 1 pasti mati oleh satu Pukul, dan `xp_reward` 100 tepat mencapai ambang level 1 (`xpForLevel(1)` = `floor(100 × 1^1.5)` = 100):

```php
    public function test_win_payload_reports_stat_points_gained_on_level_up(): void
    {
        $s = $this->scenario(monsterOverrides: ['max_hp' => 1, 'xp_reward' => 100]);
        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();

        $res = $combat->act($session, 'skill', $s['skill']->id);

        $this->assertTrue($res['rewards']['leveled_up']);
        $this->assertSame(2, $res['rewards']['new_level']);
        $this->assertSame(LevelService::POINTS_PER_LEVEL, $res['rewards']['stat_points_gained']);
        $this->assertSame(LevelService::POINTS_PER_LEVEL, $s['char']->fresh()->pending_stat_points);
    }

    public function test_win_payload_reports_zero_stat_points_without_a_level_up(): void
    {
        $s = $this->scenario(monsterOverrides: ['max_hp' => 1, 'xp_reward' => 10]);
        $combat = app(CombatService::class);
        $combat->start($s['char'], $s['node']);
        $session = $s['char']->activeCombat()->first();

        $res = $combat->act($session, 'skill', $s['skill']->id);

        $this->assertFalse($res['rewards']['leveled_up']);
        $this->assertSame(0, $res['rewards']['stat_points_gained']);
    }
```

Tambahkan `use App\Services\LevelService;` di bagian import file test itu.

- [ ] **Step 2: Jalankan test, pastikan gagal**

```bash
php artisan test --filter=CombatServiceTest
```

Harapan: FAIL — "Undefined array key \"stat_points_gained\"".

- [ ] **Step 3: Tambahkan field di payload**

Di `app/Services/CombatService.php`, dalam `resolveWin()`, ubah blok rewards:

```php
            'rewards' => [
                'xp' => $monster->xp_reward,
                'gold' => $gold,
                'items' => $loot,
                'leveled_up' => $xp['leveled_up'],
                'new_level' => $xp['new_level'],
                'stat_points_gained' => $xp['levels_gained'] * LevelService::POINTS_PER_LEVEL,
            ],
```

Tanpa import tambahan: `CombatService` dan `LevelService` sama-sama di namespace `App\Services`.

- [ ] **Step 4: Tampilkan di `CombatView.vue`**

Di interface reward (sekitar baris 18-26), tambahkan:

```ts
    stat_points_gained: number;
```

Ganti baris tampilannya (sekitar baris 361):

```vue
                    <span v-if="rewards.leveled_up" class="font-semibold text-violet-500">
                        Naik level! Kini level {{ rewards.new_level }}
                        <span v-if="rewards.stat_points_gained"> · +{{ rewards.stat_points_gained }} poin atribut</span>
                    </span>
```

- [ ] **Step 5: Jalankan test & build**

```bash
php artisan test --filter=CombatServiceTest
```

Harapan: PASS.

```bash
npm run build
```

Harapan: sukses.

- [ ] **Step 6: Commit**

```bash
git add app/Services/CombatService.php resources/js/components/game/CombatView.vue tests/Feature/CombatServiceTest.php
git commit -m "Pesan naik level di combat menyebut poin atribut yang didapat"
```

---

### Task 6: Respec di guild (service, rute, controller)

**Files:**
- Modify: `app/Services/LevelService.php` (tambah `resetAllocation()`)
- Modify: `app/Services/TownService.php` (konstanta + `respecCost()`, `needsRespec()`, `respec()`)
- Modify: `app/Http/Controllers/TownController.php` (constructor sudah punya `TownService`; tambah method `respec()`)
- Modify: `routes/web.php` (setelah baris `town.mission.accept`, ~baris 52)
- Test: `tests/Feature/StatAllocationTest.php`

**Interfaces:**
- Consumes: `LevelService::poolFor()`, `LevelService::ATTRIBUTES` (Task 2); `TownController::authorizePlace()` (sudah ada, private).
- Produces:
  - `LevelService::resetAllocation(Character $character): void` — set keenam atribut ke 1, **set** `pending_stat_points = poolFor(level)`, simpan.
  - `LevelService::hasAllocation(Character $character): bool` — true bila ada atribut ≠ 1 atau pool tidak utuh.
  - `TownService::RESPEC_CATEGORIES` — `public const array` = `['adventurer_guild', 'merchant_guild']`
  - `TownService::respecCost(Character $character): int` — `20 * max(1, level)`
  - `TownService::needsRespec(Character $character): bool` — false bila keenam atribut sudah 1 **dan** pool sudah utuh
  - `TownService::respec(Character $character, Place $place): array{cost: int, points: int}` — dipakai Task 7 untuk pesan flash

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/StatAllocationTest.php`. Tambahkan dulu helper kota (salin pola dari `tests/Feature/TownTest.php`) di dalam kelas:

```php
    private function makeGuildCity(): \App\Models\City
    {
        $country = \App\Models\Country::create(['slug' => 'respec-land', 'name' => 'Tanah Uji']);
        $province = $country->provinces()->create(['slug' => 'respec-prov', 'name' => 'Provinsi']);

        return $province->cities()->create(['slug' => 'respec-city', 'name' => 'Kota Uji']);
    }
```

Lalu test-testnya:

```php
    public function test_respec_at_the_guild_resets_attributes_and_refunds_the_whole_pool(): void
    {
        $city = $this->makeGuildCity();
        $guild = $city->places()->create(['category' => 'adventurer_guild', 'slug' => 'guild', 'name' => 'Guild Petualang']);
        [$user, $char] = $this->player([
            'level' => 3, 'gold' => 200, 'city_id' => $city->id,
            'strength' => 9, 'luck' => 3, 'pending_stat_points' => 0,
        ]);

        $this->actingAs($user)->post(route('town.respec', $guild->slug))
            ->assertRedirect()->assertSessionHas('success');

        $char->refresh();
        $this->assertSame(140, $char->gold); // 200 - (20 * level 3)
        $this->assertSame(1, $char->strength);
        $this->assertSame(1, $char->luck);
        $this->assertSame(10, $char->pending_stat_points); // 5 * (3 - 1)
    }

    public function test_respec_is_blocked_without_enough_gold(): void
    {
        $city = $this->makeGuildCity();
        $guild = $city->places()->create(['category' => 'adventurer_guild', 'slug' => 'guild', 'name' => 'Guild']);
        [$user, $char] = $this->player(['level' => 3, 'gold' => 10, 'city_id' => $city->id, 'strength' => 9]);

        $this->actingAs($user)->post(route('town.respec', $guild->slug))
            ->assertRedirect()->assertSessionHas('error');

        $char->refresh();
        $this->assertSame(10, $char->gold);
        $this->assertSame(9, $char->strength);
    }

    public function test_respec_is_rejected_outside_a_guild(): void
    {
        $city = $this->makeGuildCity();
        $inn = $city->places()->create(['category' => 'inn', 'slug' => 'inn', 'name' => 'Penginapan']);
        [$user, $char] = $this->player(['level' => 3, 'gold' => 200, 'city_id' => $city->id, 'strength' => 9]);

        $this->actingAs($user)->post(route('town.respec', $inn->slug))
            ->assertRedirect()->assertSessionHas('error');

        $char->refresh();
        $this->assertSame(200, $char->gold);
        $this->assertSame(9, $char->strength);
    }

    public function test_respec_is_forbidden_in_another_city(): void
    {
        $home = $this->makeGuildCity();
        $other = $home->province->cities()->create(['slug' => 'kota-lain', 'name' => 'Kota Lain']);
        $guild = $other->places()->create(['category' => 'adventurer_guild', 'slug' => 'guild-lain', 'name' => 'Guild Lain']);
        [$user] = $this->player(['level' => 3, 'gold' => 200, 'city_id' => $home->id, 'strength' => 9]);

        $this->actingAs($user)->post(route('town.respec', $guild->slug))->assertForbidden();
    }

    public function test_respec_is_rejected_when_nothing_is_allocated(): void
    {
        $city = $this->makeGuildCity();
        $guild = $city->places()->create(['category' => 'adventurer_guild', 'slug' => 'guild', 'name' => 'Guild']);
        // Atribut sudah baseline & pool sudah utuh → tak ada yang perlu diatur ulang.
        [$user, $char] = $this->player(['level' => 3, 'gold' => 200, 'city_id' => $city->id, 'pending_stat_points' => 10]);

        $this->actingAs($user)->post(route('town.respec', $guild->slug))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(200, $char->fresh()->gold); // emas tidak hangus
    }

    public function test_respec_twice_does_not_double_the_pool(): void
    {
        $city = $this->makeGuildCity();
        $guild = $city->places()->create(['category' => 'adventurer_guild', 'slug' => 'guild', 'name' => 'Guild']);
        [$user, $char] = $this->player(['level' => 3, 'gold' => 500, 'city_id' => $city->id, 'strength' => 9]);

        $this->actingAs($user)->post(route('town.respec', $guild->slug))->assertSessionHas('success');
        $this->assertSame(10, $char->fresh()->pending_stat_points);

        // Yang kedua ditolak (tak ada yang perlu diatur ulang) — pool tetap 10.
        $this->actingAs($user)->post(route('town.respec', $guild->slug))->assertSessionHas('error');
        $this->assertSame(10, $char->fresh()->pending_stat_points);
    }

    public function test_respec_also_works_at_the_merchant_guild(): void
    {
        $city = $this->makeGuildCity();
        $guild = $city->places()->create(['category' => 'merchant_guild', 'slug' => 'guild-dagang', 'name' => 'Guild Merchant']);
        [$user, $char] = $this->player(['level' => 2, 'gold' => 100, 'city_id' => $city->id, 'vitality' => 6]);

        $this->actingAs($user)->post(route('town.respec', $guild->slug))->assertSessionHas('success');

        $char->refresh();
        $this->assertSame(1, $char->vitality);
        $this->assertSame(5, $char->pending_stat_points);
        $this->assertSame(60, $char->gold); // 100 - (20 * level 2)
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```bash
php artisan test --filter=StatAllocationTest
```

Harapan: FAIL — rute `town.respec` belum ada.

- [ ] **Step 3: Tambahkan `resetAllocation()` ke `LevelService`**

Setelah `allocate()`:

```php
    /**
     * Buang seluruh alokasi: atribut kembali ke baseline 1 dan pool seumur hidup
     * dikembalikan utuh. SET (bukan tambah), jadi memanggilnya dua kali aman.
     */
    public function resetAllocation(Character $character): void
    {
        DB::transaction(function () use ($character) {
            foreach (self::ATTRIBUTES as $attr) {
                $character->{$attr} = 1;
            }
            $character->pending_stat_points = $this->poolFor($character->level);
            $character->save();
        });
    }

    /** Apakah karakter masih punya alokasi yang bisa dibuang. */
    public function hasAllocation(Character $character): bool
    {
        foreach (self::ATTRIBUTES as $attr) {
            if ((int) $character->{$attr} !== 1) {
                return true;
            }
        }

        return (int) $character->pending_stat_points !== $this->poolFor($character->level);
    }
```

- [ ] **Step 4: Tambahkan respec ke `TownService`**

Tanpa import tambahan: `TownService` dan `LevelService` sama-sama di namespace `App\Services`. Tambahkan constructor injection di `app/Services/TownService.php` (kelas ini belum punya constructor — letakkan di atas konstanta):

```php
    public function __construct(private LevelService $levels) {}
```

Tambahkan konstanta di samping `REST_COST_PER_LEVEL`:

```php
    /** Biaya latih ulang atribut per level karakter (tunable). */
    private const RESPEC_COST_PER_LEVEL = 20;

    /** Kategori Tempat yang melayani latih ulang atribut. */
    public const RESPEC_CATEGORIES = ['adventurer_guild', 'merchant_guild'];
```

Tambahkan method setelah `rest()`:

```php
    /** Biaya latih ulang atribut (skala level). */
    public function respecCost(Character $character): int
    {
        return self::RESPEC_COST_PER_LEVEL * max(1, (int) $character->level);
    }

    /** Apakah ada alokasi yang bisa diatur ulang (kalau tidak, jangan tagih emas). */
    public function needsRespec(Character $character): bool
    {
        return $this->levels->hasAllocation($character);
    }

    /**
     * Latih ulang atribut di guild: buang alokasi, kembalikan seluruh pool,
     * dengan biaya emas. Ditolak bila bukan guild, emas kurang, atau tidak ada
     * yang perlu diatur ulang.
     *
     * @return array{cost: int, points: int}
     */
    public function respec(Character $character, Place $place): array
    {
        abort_unless(in_array($place->category, self::RESPEC_CATEGORIES, true), 422, 'Tempat ini tidak melayani latih ulang atribut.');
        abort_unless($this->needsRespec($character), 422, 'Tidak ada alokasi atribut yang perlu diatur ulang.');

        $cost = $this->respecCost($character);
        abort_if($character->gold < $cost, 422, "Emasmu tidak cukup untuk berlatih ulang (butuh {$cost} emas).");

        return DB::transaction(function () use ($character, $cost) {
            $character->gold -= $cost;
            $character->save();
            $this->levels->resetAllocation($character);

            return ['cost' => $cost, 'points' => (int) $character->pending_stat_points];
        });
    }
```

- [ ] **Step 5: Tambahkan controller & rute**

Di `app/Http/Controllers/TownController.php`, tambahkan method setelah `rest()`:

```php
    /** Latih ulang atribut di guild. */
    public function respec(Request $request, Place $place): RedirectResponse
    {
        $character = $request->user()->character;
        if (! $character) {
            return redirect()->route('character.create');
        }
        $this->authorizePlace($character, $place);

        try {
            $result = $this->town->respec($character, $place);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Kamu berlatih ulang ({$result['cost']} emas). {$result['points']} poin atribut siap dialokasikan.");
    }
```

Di `routes/web.php`, setelah baris `town.mission.accept`:

```php
    Route::post('town/{place:slug}/respec', [TownController::class, 'respec'])->name('town.respec');
```

- [ ] **Step 6: Jalankan test, pastikan lulus**

```bash
php artisan test --filter=StatAllocationTest
```

Harapan: PASS (19 test).

- [ ] **Step 7: Jalankan seluruh test**

```bash
php artisan test
```

Harapan: hijau. `TownService` kini punya constructor — kalau ada test yang meng-instansiasi `new TownService()` langsung, ganti ke `app(TownService::class)`.

- [ ] **Step 8: Buktikan test menangkap regresi**

Ganti sementara `abort_unless($this->needsRespec($character), ...)` menjadi `abort_unless(true, ...)`, lalu:

```bash
php artisan test --filter=test_respec_is_rejected_when_nothing_is_allocated
```

Harapan: FAIL (emas terpotong padahal tak ada yang direset). Pulihkan, pastikan hijau.

Ulangi untuk `resetAllocation()`: ganti `$character->pending_stat_points = $this->poolFor(...)` menjadi `+=`, lalu jalankan `--filter=test_respec_twice_does_not_double_the_pool` — harus FAIL. Pulihkan.

- [ ] **Step 9: Commit**

```bash
git add app/Services/LevelService.php app/Services/TownService.php app/Http/Controllers/TownController.php routes/web.php tests/Feature/StatAllocationTest.php
git commit -m "Latih ulang atribut di guild: buang alokasi, kembalikan pool, tagih emas"
```

---

### Task 7: Panel "Latih Ulang Atribut" di halaman guild

**Files:**
- Modify: `app/Http/Controllers/TownController.php` (`place()`, di dalam blok `if (isset(RankService::GUILD_AFFILIATION[$place->category]))`)
- Modify: `resources/js/pages/Town/Place.vue` (props + panel baru)
- Test: manual (browser) + `php artisan test`

**Interfaces:**
- Consumes: `TownService::respecCost()`, `TownService::needsRespec()` (Task 6); rute `town.respec` (Task 6).
- Produces: props `respec_cost?: number` dan `can_respec?: boolean` di halaman `Town/Place`.

- [ ] **Step 1: Kirim datanya dari controller**

Di `place()`, di dalam blok guild (setelah `$data['missions'] = ...`), tambahkan:

```php
            $data['respec_cost'] = $this->town->respecCost($character);
            $data['can_respec'] = $this->town->needsRespec($character) && $character->gold >= $this->town->respecCost($character);
```

- [ ] **Step 2: Tambahkan props di `Place.vue`**

Di `defineProps`, tambahkan setelah `missions?: Mission[];`:

```ts
    respec_cost?: number;
    can_respec?: boolean;
```

Tambahkan `Repeat` ke daftar import ikon `lucide-vue-next` di file itu.

- [ ] **Step 3: Tambahkan panelnya**

Di dalam `<template v-if="place.is_guild">`, setelah blok papan misi (tepat sebelum `</template>` penutupnya), tambahkan:

```vue
                <div class="rounded-xl border bg-card p-5">
                    <div class="flex items-center gap-2 font-semibold"><Repeat class="h-5 w-5 text-violet-400" /> Latih Ulang Atribut</div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Guild melatihmu dari awal: seluruh atribut kembali ke dasar dan semua poinmu bisa dialokasikan ulang, dengan biaya
                        <span class="font-medium text-amber-600">{{ respec_cost }} emas</span>.
                    </p>
                    <div class="mt-4 flex items-center gap-3">
                        <Button variant="outline" :disabled="!can_respec || processing" @click="post(route('town.respec', place.slug))">
                            <Repeat class="mr-2 h-4 w-4" /> Latih Ulang ({{ respec_cost }} emas)
                        </Button>
                        <span v-if="character.gold < (respec_cost ?? 0)" class="text-xs text-red-500">Emasmu tidak cukup.</span>
                        <span v-else-if="!can_respec" class="text-xs text-muted-foreground">Belum ada atribut yang dialokasikan.</span>
                    </div>
                </div>
```

- [ ] **Step 4: Build & test**

```bash
npm run build
```

Harapan: sukses.

```bash
php artisan test
```

Harapan: hijau.

- [ ] **Step 5: Verifikasi di browser**

Server dev `webio-8001`. Sebagai `player1`:

1. Alokasikan beberapa poin di `/character`.
2. Buka guild di `/town` → panel "Latih Ulang Atribut" tampil dengan biaya `20 × level`.
3. Klik → flash sukses, emas terpotong, dan `/character` menunjukkan atribut kembali 1 dengan poin utuh.
4. Klik lagi → tombolnya nonaktif dengan keterangan "Belum ada atribut yang dialokasikan."
5. Cek tampilan gelap + 375px.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/TownController.php resources/js/pages/Town/Place.vue
git commit -m "Panel latih ulang atribut di halaman guild"
```

---

### Task 8: Field `pending_stat_points` di Panel Dewa

**Files:**
- Modify: `app/Http/Controllers/Admin/PlayerController.php` (`edit()` payload karakter, `update()` daftar field)
- Modify: `resources/js/pages/admin/players/Form.vue` (interface `CharacterData`, grup "Progres")
- Test: `tests/Feature/AdminPlayerTest.php`

**Interfaces:**
- Consumes: kolom `pending_stat_points` (Task 1).
- Produces: —

**Ketergantungan yang mudah terlewat:** field ini masuk ke `foreach` yang memberi aturan `['required', 'integer', 'min:0']`, jadi **setiap** test yang mem-PUT `admin.players.update` tanpa `character.pending_stat_points` akan gagal validasi. Helper `charPayload()` di `tests/Feature/AdminPlayerTest.php` (baris ~45) harus ikut ditambah di Step 3 — bukan opsional.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/AdminPlayerTest.php`, setelah `test_superadmin_can_update_a_player_account_and_character()`, memakai helper `superadmin()`, `playerWithProgress()`, dan `charPayload()` yang sudah ada:

```php
    public function test_dewa_can_set_pending_stat_points(): void
    {
        $admin = $this->superadmin();
        [$user, $char] = $this->playerWithProgress();

        $this->actingAs($admin)->put(route('admin.players.update', $user->id), [
            'name' => $user->name, 'email' => $user->email, 'role' => 'player', 'job' => null,
            'character' => array_merge($this->charPayload($char), ['pending_stat_points' => 12]),
        ])->assertRedirect(route('admin.players.index'))->assertSessionHasNoErrors();

        $this->assertSame(12, $char->fresh()->pending_stat_points);
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```bash
php artisan test --filter=test_dewa_can_set_pending_stat_points
```

Harapan: FAIL — nilainya tetap 0 karena field itu belum divalidasi, jadi tidak ikut masuk `$data['character']` yang di-`update()`.

- [ ] **Step 3: Tambahkan di controller**

Di `edit()`, dalam array karakter, tambahkan setelah `'intelligence' => ..., 'vitality' => ..., 'luck' => $c->luck,`:

```php
                    'pending_stat_points' => $c->pending_stat_points,
```

Di `update()`, tambahkan `'pending_stat_points'` ke daftar field di dalam `foreach`:

```php
            foreach (['level', 'xp', 'gold', 'hp', 'max_hp', 'sp', 'max_sp', 'mp', 'max_mp',
                'attack', 'defense', 'magic_attack', 'magic_defense',
                'strength', 'agility', 'dexterity', 'intelligence', 'vitality', 'luck',
                'pending_stat_points'] as $field) {
```

Lalu di `tests/Feature/AdminPlayerTest.php`, helper `charPayload()` — tambahkan setelah `'intelligence' => ..., 'vitality' => ..., 'luck' => $c->luck,`:

```php
            'pending_stat_points' => $c->pending_stat_points,
```

Tanpa baris ini, `test_superadmin_can_update_a_player_account_and_character` dan `test_superadmin_can_change_a_players_role` akan gagal validasi.

- [ ] **Step 4: Tambahkan di form**

Di `resources/js/pages/admin/players/Form.vue`, pada `interface CharacterData` tambahkan setelah baris atribut:

```ts
    pending_stat_points: number;
```

Pada `charGroups`, grup "Progres", tambahkan field:

```ts
        { key: 'level', label: 'Level' }, { key: 'xp', label: 'XP' }, { key: 'gold', label: 'Emas' },
        { key: 'pending_stat_points', label: 'Poin Atribut' },
```

- [ ] **Step 5: Jalankan test & build**

```bash
php artisan test --filter=AdminPlayerTest
```

Harapan: PASS — seluruh kelas, bukan hanya test baru (membuktikan `charPayload()` sudah ikut diperbarui).

```bash
npm run build
```

Harapan: sukses.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/PlayerController.php resources/js/pages/admin/players/Form.vue tests/Feature/AdminPlayerTest.php
git commit -m "Panel Dewa bisa menyetel poin atribut yang belum dialokasikan"
```

---

### Task 9: Verifikasi penuh + dokumentasi

**Files:**
- Modify: `GAME.md` (bagian **Combat (model serangan)** paragraf atribut RPG, bagian **Kota — layer pemain**, bagian **Rank & Misi** atau bagian baru, tabel **Arsitektur kode**, bagian **Test**, **Peta jalan**)
- Test: seluruh suite + verifikasi manual MySQL & browser

**Interfaces:**
- Consumes: semuanya.
- Produces: —

- [ ] **Step 1: Jalankan seluruh test**

```bash
php artisan test
```

Harapan: semua hijau. Catat jumlah test barunya (244 + jumlah test yang ditambahkan) — angka ini masuk ke `GAME.md`.

- [ ] **Step 2: Verifikasi migrasi dua arah di MySQL**

Butuh `mysqld` hidup. **Backup dulu kalau database `webio` berisi data yang kamu sayangi.**

Sebelum apa-apa, catat keadaan awal:

```bash
php artisan tinker --execute="\App\Models\Character::select('id','level','strength','pending_stat_points')->get()->each(fn(\$c) => print(\"{\$c->id} lv{\$c->level} str{\$c->strength} pts{\$c->pending_stat_points}\n\"));"
```

Turunkan lalu naikkan lagi:

```bash
php artisan migrate:rollback --step=1
```

Harapan: sukses; kolom `pending_stat_points` hilang dan atribut tiap karakter = level-nya. Periksa:

```bash
php artisan tinker --execute="\App\Models\Character::select('id','level','strength')->get()->each(fn(\$c) => print(\"{\$c->id} lv{\$c->level} str{\$c->strength}\n\"));"
```

Naikkan kembali:

```bash
php artisan migrate
```

Harapan: atribut kembali 1 dan `pending_stat_points` = `5 × (level − 1)` untuk tiap karakter. Verifikasi dengan perintah tinker pertama di atas. **Ini satu-satunya bukti backfill migrasi bekerja** — suite otomatis tidak mencakupnya.

- [ ] **Step 3: Verifikasi impor konten tidak terpengaruh**

```bash
php artisan game:import
```

Harapan: `Imported: 6 quests, 27 nodes, 22 choices, 3 monsters, 2 skills, 2 spells, 20 items.`

- [ ] **Step 4: Main sampai naik level di browser**

Server dev `webio-8001`. Sebagai `player1`:

1. Ambil misi combat di guild, menangkan pertarungannya sampai naik level.
2. Pastikan pesan kemenangan menyebut "+5 poin atribut".
3. Buka `/character`, alokasikan poinnya, pastikan efek turunan (dmg fisik/kritikal/hindar) ikut berubah.
4. Bertarung lagi dan pastikan atribut baru benar-benar terpakai (mis. taruh semua ke AGI, lihat "Menghindar!" muncul).
5. Latih ulang di guild, pastikan poinnya kembali utuh.

- [ ] **Step 5: Perbarui `GAME.md`**

Sunting bagian-bagian ini (jangan membuat file dokumentasi baru):

1. **Combat (model serangan)** — paragraf "Atribut RPG": ganti "Default karakter baru = 1, naik +1 tiap level" menjadi penjelasan alokasi: 5 poin per level, ditumpuk di `characters.pending_stat_points`, dialokasikan pemain di halaman Karakter, tanpa cap.
2. Bagian baru **Alokasi Poin Atribut** (letakkan setelah **Perolehan Skill/Sihir**): kurva 5/level, pool `5 × (level − 1)`, payload `{points: {...}}`, aturan penolakan, dan respec di guild `20 × level` dengan syarat "ada yang perlu diatur ulang".
3. **Kota — layer pemain** — tambahkan satu baris: guild kini juga melayani latih ulang atribut.
4. **Arsitektur kode** — baris baru: `Alokasi & respec poin atribut | app/Services/LevelService.php · TownService::respec · CharacterController@allocate · resources/js/components/game/CharacterStats.vue`.
5. **Test** — perbarui jumlah test dan tambahkan `StatAllocation` ke daftar.
6. **Peta jalan** — di bawah Phase 2, tandai "kurva level" sebagai sudah jalan lewat alokasi poin; catat tiga sub-proyek M2 yang tersisa (rank reward, misi berulang/harian, achievement) sebagai lanjutan.

- [ ] **Step 6: Commit**

```bash
git add GAME.md
git commit -m "GAME.md: dokumentasikan alokasi poin atribut & respec guild"
```

- [ ] **Step 7: Laporkan hasil verifikasi**

Laporkan apa adanya: jumlah test, hasil migrasi dua arah di MySQL, hasil `game:import`, dan apa yang dilihat di browser. Kalau ada langkah yang dilewati atau gagal, sebutkan langkah mana dan kenapa — jangan mengklaim selesai untuk yang belum diverifikasi.
