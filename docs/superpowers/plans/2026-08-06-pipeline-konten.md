# M1 Pipeline Konten — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menulis satu misi baru lengkap (narasi + monster + reward) cukup ~19 baris JSON, bukan 66 baris perancah.

**Architecture:** Satu kelas `QuestTemplate` mengembangkan bentuk ringkas (`hunt`/`errand`) jadi struktur node long-form **sebelum** masuk jalur upsert `game:import` yang sudah ada. Stat monster diturunkan rumus `Monster::statsForLevel()` di dalam `upsertMonster()`, dan field eksplisit selalu menimpanya. Runtime (`StoryEngine`, `CombatService`, editor Panel Dewa) tidak berubah sebaris pun — yang masuk DB tetap node biasa.

**Tech Stack:** Laravel 12, PHP 8.2, PHPUnit lewat `php artisan test` (SQLite in-memory), konten JSON di `database/content/`.

**Spec:** `docs/superpowers/specs/2026-08-06-pipeline-konten-design.md`

## Global Constraints

- PHP **8.2** — tanpa fitur 8.3+ (`json_validate()`, konstanta typed di trait, dll).
- **Tanpa dependency baru.** Tidak ada paket Composer/npm yang ditambahkan.
- Pesan galat & string yang terlihat penulis konten: **Bahasa Indonesia**, sebut **slug misi** atau **slug monster** yang bersalah.
- Nama field konten yang sudah ada **tidak diubah**: `affiliation`, `required_rank`, `min_level`, `order`, `is_published`, `cover_image`, `start_node`, `nodes`, `monsters`.
- Bentuk long-form (`nodes`) tetap didukung penuh. `database/content/quests/goblin-cave.json` **tidak boleh disentuh**.
- `game:import` tetap idempoten dan tetap satu transaksi: satu file rusak = seluruh import batal.
- **165 test yang sudah ada harus tetap lulus** di akhir setiap task.
- Struktur node yang diterima importer (jangan menyimpang): node = `{key, type, title?, body?, image?, monster?, payload?, choices?}`; choice = `{label, next?, requirements?, effects?, order?, is_auto?}`; tipe node yang dipakai plan ini: `narrative`, `combat`, `reward`, `ending`.
- Semua kode baru pakai `declare`-less style yang sudah dipakai repo (tanpa `declare(strict_types=1)`), komentar Bahasa Indonesia, `RuntimeException` untuk galat konten.

---

### Task 1: Rumus stat monster dari level

**Files:**
- Modify: `app/Models/Monster.php`
- Modify: `app/Console/Commands/ImportGameContent.php:194-206` (method `upsertMonster`)
- Test: `tests/Feature/MonsterScalingTest.php` (create)

