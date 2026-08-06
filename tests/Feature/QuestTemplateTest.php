<?php

namespace Tests\Feature;

use App\Services\QuestTemplate;
use Tests\TestCase;

class QuestTemplateTest extends TestCase
{
    /** Misi `hunt` minimal yang dipakai berulang di test ini. */
    private function huntQuest(array $overrides = []): array
    {
        return [
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
        ];
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
}
