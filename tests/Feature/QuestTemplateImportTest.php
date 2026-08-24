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

        // intro -> fight. choose() menerima objek NodeChoice, bukan id.
        $choice = $story->currentNode($save)->choices->firstOrFail();
        $story->choose($char, $choice);
        $save = $story->save($char->fresh());
        $this->assertSame('fight', $save->current_node_key);

        // Habisi monster (HP 3, Pukul power 1) lalu pastikan cerita maju.
        $node = $story->currentNode($save);
        $combat->start($char->fresh(), $node);
        $session = $char->fresh()->activeCombat()->firstOrFail();
        $skill = $char->fresh()->skills()->firstOrFail();

        $result = [];
        for ($turn = 0; $turn < 5; $turn++) {
            $result = $combat->act($session->fresh(), 'skill', $skill->id);
            if ($result['status'] !== 'active') {
                break;
            }
        }

        $this->assertSame('won', $result['status']);
        $this->assertSame('win', $result['next_node']['node_key']);
    }

    // ── Validasi payload adegan (berlaku untuk bentuk ringkas & long-form) ───

    /**
     * `assertPayloadKeys` privat — dipanggil lewat reflection supaya satu adegan
     * bisa diuji tanpa menulis file JSON sementara.
     *
     * @param  array<string, mixed>  $node
     */
    private function assertPayload(array $node): void
    {
        $command = app(\App\Console\Commands\ImportGameContent::class);
        $method = new \ReflectionMethod($command, 'assertPayloadKeys');
        $method->setAccessible(true);
        $method->invoke($command, 'misi-uji', $node);
    }

    public function test_reward_payload_typo_is_rejected_instead_of_silently_awarding_nothing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('exp');
        $this->assertPayload(['key' => 'win', 'type' => 'reward', 'payload' => ['exp' => 15]]);
    }

    public function test_the_rejection_names_the_quest_and_the_scene(): void
    {
        try {
            $this->assertPayload(['key' => 'win', 'type' => 'reward', 'payload' => ['exp' => 15]]);
            $this->fail('Kunci payload tak dikenal seharusnya ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('misi-uji', $e->getMessage());
            $this->assertStringContainsString('win', $e->getMessage());
            $this->assertStringContainsString('xp, gold, item_slugs', $e->getMessage()); // sebut yang benar
        }
    }

    public function test_a_valid_reward_payload_passes(): void
    {
        $this->assertPayload([
            'key' => 'win', 'type' => 'reward',
            'payload' => ['xp' => 15, 'gold' => 20, 'item_slugs' => ['potion']],
        ]);

        $this->addToAssertionCount(1); // tidak melempar = lulus
    }

    public function test_ending_payload_typo_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('hasil');
        $this->assertPayload(['key' => 'ending_win', 'type' => 'ending', 'payload' => ['hasil' => 'victory']]);
    }

    public function test_combat_routing_typo_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('on_win_node');
        $this->assertPayload([
            'key' => 'fight', 'type' => 'combat',
            'payload' => ['on_win_node' => 'win', 'on_lose_node_key' => 'lose'],
        ]);
    }

    public function test_non_object_payload_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('harus berupa objek');
        $this->assertPayload(['key' => 'win', 'type' => 'reward', 'payload' => 'lima belas xp']);
    }

    public function test_non_numeric_reward_amount_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('`payload.xp` harus angka');
        $this->assertPayload(['key' => 'win', 'type' => 'reward', 'payload' => ['xp' => 'banyak']]);
    }

    public function test_negative_reward_amount_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('`payload.gold` harus angka');
        $this->assertPayload(['key' => 'win', 'type' => 'reward', 'payload' => ['gold' => -5]]);
    }

    public function test_malformed_item_slugs_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('item_slugs` harus daftar slug');
        $this->assertPayload(['key' => 'win', 'type' => 'reward', 'payload' => ['item_slugs' => 'potion']]);
    }

    public function test_unlisted_node_types_keep_their_payload_freedom(): void
    {
        $this->assertPayload(['key' => 'intro', 'type' => 'narrative', 'payload' => ['apa_saja' => true]]);

        $this->addToAssertionCount(1);
    }

    public function test_import_aborts_on_a_typo_ed_reward_and_persists_nothing(): void
    {
        // Lewat bentuk ringkas: `exp` bukan `xp`. Dulu ini terimpor bersih lalu
        // pemain tidak dapat apa pun — sekarang harus menggagalkan seluruh import.
        $this->path = database_path('content/quests/uji-reward-rusak.json');
        File::put($this->path, json_encode([
            'slug' => 'uji-reward-rusak',
            'title' => 'Uji Reward Rusak',
            'affiliation' => 'adventurer',
            'hunt' => [
                'monster' => ['slug' => 'kelinci-rusak', 'name' => 'Kelinci Rusak', 'level' => 1],
                'intro' => 'Seekor kelinci menghadangmu.',
                'fight' => 'Kelinci itu menerjang.',
                'win' => 'Kelinci itu kabur.',
                'reward' => ['exp' => 15, 'gold' => 5],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        try {
            $this->artisan('game:import');
            $this->fail('Import dengan kunci reward salah seharusnya gagal.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('uji-reward-rusak', $e->getMessage());
            $this->assertStringContainsString('exp', $e->getMessage());
        }

        // Satu transaksi: tidak boleh ada sisa data dari file yang gagal.
        $this->assertDatabaseMissing('quests', ['slug' => 'uji-reward-rusak']);
        $this->assertDatabaseMissing('monsters', ['slug' => 'kelinci-rusak']);
    }
}
