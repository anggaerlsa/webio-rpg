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

    public function test_importer_fills_stats_from_level(): void
    {
        $this->upsertMonster(['slug' => 'tikus-uji', 'name' => 'Tikus Uji', 'level' => 1]);

        $monster = Monster::where('slug', 'tikus-uji')->firstOrFail();
        $this->assertSame(3, $monster->max_hp);
        $this->assertSame(1, $monster->attack);
        $this->assertSame(30, $monster->xp_reward);
        $this->assertSame(10, $monster->gold_reward);
        $this->assertSame('physical', $monster->attack_kind);
    }

    public function test_explicit_fields_beat_the_formula(): void
    {
        $this->upsertMonster([
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
        $this->upsertMonster(['slug' => 'rusak', 'name' => 'Rusak']);
    }

    public function test_unknown_monster_field_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('magik_attack');
        $this->upsertMonster([
            'slug' => 'salah-tulis', 'name' => 'Salah Tulis', 'level' => 2, 'magik_attack' => 5,
        ]);
    }

    public function test_level_must_be_a_positive_integer(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('`level` harus integer');
        $this->upsertMonster(['slug' => 'aneh', 'name' => 'Aneh', 'level' => 'tiga']);
    }

    /**
     * `upsertMonster` privat — dipanggil lewat reflection supaya test satu blok
     * monster tidak perlu menulis file JSON sementara.
     *
     * @param  array<string, mixed>  $data
     */
    private function upsertMonster(array $data): void
    {
        $command = app(\App\Console\Commands\ImportGameContent::class);
        $method = new \ReflectionMethod($command, 'upsertMonster');
        $method->setAccessible(true);
        $method->invoke($command, $data);
    }
}