**Interfaces:**
- Consumes: nothing (task pertama)
- Produces:
  - `Monster::statsForLevel(int $level): array` → kunci `max_hp`, `attack`, `defense`, `magic_attack`, `magic_defense`, `xp_reward`, `gold_reward` (semua `int`)
  - `ImportGameContent::upsertMonster(array $data): void` sekarang menerima kunci `level` dan memvalidasi field tak dikenal

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MonsterScalingTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Monster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonsterScalingTest extends TestCase
{
    use RefreshDatabase;

    public function test_level_one_matches_the_proven_balance_of_tikus_raksasa(): void
    {
        // Angka literal, BUKAN dibaca dari file konten — file itu ikut diubah
        // di task migrasi konten, jadi assertion tidak boleh memeriksa dirinya sendiri.
        $this->assertSame([
            'max_hp' => 3,
            'attack' => 1,
            'defense' => 0,
            'magic_attack' => 0,
            'magic_defense' => 0,
            'xp_reward' => 30,
            'gold_reward' => 10,
        ], Monster::statsForLevel(1));
    }

    public function test_stats_grow_with_level(): void
    {
        $lv5 = Monster::statsForLevel(5);

        $this->assertSame(11, $lv5['max_hp']);
        $this->assertSame(5, $lv5['attack']);
        $this->assertSame(2, $lv5['defense']);
        $this->assertSame(2, $lv5['magic_defense']);
        $this->assertSame(70, $lv5['xp_reward']);
        $this->assertSame(30, $lv5['gold_reward']);
    }

    public function test_level_below_one_is_clamped(): void
    {
        $this->assertSame(Monster::statsForLevel(1), Monster::statsForLevel(0));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MonsterScalingTest`
Expected: FAIL — `Call to undefined method App\Models\Monster::statsForLevel()`

- [ ] **Step 3: Implement the formula**

In `app/Models/Monster.php`, add inside the class:

```php
    /**
     * Stat monster yang diturunkan dari level. Level 1 sengaja dipas ke
     * keseimbangan `tikus-raksasa` yang sudah terbukti. Field eksplisit di
     * konten selalu menimpa hasil rumus ini (lihat ImportGameContent::upsertMonster).
     *
     * ponytail: rumus HP menganggap kekuatan serang pemain naik seiring level,
     * padahal damage berasal dari `power` skill — pemain level 5 yang cuma
     * menguasai Pukul (power 1) butuh 11 giliran. Kurva skill = milestone M2.
     *
     * @return array<string, int>
     */
    public static function statsForLevel(int $level): array
    {
        $level = max(1, $level);

        return [
            'max_hp' => 3 + 2 * ($level - 1),
            'attack' => $level,
            'defense' => intdiv($level - 1, 2),
            'magic_attack' => 0,
            'magic_defense' => intdiv($level - 1, 2),
            'xp_reward' => 20 + 10 * $level,
            'gold_reward' => 5 + 5 * $level,
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MonsterScalingTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Write the failing test for importer level support**

Append to `tests/Feature/MonsterScalingTest.php` (inside the class):

```php
    public function test_importer_fills_stats_from_level(): void
    {
        $this->importer()->upsertMonster([
            'slug' => 'tikus-uji', 'name' => 'Tikus Uji', 'level' => 1,
        ]);

        $monster = Monster::where('slug', 'tikus-uji')->firstOrFail();
        $this->assertSame(3, $monster->max_hp);
        $this->assertSame(1, $monster->attack);
        $this->assertSame(30, $monster->xp_reward);
        $this->assertSame(10, $monster->gold_reward);
        $this->assertSame('physical', $monster->attack_kind);
    }

    public function test_explicit_fields_beat_the_formula(): void
    {
        $this->importer()->upsertMonster([
            'slug' => 'goblin-uji', 'name' => 'Goblin Uji', 'level' => 1,
            'max_hp' => 5, 'attack' => 3, 'xp_reward' => 60, 'gold_reward' => 20,
        ]);

        $monster = Monster::where('slug', 'goblin-uji')->firstOrFail();
        $this->assertSame(5, $monster->max_hp);   // bukan 3
        $this->assertSame(3, $monster->attack);   // bukan 1
        $this->assertSame(60, $monster->xp_reward);
        $this->assertSame(0, $monster->defense);  // tetap dari rumus
    }

    public function test_monster_without_level_still_requires_hp_and_attack(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('`max_hp` wajib');
        $this->importer()->upsertMonster(['slug' => 'rusak', 'name' => 'Rusak']);
    }

    public function test_unknown_monster_field_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('magik_attack');
        $this->importer()->upsertMonster([
            'slug' => 'salah-tulis', 'name' => 'Salah Tulis', 'level' => 2, 'magik_attack' => 5,
        ]);
    }

    public function test_level_must_be_a_positive_integer(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('`level` harus integer');
        $this->importer()->upsertMonster(['slug' => 'aneh', 'name' => 'Aneh', 'level' => 'tiga']);
    }

    /**
     * `upsertMonster` privat — dipanggil lewat reflection supaya test tidak
     * perlu menulis file JSON sementara untuk memeriksa satu blok monster.
     */
    private function importer(): object
    {
        $command = app(\App\Console\Commands\ImportGameContent::class);

        return new class($command)
        {
            public function __construct(private object $command) {}

            public function upsertMonster(array $data): void
            {
                $method = new \ReflectionMethod($this->command, 'upsertMonster');
                $method->setAccessible(true);
                $method->invoke($this->command, $data);
            }
        };
    }
```

- [ ] **Step 6: Run test to verify it fails**

Run: `php artisan test --filter=MonsterScalingTest`
Expected: FAIL — 5 test baru gagal (`level` diabaikan, `Undefined array key "max_hp"` bukan `RuntimeException`)

- [ ] **Step 7: Rewrite upsertMonster**

Replace `upsertMonster` in `app/Console/Commands/ImportGameContent.php` with:

```php
    /**
     * Field yang boleh muncul di blok monster konten. Salah tulis harus gagal
     * keras — field yang diam-diam diabaikan itu jebakan saat menyeimbangkan.
     */
    private const MONSTER_FIELDS = [
        'slug', 'name', 'level', 'image', 'max_hp', 'attack', 'defense',
        'magic_attack', 'magic_defense', 'attack_kind', 'xp_reward', 'gold_reward', 'loot',
    ];

    /** @param array<string, mixed> $data */
    private function upsertMonster(array $data): void
    {
        $slug = $data['slug'] ?? null;
        if (! is_string($slug) || $slug === '') {
            throw new RuntimeException('Ada blok monster tanpa `slug`.');
        }
        if (! isset($data['name'])) {
            throw new RuntimeException("Monster `{$slug}`: `name` wajib diisi.");
        }

        $unknown = array_diff(array_keys($data), self::MONSTER_FIELDS);
        if ($unknown !== []) {
            throw new RuntimeException("Monster `{$slug}`: field tak dikenal — ".implode(', ', $unknown).'.');
        }

        if (array_key_exists('level', $data) && (! is_int($data['level']) || $data['level'] < 1)) {
            throw new RuntimeException("Monster `{$slug}`: `level` harus integer ≥ 1.");
        }

        // Rumus level jadi dasar; field yang ditulis eksplisit menimpanya.
        $stats = isset($data['level']) ? Monster::statsForLevel($data['level']) : [];
        foreach (['max_hp', 'attack', 'defense', 'magic_attack', 'magic_defense', 'xp_reward', 'gold_reward'] as $field) {
            if (isset($data[$field])) {
                $stats[$field] = (int) $data[$field];
            }
        }

        foreach (['max_hp', 'attack'] as $field) {
            if (! isset($stats[$field])) {
                throw new RuntimeException("Monster `{$slug}`: `{$field}` wajib bila `level` tidak diisi.");
            }
        }

        Monster::updateOrCreate(['slug' => $slug], [
            'name' => $data['name'],
            'image' => $data['image'] ?? null,
            'max_hp' => $stats['max_hp'],
            'attack' => $stats['attack'],
            'defense' => $stats['defense'] ?? 0,
            'magic_attack' => $stats['magic_attack'] ?? 0,
            'magic_defense' => $stats['magic_defense'] ?? 0,
            'attack_kind' => $data['attack_kind'] ?? 'physical',
            'xp_reward' => $stats['xp_reward'] ?? 0,
            'gold_reward' => $stats['gold_reward'] ?? 0,
            'loot' => $data['loot'] ?? null,
        ]);
    }
```

Add the import at the top of the file, after the existing `use` statements:

```php
use RuntimeException;
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --filter=MonsterScalingTest`
Expected: PASS (8 tests)

Run: `php artisan test`
Expected: PASS — 165 test lama + 8 baru = 173

- [ ] **Step 9: Commit**

```bash
git add app/Models/Monster.php app/Console/Commands/ImportGameContent.php tests/Feature/MonsterScalingTest.php
git commit -m "Stat monster bisa diturunkan dari level, field eksplisit menimpanya"
```

---

### Task 2: QuestTemplate — arketipe `hunt`

**Files:**
- Create: `app/Services/QuestTemplate.php`
- Test: `tests/Feature/QuestTemplateTest.php` (create)

**Interfaces:**
- Consumes: `Monster::statsForLevel()` tidak dipanggil di sini — blok monster diteruskan apa adanya ke `monsters` dan diselesaikan `upsertMonster` (Task 1).
- Produces:
  - `QuestTemplate::expand(array $quest): array` — bila ada `hunt`/`errand`, kembalikan array yang sama dengan `nodes` + `monsters` + `start_node` terisi dan key arketipe dibuang; bila ada `nodes`, kembalikan apa adanya.
  - Kunci node yang dihasilkan `hunt`: `intro`, `fight`, `win` (hanya bila ada `reward`), `ending_win`, `lose`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/QuestTemplateTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\QuestTemplate;
use Tests\TestCase;

class QuestTemplateTest extends TestCase
{
    /** Misi `hunt` minimal yang dipakai berulang di test ini. */
    private function huntQuest(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'tikus-gudang',
            'title' => 'Tikus Gudang',
            'affiliation' => 'adventurer',
            'hunt' => array_merge([
                'monster' => ['slug' => 'tikus-raksasa', 'name' => 'Tikus Raksasa', 'level' => 1],
                'intro' => 'Bau apek dan cericit menyambutmu.',
                'fight' => 'Tikus itu melompat dengan gigi kuning terbuka.',
                'win' => 'Tikus itu kabur ke lubang dindingnya.',
                'lose' => 'Memalukan — kau mundur dari seekor tikus.',
                'reward' => ['xp' => 15, 'gold' => 15],
            ], $overrides),
        ]);
    }

    /** @return array<string, array<string, mixed>> node dikunci `key` */
    private function nodesByKey(array $expanded): array
    {
        return collect($expanded['nodes'])->keyBy('key')->all();
    }

    public function test_hunt_expands_into_five_wired_nodes(): void
    {
        $expanded = QuestTemplate::expand($this->huntQuest());
        $nodes = $this->nodesByKey($expanded);

        $this->assertSame('intro', $expanded['start_node']);
        $this->assertSame(['intro', 'fight', 'win', 'ending_win', 'lose'], array_keys($nodes));

        $this->assertSame('narrative', $nodes['intro']['type']);
        $this->assertSame('combat', $nodes['fight']['type']);
        $this->assertSame('reward', $nodes['win']['type']);
        $this->assertSame('ending', $nodes['ending_win']['type']);
        $this->assertSame('ending', $nodes['lose']['type']);

        $this->assertSame('fight', $nodes['intro']['choices'][0]['next']);
        $this->assertSame('tikus-raksasa', $nodes['fight']['monster']);
        $this->assertSame('win', $nodes['fight']['payload']['on_win_node_key']);
        $this->assertSame('lose', $nodes['fight']['payload']['on_lose_node_key']);
        $this->assertSame(['xp' => 15, 'gold' => 15], $nodes['win']['payload']);
        $this->assertSame('ending_win', $nodes['win']['choices'][0]['next']);
        $this->assertTrue($nodes['win']['choices'][0]['is_auto']);
        $this->assertSame('victory', $nodes['ending_win']['payload']['result']);
        $this->assertSame('defeat', $nodes['lose']['payload']['result']);
    }

    public function test_hunt_carries_the_monster_block_through(): void
    {
        $expanded = QuestTemplate::expand($this->huntQuest());

        $this->assertSame([
            ['slug' => 'tikus-raksasa', 'name' => 'Tikus Raksasa', 'level' => 1],
        ], $expanded['monsters']);
        $this->assertArrayNotHasKey('hunt', $expanded);
    }

    public function test_hunt_prose_lands_in_the_right_nodes(): void
    {
        $nodes = $this->nodesByKey(QuestTemplate::expand($this->huntQuest()));

        $this->assertSame('Bau apek dan cericit menyambutmu.', $nodes['intro']['body']);
        $this->assertSame('Tikus itu melompat dengan gigi kuning terbuka.', $nodes['fight']['body']);
        $this->assertSame('Tikus itu kabur ke lubang dindingnya.', $nodes['win']['body']);
        $this->assertSame('Memalukan — kau mundur dari seekor tikus.', $nodes['lose']['body']);
        // Prosa `win` TIDAK diulang di ending — pemain tak membaca teks sama dua kali.
        $this->assertNotSame($nodes['win']['body'], $nodes['ending_win']['body']);
    }

    public function test_hunt_without_reward_skips_the_reward_node(): void
    {
        $quest = $this->huntQuest();
        unset($quest['hunt']['reward']);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame(['intro', 'fight', 'ending_win', 'lose'], array_keys($nodes));
        $this->assertSame('ending_win', $nodes['fight']['payload']['on_win_node_key']);
        // Prosa `win` pindah ke ending karena node reward tidak dibuat.
        $this->assertSame('Tikus itu kabur ke lubang dindingnya.', $nodes['ending_win']['body']);
    }

    public function test_long_form_quests_pass_through_untouched(): void
    {
        $longForm = [
            'slug' => 'gua-goblin',
            'title' => 'Gua Goblin',
            'start_node' => 'intro',
            'nodes' => [['key' => 'intro', 'type' => 'narrative', 'body' => 'Mulut gua.']],
        ];

        $this->assertSame($longForm, QuestTemplate::expand($longForm));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=QuestTemplateTest`
Expected: FAIL — `Class "App\Services\QuestTemplate" not found`

- [ ] **Step 3: Implement QuestTemplate with the hunt archetype**

Create `app/Services/QuestTemplate.php`:

```php
<?php

namespace App\Services;

use RuntimeException;

/**
 * Mengembangkan bentuk misi RINGKAS (`hunt` / `errand`) jadi struktur node
 * long-form yang dimengerti importer. Dipanggil sebelum jalur upsert biasa,
 * jadi runtime (StoryEngine/CombatService/Panel Dewa) tidak pernah tahu
 * template ini ada — yang masuk DB tetap node normal.
 *
 * Murni fungsi: tidak menyentuh DB, jadi bisa diuji tanpa migrasi.
 */
class QuestTemplate
{
    private const DEFAULT_LOSE = 'Kau tumbang. Pulihkan diri lalu coba lagi.';

    private const DEFAULT_OUTRO = 'Tugas selesai. Laporkan hasilnya ke guild.';

    /**
     * @param  array<string, mixed>  $quest
     * @return array<string, mixed>
     */
    public static function expand(array $quest): array
    {
        $slug = $quest['slug'] ?? '(tanpa slug)';
        $shapes = array_values(array_filter(['hunt', 'errand'], fn (string $k) => isset($quest[$k])));

        if (count($shapes) > 1) {
            throw new RuntimeException("Misi `{$slug}`: pilih salah satu, `hunt` atau `errand`.");
        }
        if ($shapes === []) {
            return $quest; // long-form, biarkan apa adanya
        }
        if (isset($quest['nodes'])) {
            throw new RuntimeException("Misi `{$slug}`: bentuk ringkas tidak bisa dicampur dengan `nodes`.");
        }

        $shape = $shapes[0];
        $body = $quest[$shape];
        if (! is_array($body)) {
            throw new RuntimeException("Misi `{$slug}`: `{$shape}` harus berupa objek.");
        }
        unset($quest[$shape]);

        $title = (string) ($quest['title'] ?? $slug);
        $expanded = $shape === 'hunt'
            ? self::hunt($slug, $title, $body)
            : self::errand($slug, $title, $body);

        return array_merge($quest, $expanded);
    }

    /**
     * intro → fight → (win) → ending_win, plus ending kalah.
     *
     * @param  array<string, mixed>  $hunt
     * @return array<string, mixed>
     */
    private static function hunt(string $slug, string $title, array $hunt): array
    {
        $monster = $hunt['monster'] ?? null;
        if (! is_array($monster) || ! isset($monster['slug'])) {
            throw new RuntimeException("Misi `{$slug}`: `hunt.monster` wajib punya `slug`.");
        }

        $intro = self::prose($slug, 'hunt.intro', $hunt['intro'] ?? null);
        $fight = self::prose($slug, 'hunt.fight', $hunt['fight'] ?? null);
        $win = self::prose($slug, 'hunt.win', $hunt['win'] ?? null);
        $lose = self::prose($slug, 'hunt.lose', $hunt['lose'] ?? null, required: false);
        $outro = self::prose($slug, 'hunt.outro', $hunt['outro'] ?? null, required: false);

        $reward = $hunt['reward'] ?? null;
        $afterFight = $reward ? 'win' : 'ending_win';

        $nodes = [
            [
                'key' => 'intro',
                'type' => 'narrative',
                'title' => $intro['title'] ?? $title,
                'body' => $intro['body'],
                'choices' => [['label' => 'Hadapi', 'next' => 'fight']],
            ],
            [
                'key' => 'fight',
                'type' => 'combat',
                'title' => $fight['title'] ?? $monster['name'].'!',
                'body' => $fight['body'],
                'monster' => $monster['slug'],
                'payload' => ['on_win_node_key' => $afterFight, 'on_lose_node_key' => 'lose'],
            ],
        ];

        if ($reward) {
            $nodes[] = [
                'key' => 'win',
                'type' => 'reward',
                'title' => $win['title'] ?? 'Berhasil',
                'body' => $win['body'],
                'payload' => $reward,
                'choices' => [['label' => 'Lanjutkan', 'next' => 'ending_win', 'is_auto' => true]],
            ];
        }

        $nodes[] = self::endingWin($win, $outro, hasReward: (bool) $reward);
        $nodes[] = [
            'key' => 'lose',
            'type' => 'ending',
            'title' => $lose['title'] ?? 'Kalah',
            'body' => $lose['body'] ?? self::DEFAULT_LOSE,
            'payload' => ['result' => 'defeat'],
        ];

        return ['start_node' => 'intro', 'nodes' => $nodes, 'monsters' => [$monster]];
    }

    /**
     * Rangkaian narasi tanpa tarung: beat_1..beat_n → (win) → ending_win.
     *
     * @param  array<string, mixed>  $errand
     * @return array<string, mixed>
     */
    private static function errand(string $slug, string $title, array $errand): array
    {
        $beats = $errand['beats'] ?? null;
        if (! is_array($beats) || $beats === []) {
            throw new RuntimeException("Misi `{$slug}`: `errand.beats` wajib berisi minimal satu adegan.");
        }

        $win = self::prose($slug, 'errand.win', $errand['win'] ?? null);
        $outro = self::prose($slug, 'errand.outro', $errand['outro'] ?? null, required: false);

        $reward = $errand['reward'] ?? null;
        $afterBeats = $reward ? 'win' : 'ending_win';

        $nodes = [];
        $beats = array_values($beats);
        $count = count($beats);
        foreach ($beats as $i => $raw) {
            $n = $i + 1;
            $beat = self::prose($slug, "errand.beats[{$i}]", $raw);
            $nodes[] = [
                'key' => "beat_{$n}",
                'type' => 'narrative',
                'title' => $beat['title'] ?? $title,
                'body' => $beat['body'],
                'choices' => [[
                    'label' => 'Lanjutkan',
                    'next' => $n < $count ? 'beat_'.($n + 1) : $afterBeats,
                ]],
            ];
        }

        if ($reward) {
            $nodes[] = [
                'key' => 'win',
                'type' => 'reward',
                'title' => $win['title'] ?? 'Berhasil',
                'body' => $win['body'],
                'payload' => $reward,
                'choices' => [['label' => 'Lanjutkan', 'next' => 'ending_win', 'is_auto' => true]],
            ];
        }

        $nodes[] = self::endingWin($win, $outro, hasReward: (bool) $reward);

        return ['start_node' => 'beat_1', 'nodes' => $nodes, 'monsters' => []];
    }

    /**
     * Node ending sukses. Bila ada node reward, prosa `win` sudah terpakai di
     * sana, jadi ending memakai `outro` (atau default). Bila tidak, prosa `win`
     * pindah ke ending supaya tidak hilang.
     *
     * @param  array{title: ?string, body: ?string}  $win
     * @param  array{title: ?string, body: ?string}  $outro
     * @return array<string, mixed>
     */
    private static function endingWin(array $win, array $outro, bool $hasReward): array
    {
        return [
            'key' => 'ending_win',
            'type' => 'ending',
            'title' => ($hasReward ? $outro['title'] : $win['title']) ?? 'Misi Tuntas',
            'body' => $hasReward
                ? ($outro['body'] ?? self::DEFAULT_OUTRO)
                : $win['body'],
            'payload' => ['result' => 'victory'],
        ];
    }

    /**
     * Normalkan satu field prosa. Menerima string ATAU objek
     * `{"title": "...", "body": "..."}` — bentuk objek dipakai kalau penulis
     * mau menimpa judul default.
     *
     * @return array{title: ?string, body: ?string}
     */
    private static function prose(string $slug, string $field, mixed $value, bool $required = true): array
    {
        if (is_string($value) && trim($value) !== '') {
            return ['title' => null, 'body' => $value];
        }

        if (is_array($value)) {
            $title = isset($value['title']) ? (string) $value['title'] : null;
            $body = isset($value['body']) ? (string) $value['body'] : '';

            if (trim($body) !== '') {
                return ['title' => $title, 'body' => $body];
            }
            if (! $required) {
                return ['title' => $title, 'body' => null];
            }
        }

        if ($required) {
            throw new RuntimeException("Misi `{$slug}`: `{$field}` wajib diisi.");
        }

        return ['title' => null, 'body' => null];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=QuestTemplateTest`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/QuestTemplate.php tests/Feature/QuestTemplateTest.php
git commit -m "QuestTemplate: arketipe hunt jadi node long-form"
```

---

### Task 3: Arketipe `errand`

**Files:**
- Modify: `app/Services/QuestTemplate.php` (kode `errand` sudah ditulis di Task 2 — task ini menguncinya dengan test)
- Test: `tests/Feature/QuestTemplateTest.php` (append)

**Interfaces:**
- Consumes: `QuestTemplate::expand()` dari Task 2
- Produces: kunci node `errand`: `beat_1..beat_n`, `win` (hanya bila ada `reward`), `ending_win`. Tidak ada node `lose`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/QuestTemplateTest.php` (inside the class):

```php
    private function errandQuest(array $overrides = []): array
    {
        return [
            'slug' => 'antar-surat',
            'title' => 'Antar Surat',
            'affiliation' => 'merchant',
            'errand' => array_merge([
                'beats' => [
                    'Juru tulis menyerahkan surat bersegel lilin merah.',
                    'Jalan berdebu menuju desa sepi. Tak ada yang mengganggumu.',
                ],
                'win' => 'Surat sampai di tangan yang benar. Bayaran diserahkan.',
                'reward' => ['xp' => 10, 'gold' => 20],
            ], $overrides),
        ];
    }

    public function test_errand_chains_its_beats_in_order(): void
    {
        $expanded = QuestTemplate::expand($this->errandQuest());
        $nodes = $this->nodesByKey($expanded);

        $this->assertSame('beat_1', $expanded['start_node']);
        $this->assertSame(['beat_1', 'beat_2', 'win', 'ending_win'], array_keys($nodes));

        $this->assertSame('narrative', $nodes['beat_1']['type']);
        $this->assertSame('beat_2', $nodes['beat_1']['choices'][0]['next']);
        $this->assertSame('Lanjutkan', $nodes['beat_1']['choices'][0]['label']);
        $this->assertSame('win', $nodes['beat_2']['choices'][0]['next']);
        $this->assertSame('victory', $nodes['ending_win']['payload']['result']);
        $this->assertSame([], $expanded['monsters']);
    }

    public function test_errand_has_no_defeat_ending(): void
    {
        $nodes = $this->nodesByKey(QuestTemplate::expand($this->errandQuest()));

        $this->assertArrayNotHasKey('lose', $nodes);
    }

    public function test_errand_without_reward_ends_after_the_last_beat(): void
    {
        $quest = $this->errandQuest();
        unset($quest['errand']['reward']);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame(['beat_1', 'beat_2', 'ending_win'], array_keys($nodes));
        $this->assertSame('ending_win', $nodes['beat_2']['choices'][0]['next']);
        $this->assertSame('Surat sampai di tangan yang benar. Bayaran diserahkan.', $nodes['ending_win']['body']);
    }

    public function test_a_single_beat_errand_works(): void
    {
        $quest = $this->errandQuest(['beats' => ['Kau menyampaikan pesan singkat itu.']]);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame(['beat_1', 'win', 'ending_win'], array_keys($nodes));
        $this->assertSame('win', $nodes['beat_1']['choices'][0]['next']);
    }
```

- [ ] **Step 2: Run test to verify it passes**

Run: `php artisan test --filter=QuestTemplateTest`
Expected: PASS (9 tests) — implementasi `errand` sudah ada dari Task 2; test ini yang menguncinya. Jika ada yang gagal, perbaiki `QuestTemplate::errand()` sampai lulus sebelum lanjut.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/QuestTemplateTest.php
git commit -m "Kunci perilaku arketipe errand dengan test"
```

---

### Task 4: Default judul, label, dan bentuk prosa objek

**Files:**
- Modify: `app/Services/QuestTemplate.php` (bila ada default yang belum sesuai)
- Test: `tests/Feature/QuestTemplateTest.php` (append)

**Interfaces:**
- Consumes: `QuestTemplate::expand()` dari Task 2 & 3
- Produces: kontrak default yang dipegang task migrasi konten (Task 7)

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/QuestTemplateTest.php` (inside the class):

```php
    public function test_titles_fall_back_to_sane_defaults(): void
    {
        $nodes = $this->nodesByKey(QuestTemplate::expand($this->huntQuest()));

        $this->assertSame('Tikus Gudang', $nodes['intro']['title']);      // judul misi
        $this->assertSame('Tikus Raksasa!', $nodes['fight']['title']);    // nama monster + !
        $this->assertSame('Berhasil', $nodes['win']['title']);
        $this->assertSame('Misi Tuntas', $nodes['ending_win']['title']);
        $this->assertSame('Kalah', $nodes['lose']['title']);
    }

    public function test_object_prose_overrides_the_default_title(): void
    {
        $quest = $this->huntQuest([
            'intro' => ['title' => 'Gudang Berdebu', 'body' => 'Bau apek menyambutmu.'],
        ]);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame('Gudang Berdebu', $nodes['intro']['title']);
        $this->assertSame('Bau apek menyambutmu.', $nodes['intro']['body']);
    }

    public function test_missing_lose_prose_uses_the_default_text(): void
    {
        $quest = $this->huntQuest();
        unset($quest['hunt']['lose']);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame('Kalah', $nodes['lose']['title']);
        $this->assertNotEmpty($nodes['lose']['body']);
    }

    public function test_outro_overrides_the_default_ending_text(): void
    {
        $quest = $this->huntQuest(['outro' => 'Guild mencatat namamu di papan jasa.']);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame('Guild mencatat namamu di papan jasa.', $nodes['ending_win']['body']);
    }
```

- [ ] **Step 2: Run test to verify it passes**

Run: `php artisan test --filter=QuestTemplateTest`
Expected: PASS (13 tests). Jika `test_outro_overrides_the_default_ending_text` gagal, periksa `endingWin()` — `outro` harus dipakai saat node reward ada.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/QuestTemplateTest.php
git commit -m "Kunci default judul/label dan bentuk prosa objek"
```

---

### Task 5: Pesan galat validasi

**Files:**
- Modify: `app/Services/QuestTemplate.php` (bila ada pesan yang belum sesuai)
- Test: `tests/Feature/QuestTemplateTest.php` (append)

**Interfaces:**
- Consumes: `QuestTemplate::expand()`
- Produces: jaminan setiap konten rusak gagal keras dengan pesan yang menyebut slug misi

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/QuestTemplateTest.php` (inside the class):

```php
    public function test_mixing_two_archetypes_is_rejected(): void
    {
        $quest = $this->huntQuest();
        $quest['errand'] = ['beats' => ['Apa pun.'], 'win' => 'Selesai.'];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('pilih salah satu');
        QuestTemplate::expand($quest);
    }

    public function test_mixing_shorthand_with_long_form_nodes_is_rejected(): void
    {
        $quest = $this->huntQuest();
        $quest['nodes'] = [['key' => 'intro', 'type' => 'narrative', 'body' => 'Halo.']];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak bisa dicampur');
        QuestTemplate::expand($quest);
    }

    public function test_hunt_without_a_monster_is_rejected(): void
    {
        $quest = $this->huntQuest();
        unset($quest['hunt']['monster']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('`hunt.monster` wajib punya `slug`');
        QuestTemplate::expand($quest);
    }

    public function test_monster_without_slug_is_rejected(): void
    {
        $quest = $this->huntQuest(['monster' => ['name' => 'Tanpa Slug', 'level' => 1]]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tikus-gudang');
        QuestTemplate::expand($quest);
    }

    public function test_empty_beats_is_rejected(): void
    {
        $quest = $this->errandQuest(['beats' => []]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('`errand.beats` wajib');
        QuestTemplate::expand($quest);
    }

    public function test_required_prose_cannot_be_blank(): void
    {
        $quest = $this->huntQuest(['intro' => '   ']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('`hunt.intro` wajib diisi');
        QuestTemplate::expand($quest);
    }

    public function test_missing_win_prose_is_rejected(): void
    {
        $quest = $this->huntQuest();
        unset($quest['hunt']['win']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('`hunt.win` wajib diisi');
        QuestTemplate::expand($quest);
    }

    public function test_archetype_must_be_an_object(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('harus berupa objek');
        QuestTemplate::expand(['slug' => 'rusak', 'title' => 'Rusak', 'hunt' => 'bukan objek']);
    }
```

- [ ] **Step 2: Run test to verify it passes**

Run: `php artisan test --filter=QuestTemplateTest`
Expected: PASS (21 tests). Jika ada yang gagal, sesuaikan pesan galat di `QuestTemplate` — bukan test-nya.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/QuestTemplateTest.php app/Services/QuestTemplate.php
git commit -m "Kunci pesan galat validasi konten misi ringkas"
```

---

### Task 6: Sambungkan ke `game:import` + test integrasi

**Files:**
- Modify: `app/Console/Commands/ImportGameContent.php:130-135` (awal `importQuests`)
- Test: `tests/Feature/QuestTemplateImportTest.php` (create)

**Interfaces:**
- Consumes: `QuestTemplate::expand()` (Task 2–5), `Monster::statsForLevel()` via `upsertMonster` (Task 1)
- Produces: `game:import` menerima file misi ringkas; hasilnya quest yang bisa dimainkan

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/QuestTemplateImportTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Monster;
use App\Models\Quest;
use App\Models\User;
use App\Services\CombatService;
use App\Services\StoryEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class QuestTemplateImportTest extends TestCase
{
    use RefreshDatabase;

    private string $path = '';

    protected function tearDown(): void
    {
        if ($this->path !== '' && File::exists($this->path)) {
            File::delete($this->path);
        }

        parent::tearDown();
    }

    /** Tulis satu file misi ringkas ke direktori konten, lalu impor. */
    private function importShorthandQuest(): void
    {
        $this->path = database_path('content/quests/uji-integrasi.json');
        File::put($this->path, json_encode([
            'slug' => 'uji-integrasi',
            'title' => 'Uji Integrasi',
            'description' => 'Misi ringkas untuk membuktikan hasil ekspansi benar-benar jalan.',
            'affiliation' => 'adventurer',
            'required_rank' => 'F',
            'min_level' => 1,
            'order' => 99,
            'hunt' => [
                'monster' => ['slug' => 'kelinci-uji', 'name' => 'Kelinci Uji', 'level' => 1],
                'intro' => 'Seekor kelinci menghadangmu dengan tatapan menantang.',
                'fight' => 'Kelinci itu menerjang.',
                'win' => 'Kelinci itu kabur ke balik semak.',
                'lose' => 'Kalah dari kelinci. Ini akan dikenang.',
                'reward' => ['xp' => 5, 'gold' => 5],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->artisan('game:import')->assertSuccessful();
    }

    public function test_import_turns_shorthand_into_a_playable_quest(): void
    {
        $this->importShorthandQuest();

        $quest = Quest::where('slug', 'uji-integrasi')->firstOrFail();
        $this->assertNotNull($quest->start_node_id);
        $this->assertSame(5, $quest->nodes()->count());
        $this->assertSame('intro', $quest->startNode->key);

        $monster = Monster::where('slug', 'kelinci-uji')->firstOrFail();
        $this->assertSame(3, $monster->max_hp);  // dari rumus level 1
        $this->assertSame(1, $monster->attack);
    }

    public function test_the_expanded_quest_can_be_played_to_its_victory_ending(): void
    {
        $this->importShorthandQuest();

        $user = User::factory()->create();
        $char = $user->characters()->create([
            'name' => 'Penguji', 'level' => 1, 'xp' => 0, 'hp' => 50, 'max_hp' => 50,
            'sp' => 30, 'max_sp' => 30, 'mp' => 30, 'max_mp' => 30,
            'attack' => 10, 'defense' => 5, 'gold' => 0, 'is_alive' => true,
        ]);

        $story = app(StoryEngine::class);
        $combat = app(CombatService::class);
        $quest = Quest::where('slug', 'uji-integrasi')->firstOrFail();

        $story->startQuest($char, $quest);
        $save = $story->save($char);
        $this->assertSame('intro', $save->current_node_key);

        // intro -> fight. Catatan: choose() menerima objek NodeChoice, bukan id.
        $choice = $story->currentNode($save)->choices->firstOrFail();
        $story->choose($char, $choice);
        $save = $story->save($char->fresh());
        $this->assertSame('fight', $save->current_node_key);

        // Habisi monster (HP 3, Pukul power 1) lalu pastikan cerita maju.
        $node = $story->currentNode($save);
        $combat->start($char->fresh(), $node);
        $session = $char->fresh()->activeCombat()->first();
        $skill = $char->fresh()->skills()->firstOrFail();

        for ($turn = 0; $turn < 5; $turn++) {
            $result = $combat->act($session->fresh(), 'skill', $skill->id);
            if ($result['status'] === 'won') {
                break;
            }
        }

        $this->assertSame('won', $result['status']);
        $this->assertSame('win', $result['next_node']['node_key']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=QuestTemplateImportTest`
Expected: FAIL — quest terimpor tanpa node (`assertSame(5, ...)` gagal, `start_node_id` null), karena `importQuests` belum memanggil expander.

- [ ] **Step 3: Wire the expander into the importer**

In `app/Console/Commands/ImportGameContent.php`, change the top of `importQuests`:

```php
    private function importQuests(string $dir): void
    {
        foreach ($this->jsonFiles($dir) as $data) {
            // Bentuk ringkas (hunt/errand) dikembangkan jadi node long-form dulu,
            // supaya sisa jalur di bawah ini tidak perlu tahu template itu ada.
            $data = QuestTemplate::expand($data);

            // Embedded monsters first so nodes can resolve monster_id by slug.
            foreach ($data['monsters'] ?? [] as $monsterData) {
                $this->upsertMonster($monsterData);
            }
```

Add the import at the top of the file:

```php
use App\Services\QuestTemplate;
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=QuestTemplateImportTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Verify nothing else broke**

Run: `php artisan test`
Expected: PASS — 173 + 21 + 2 = 196 test, nol gagal

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/ImportGameContent.php tests/Feature/QuestTemplateImportTest.php
git commit -m "game:import menerima bentuk misi ringkas"
```

---

### Task 7: Migrasi `tikus-gudang` + satu misi `errand` baru

**Files:**
- Modify: `database/content/quests/tikus-gudang.json`
- Create: `database/content/quests/kabar-desa.json`
- Jangan sentuh: `database/content/quests/goblin-cave.json`, `patroli-tembok.json`, `antar-surat.json`

**Interfaces:**
- Consumes: seluruh kontrak dari Task 1–6
- Produces: bukti bahwa kedua arketipe menghasilkan misi yang bisa dimainkan

**Kenapa hanya satu misi yang dimigrasi:** pemeriksaan ulang konten menunjukkan `patroli-tembok` dan `antar-surat` **bercabang** (masing-masing punya dua pilihan di node pertama), jadi keduanya bukan rantai lurus dan **tidak boleh** dipaksa jadi `errand` — memaksanya akan menghapus percabangan yang justru jadi isi misinya. Keduanya tetap long-form. Karena itu `errand` divalidasi lewat satu misi baru yang memang lurus — sekaligus mendemonstrasikan klaim M1 bahwa menambah misi itu murah.

- [ ] **Step 1: Record the current shape of the quests**

Run: `php artisan tinker --execute="foreach (App\Models\Quest::orderBy('slug')->get() as \$q) { echo \$q->slug.': '.\$q->nodes()->count().\" node\n\"; }"`

Catat keluarannya. Setelah semua perubahan, hanya `tikus-gudang` yang boleh berubah bentuk (tetap 5 node) dan `kabar-desa` yang baru muncul.

- [ ] **Step 2: Rewrite `tikus-gudang.json` in shorthand**

Replace the whole file with:

```json
{
    "slug": "tikus-gudang",
    "title": "Tikus Gudang",
    "description": "Gudang logistik guild dipenuhi tikus sebesar anjing. Bersihkan seekor yang paling berani agar para juru tulis bisa bekerja tenang.",
    "affiliation": "adventurer",
    "required_rank": "F",
    "cover_image": null,
    "min_level": 1,
    "order": 2,
    "hunt": {
        "monster": { "slug": "tikus-raksasa", "name": "Tikus Raksasa", "level": 1 },
        "intro": {
            "title": "Gudang Berdebu",
            "body": "Bau apek dan cericit menyambutmu. Di antara tumpukan peti, sepasang mata merah berkilat. Seekor tikus raksasa menggeram, menjaga wilayahnya."
        },
        "fight": "Tikus itu melompat dengan gigi kuning terbuka.",
        "win": {
            "title": "Gudang Aman",
            "body": "Tikus itu kabur ke lubang dindingnya, tak akan kembali untuk sementara. Juru tulis menyelipkan beberapa koin sebagai tanda terima kasih."
        },
        "outro": {
            "title": "Misi Tuntas",
            "body": "Gudang kembali tenang. Tugas kecil, tapi setiap reputasi dibangun dari hal-hal seperti ini."
        },
        "lose": {
            "title": "Dikalahkan Hama",
            "body": "Memalukan — kau mundur dari seekor tikus. Pulihkan diri dan coba lagi."
        },
        "reward": { "xp": 15, "gold": 15 }
    }
}
```

Catatan: `tikus-raksasa` cukup `"level": 1` karena rumus lv1 menghasilkan stat yang identik dengan nilai lamanya (hp 3, atk 1, def 0, xp 30, emas 10).

- [ ] **Step 3: Re-import and verify the quest is unchanged in shape**

Run: `php artisan game:import`
Expected: berhasil, baris `- quest 'tikus-gudang' (5 nodes)`

Run: `php artisan tinker --execute="\$m = App\Models\Monster::where('slug','tikus-raksasa')->firstOrFail(); echo \$m->max_hp.'/'.\$m->attack.'/'.\$m->xp_reward.'/'.\$m->gold_reward;"`
Expected: `3/1/30/10` — sama dengan sebelum migrasi

- [ ] **Step 4: Write a new `errand` mission**

Create `database/content/quests/kabar-desa.json`:

```json
{
    "slug": "kabar-desa",
    "title": "Kabar dari Desa Seberang",
    "description": "Seorang juru tulis guild butuh kurir untuk membawa kabar ke desa seberang bukit. Tak ada monster, hanya jalan panjang dan kaki yang pegal.",
    "affiliation": "merchant",
    "required_rank": "F",
    "cover_image": null,
    "min_level": 1,
    "order": 5,
    "errand": {
        "beats": [
            {
                "title": "Meja Juru Tulis",
                "body": "Juru tulis menyerahkan gulungan tipis bersegel lilin biru. \"Jangan dibaca,\" katanya tanpa mengangkat kepala. \"Dan jangan sampai basah.\""
            },
            {
                "title": "Jalan Bukit",
                "body": "Jalan tanah menanjak di antara ladang gandum. Angin membawa bau hujan dari kejauhan, tapi langit masih terang sampai kau melewati punggung bukit."
            },
            {
                "title": "Desa Seberang",
                "body": "Anak-anak berhenti bermain dan menatapmu. Seorang perempuan tua menunjuk rumah beratap jerami di ujung jalan tanpa perlu kau bertanya."
            }
        ],
        "win": {
            "title": "Gulungan Berpindah Tangan",
            "body": "Segel lilin biru dibuka di depanmu, dibaca cepat, lalu diikuti anggukan puas. Beberapa koin diserahkan tanpa ditawar."
        },
        "outro": {
            "title": "Kembali Sebelum Hujan",
            "body": "Kau berjalan pulang dengan kantung sedikit lebih berat. Hujan akhirnya turun tepat setelah kau melewati gerbang kota."
        },
        "reward": { "xp": 12, "gold": 18 }
    }
}
```

- [ ] **Step 5: Re-import and verify the new mission expands correctly**

Run: `php artisan game:import`
Expected: berhasil, ada baris `- quest 'kabar-desa' (5 nodes)` — 3 beat + `win` + `ending_win`

Run: `php artisan tinker --execute="\$q = App\Models\Quest::where('slug','kabar-desa')->firstOrFail(); foreach (\$q->nodes()->orderBy('id')->get() as \$n) { echo \$n->key.' ('.\$n->type.') -> '.(\$n->choices()->value('next_node_key') ?? '-').\"\n\"; }"`
Expected:
```
beat_1 (narrative) -> beat_2
beat_2 (narrative) -> beat_3
beat_3 (narrative) -> win
win (reward) -> ending_win
ending_win (ending) -> -
```

- [ ] **Step 6: Verify all quests still load**

Run: `php artisan game:import`
Expected: berhasil; jumlah node tiap misi sama dengan catatan Step 1 (kecuali misi yang sengaja dibiarkan long-form)

Run: `php artisan tinker --execute="foreach (App\Models\Quest::orderBy('slug')->get() as \$q) { echo \$q->slug.': '.\$q->nodes()->count().' node, start='.(\$q->startNode?->key ?? 'NULL').\"\n\"; }"`
Expected: tiap misi punya `start` yang tidak `NULL`; `goblin-cave`, `patroli-tembok`, dan `antar-surat` jumlah node-nya **sama** dengan catatan Step 1

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: PASS — 196 test, nol gagal

- [ ] **Step 8: Measure the win**

Run: `git diff --stat database/content/quests/tikus-gudang.json`
Expected: baris terhapus jauh lebih banyak daripada yang ditambah

Run: `php -r "echo count(file('database/content/quests/kabar-desa.json')).\" baris untuk misi 5 node\n\";"`
Expected: di bawah 40 baris — bandingkan dengan `patroli-tembok.json` yang 4 node butuh 46 baris

- [ ] **Step 9: Commit**

```bash
git add database/content/quests/
git commit -m "tikus-gudang pakai bentuk ringkas + misi errand baru kabar-desa"
```

---

### Task 8: Dokumentasi

**Files:**
- Modify: `GAME.md` (bagian "Menulis konten (developer)")

**Interfaces:**
- Consumes: kontrak final dari Task 1–7
- Produces: dokumentasi yang cocok dengan kode

- [ ] **Step 1: Update the content-authoring section**

In `GAME.md`, in the section **"Menulis konten (developer)"**, add after the existing bullet list:

```markdown
### Bentuk misi ringkas (`hunt` / `errand`)
Misi berpola tidak perlu ditulis node-per-node. Dua arketipe dikembangkan `App\Services\QuestTemplate` saat `game:import`:
- **`hunt`** — `intro` → `fight` → `win` (reward) → `ending_win`, plus ending `lose`. Wajib: `monster` (dengan `slug`), `intro`, `fight`, `win`. Opsional: `lose`, `outro`, `reward`.
- **`errand`** — `beats[]` dirantai berurutan → `win` (reward) → `ending_win`. Tanpa ending kalah. Wajib: `beats` (minimal satu), `win`.
- Tanpa `reward`, node reward tidak dibuat dan prosa `win` pindah ke ending.
- Tiap field prosa menerima string **atau** `{"title": "...", "body": "..."}` untuk menimpa judul default (judul `intro` = judul misi, `fight` = nama monster + "!", `win` = "Berhasil", `ending_win` = "Misi Tuntas", `lose` = "Kalah").
- **Monster cukup `{"slug", "name", "level"}`** — stat diturunkan `Monster::statsForLevel()` (lv1 = hp 3 / atk 1 / def 0 / xp 30 / emas 10, naik linear). Field stat yang ditulis eksplisit selalu menimpa rumus; field tak dikenal (mis. `magik_attack`) membuat import gagal.
- Misi **bercabang** tetap ditulis long-form (`nodes`) — lihat `goblin-cave.json`, `patroli-tembok.json`, dan `antar-surat.json` yang semuanya punya pilihan bercabang. Bentuk ringkas dan `nodes` tidak boleh dicampur dalam satu file.
- Contoh ringkas: `tikus-gudang.json` (hunt) & `kabar-desa.json` (errand).
```

- [ ] **Step 2: Update the test count**

In `GAME.md`, find the line starting with `php artisan test     # 165 test` and change the count to the number reported by the full suite, adding `QuestTemplate` to the list of suites.

- [ ] **Step 3: Verify the documented claims are true**

Run: `php artisan test`
Expected: jumlah test cocok dengan yang baru dituliskan di `GAME.md`

- [ ] **Step 4: Commit**

```bash
git add GAME.md
git commit -m "Dokumentasikan bentuk misi ringkas di GAME.md"
```

---

## Di luar lingkup plan ini

Jangan bangun (sudah diputuskan di spec): tabel loot bersama, drop scroll, arketipe `gather`/`escort`, wizard "Buat Misi Cepat" di Panel Dewa, rumus stat monster penyihir, kurva skill & alokasi poin atribut (M2), travel antar kota (M3).
