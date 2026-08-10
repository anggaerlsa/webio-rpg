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
}
