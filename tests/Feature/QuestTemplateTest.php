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

    public function test_hunt_with_an_empty_reward_block_still_gets_the_win_node(): void
    {
        $quest = $this->huntQuest(['reward' => []]);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame(['intro', 'fight', 'win', 'ending_win', 'lose'], array_keys($nodes));
        $this->assertSame('win', $nodes['fight']['payload']['on_win_node_key']);
        $this->assertSame([], $nodes['win']['payload']);
    }

    public function test_non_array_reward_is_rejected_with_a_runtime_exception_not_a_type_error(): void
    {
        $quest = $this->huntQuest(['reward' => 'oops']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tikus-gudang');
        QuestTemplate::expand($quest);
    }

    public function test_monster_without_a_name_falls_back_to_its_slug_for_the_title(): void
    {
        $quest = $this->huntQuest(['monster' => ['slug' => 'tikus-raksasa', 'level' => 1]]);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame('tikus-raksasa!', $nodes['fight']['title']);
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

    public function test_choice_labels_fall_back_to_sane_defaults_when_unwritten(): void
    {
        $nodes = $this->nodesByKey(QuestTemplate::expand($this->huntQuest()));

        $this->assertSame('Hadapi', $nodes['intro']['choices'][0]['label']);
        $this->assertSame('Lanjutkan', $nodes['win']['choices'][0]['label']);
    }

    public function test_blank_label_or_title_falls_back_instead_of_rendering_empty(): void
    {
        $quest = $this->huntQuest([
            'intro' => ['title' => '   ', 'label' => '', 'body' => 'Bau apek menyambutmu.'],
        ]);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame('Hadapi', $nodes['intro']['choices'][0]['label']); // bukan tombol kosong
        $this->assertSame('Tikus Gudang', $nodes['intro']['title']);         // bukan judul kosong
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

    public function test_hunt_intro_object_prose_can_override_the_choice_label(): void
    {
        $quest = $this->huntQuest([
            'intro' => ['title' => 'Gudang Berdebu', 'body' => 'Bau apek menyambutmu.', 'label' => 'Hadapi tikus itu'],
        ]);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame('Hadapi tikus itu', $nodes['intro']['choices'][0]['label']);
    }

    public function test_hunt_win_object_prose_can_override_the_reward_choice_label(): void
    {
        $quest = $this->huntQuest([
            'win' => ['title' => 'Gudang Aman', 'body' => 'Tikus itu kabur.', 'label' => 'Laporkan ke guild'],
        ]);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame('Laporkan ke guild', $nodes['win']['choices'][0]['label']);
        $this->assertTrue($nodes['win']['choices'][0]['is_auto']);
    }

    public function test_missing_lose_prose_uses_the_default_text(): void
    {
        $quest = $this->huntQuest();
        unset($quest['hunt']['lose']);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame('Kalah', $nodes['lose']['title']);
        $this->assertSame('Kau tumbang. Pulihkan diri lalu coba lagi.', $nodes['lose']['body']);
    }

    public function test_outro_overrides_the_default_ending_text(): void
    {
        $quest = $this->huntQuest(['outro' => 'Guild mencatat namamu di papan jasa.']);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame('Guild mencatat namamu di papan jasa.', $nodes['ending_win']['body']);
    }

    public function test_win_title_beats_outro_in_ending_when_there_is_no_reward(): void
    {
        // Tanpa reward, prosa `win` (lebih spesifik) yang dipakai di ending —
        // `outro` didiamkan meski ditulis, bukan malah menimpa.
        $quest = $this->huntQuest([
            'win' => ['title' => 'Tikus Tumpas', 'body' => 'Tikus itu ambruk, ekornya berhenti bergerak.'],
            'outro' => 'Guild mencatat namamu di papan jasa dengan tinta emas.',
        ]);
        unset($quest['hunt']['reward']);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame('Tikus Tumpas', $nodes['ending_win']['title']);
        $this->assertSame('Tikus itu ambruk, ekornya berhenti bergerak.', $nodes['ending_win']['body']);

        foreach ($nodes as $node) {
            $this->assertStringNotContainsString('Guild mencatat namamu di papan jasa dengan tinta emas.', (string) ($node['title'] ?? ''));
            $this->assertStringNotContainsString('Guild mencatat namamu di papan jasa dengan tinta emas.', (string) ($node['body'] ?? ''));
        }
    }

    public function test_mixing_shorthand_with_long_form_nodes_is_rejected(): void
    {
        $quest = $this->huntQuest();
        $quest['nodes'] = [['key' => 'intro', 'type' => 'narrative', 'body' => 'Halo.']];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak bisa dicampur');
        QuestTemplate::expand($quest);
    }

    public function test_mixing_shorthand_with_top_level_monsters_is_rejected(): void
    {
        $quest = $this->huntQuest();
        $quest['monsters'] = [['slug' => 'siluman', 'name' => 'Siluman', 'level' => 1]];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak bisa dicampur');
        QuestTemplate::expand($quest);
    }

    public function test_mixing_shorthand_with_top_level_start_node_is_rejected(): void
    {
        $quest = $this->huntQuest();
        $quest['start_node'] = 'intro';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak bisa dicampur');
        QuestTemplate::expand($quest);
    }

    public function test_unknown_key_inside_hunt_is_rejected(): void
    {
        // Salah tulis `rewards` (jamak) — dulu diam-diam diabaikan, pemain
        // tidak dapat apa-apa tanpa ada error.
        $quest = $this->huntQuest(['rewards' => ['xp' => 15, 'gold' => 15]]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('rewards');
        QuestTemplate::expand($quest);
    }

    public function test_label_as_an_archetype_level_key_is_still_rejected(): void
    {
        // `label` cuma sah di dalam bentuk objek prosa — bukan field arketipe.
        $quest = $this->huntQuest(['label' => 'Hadapi tikus itu']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tak dikenal');
        QuestTemplate::expand($quest);
    }

    public function test_unknown_key_inside_errand_is_rejected(): void
    {
        $quest = $this->errandQuest(['catatan' => 'field yang tidak ada di arketipe ini']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tak dikenal');
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

    private function errandQuest(array $overrides = []): array
    {
        return [
            'slug' => 'antar-kabar',
            'title' => 'Antar Kabar',
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
        $this->assertArrayNotHasKey('errand', $expanded);
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

    public function test_errand_with_an_empty_reward_block_still_gets_the_win_node(): void
    {
        $quest = $this->errandQuest(['reward' => []]);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame(['beat_1', 'beat_2', 'win', 'ending_win'], array_keys($nodes));
        $this->assertSame('win', $nodes['beat_2']['choices'][0]['next']);
        $this->assertSame([], $nodes['win']['payload']);
    }

    public function test_a_single_beat_errand_works(): void
    {
        $quest = $this->errandQuest(['beats' => ['Kau menyampaikan pesan singkat itu.']]);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame(['beat_1', 'win', 'ending_win'], array_keys($nodes));
        $this->assertSame('win', $nodes['beat_1']['choices'][0]['next']);
    }

    public function test_errand_beat_titles_default_to_the_quest_title(): void
    {
        $nodes = $this->nodesByKey(QuestTemplate::expand($this->errandQuest()));

        $this->assertSame('Antar Kabar', $nodes['beat_1']['title']);
        $this->assertSame('Antar Kabar', $nodes['beat_2']['title']);
    }

    public function test_errand_beat_accepts_object_prose(): void
    {
        $quest = $this->errandQuest([
            'beats' => [['title' => 'Meja Juru Tulis', 'body' => 'Gulungan tipis berpindah tangan.']],
        ]);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame('Meja Juru Tulis', $nodes['beat_1']['title']);
        $this->assertSame('Gulungan tipis berpindah tangan.', $nodes['beat_1']['body']);
    }

    public function test_errand_beats_can_set_their_own_choice_labels(): void
    {
        $quest = $this->errandQuest([
            'beats' => [
                ['title' => 'Meja Juru Tulis', 'body' => 'Gulungan berpindah tangan.', 'label' => 'Terima surat'],
                ['title' => 'Jalan Desa', 'body' => 'Jalan berdebu menuju desa sepi.', 'label' => 'Berjalan terus'],
            ],
        ]);

        $nodes = $this->nodesByKey(QuestTemplate::expand($quest));

        $this->assertSame('Terima surat', $nodes['beat_1']['choices'][0]['label']);
        $this->assertSame('Berjalan terus', $nodes['beat_2']['choices'][0]['label']);
    }

    public function test_empty_beats_is_rejected(): void
    {
        $quest = $this->errandQuest(['beats' => []]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('`errand.beats` wajib');
        QuestTemplate::expand($quest);
    }

    public function test_blank_beat_prose_is_rejected(): void
    {
        $quest = $this->errandQuest(['beats' => ['  ']]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('errand.beats');
        QuestTemplate::expand($quest);
    }

    public function test_mixing_two_archetypes_is_rejected(): void
    {
        $quest = $this->huntQuest();
        $quest['errand'] = ['beats' => ['Apa pun.'], 'win' => 'Selesai.'];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('salah satu');
        QuestTemplate::expand($quest);
    }
}
