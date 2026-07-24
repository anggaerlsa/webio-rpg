<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Country;
use App\Models\Quest;
use App\Models\QuestNode;
use App\Models\RankRule;
use App\Models\User;
use App\Services\RankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankMissionTest extends TestCase
{
    use RefreshDatabase;

    private function ranks(): RankService
    {
        return app(RankService::class);
    }

    private function player(string $affiliation = 'adventurer', string $rank = 'F', int $level = 1): array
    {
        $user = User::factory()->create();
        $char = $user->characters()->create([
            'name' => 'Hero', 'level' => $level, 'xp' => 0, 'hp' => 50, 'max_hp' => 50,
            'attack' => 10, 'defense' => 5, 'gold' => 0, 'is_alive' => true,
            'affiliation' => $affiliation, 'rank' => $rank, 'rank_progress' => 0,
        ]);

        return [$user, $char];
    }

    /** Misi dengan adegan awal narasi (tidak auto-selesai saat diambil). */
    private function mission(string $slug, string $affiliation = 'adventurer', string $rank = 'F', int $level = 1): Quest
    {
        $quest = Quest::create([
            'slug' => $slug, 'title' => ucfirst($slug), 'affiliation' => $affiliation,
            'required_rank' => $rank, 'min_level' => $level, 'is_published' => true,
        ]);
        $node = QuestNode::create(['quest_id' => $quest->id, 'key' => 'intro', 'type' => 'narrative', 'title' => 'Mulai']);
        $quest->update(['start_node_id' => $node->id]);

        return $quest;
    }

    /** Misi yang adegan awalnya langsung ending menang (untuk menguji hook penyelesaian). */
    private function instantMission(string $slug, string $affiliation = 'adventurer'): Quest
    {
        $quest = Quest::create([
            'slug' => $slug, 'title' => ucfirst($slug), 'affiliation' => $affiliation,
            'required_rank' => 'F', 'min_level' => 1, 'is_published' => true,
        ]);
        $node = QuestNode::create([
            'quest_id' => $quest->id, 'key' => 'end', 'type' => 'ending',
            'title' => 'Selesai', 'payload' => ['result' => 'victory'],
        ]);
        $quest->update(['start_node_id' => $node->id]);

        return $quest;
    }

    public function test_accepting_a_mission_makes_it_active(): void
    {
        [, $char] = $this->player();
        $quest = $this->mission('jaga-gerbang');

        $this->ranks()->accept($char, $quest);

        $char->refresh();
        $this->assertSame($quest->id, $char->active_quest_id);
        $this->assertSame($quest->id, (int) $char->saves()->where('slot', 1)->first()->quest_id);
    }

    public function test_cannot_accept_a_mission_for_another_guild(): void
    {
        [, $char] = $this->player('adventurer');
        $quest = $this->mission('dagang', 'merchant');

        $this->assertNotNull($this->ranks()->acceptBlockReason($char, $quest));
    }

    public function test_cannot_accept_while_a_mission_is_active(): void
    {
        [, $char] = $this->player();
        $first = $this->mission('satu');
        $second = $this->mission('dua');

        $this->ranks()->accept($char, $first);
        $this->assertNotNull($this->ranks()->acceptBlockReason($char->refresh(), $second));
    }

    public function test_completing_a_mission_via_ending_records_and_advances(): void
    {
        [, $char] = $this->player();
        $quest = $this->instantMission('kilat'); // start node = victory ending

        $this->ranks()->accept($char, $quest); // accept → start → ending → complete fires

        $char->refresh();
        $this->assertTrue($this->ranks()->hasCompleted($char, $quest));
        $this->assertSame(1, $char->rank_progress);
        $this->assertNull($char->active_quest_id); // misi aktif dikosongkan
    }

    public function test_rank_increases_when_threshold_reached(): void
    {
        RankRule::where('rank', 'F')->update(['missions_required' => 1]);
        [, $char] = $this->player('adventurer', 'F');
        $quest = $this->instantMission('naik');

        $this->ranks()->accept($char, $quest);

        $char->refresh();
        $this->assertSame('E', $char->rank);
        $this->assertSame(0, $char->rank_progress); // reset setelah naik
    }

    public function test_completed_mission_disappears_from_guild_board(): void
    {
        [, $char] = $this->player();
        $done = $this->instantMission('selesai');
        $open = $this->mission('terbuka');

        $this->ranks()->accept($char, $done); // selesai seketika
        $available = $this->ranks()->availableMissions($char->refresh(), 'adventurer')->pluck('slug')->all();

        $this->assertContains('terbuka', $available);
        $this->assertNotContains('selesai', $available);
    }

    public function test_cannot_play_a_mission_that_is_not_active(): void
    {
        [$user, $char] = $this->player();
        $quest = $this->mission('bukan-aktif');

        $this->actingAs($user)->get(route('quests.play', $quest->slug))
            ->assertRedirect(route('quests.index'))
            ->assertSessionHas('error');
    }

    public function test_accept_mission_from_guild_board_via_http(): void
    {
        $country = Country::create(['slug' => 'n', 'name' => 'N']);
        $province = $country->provinces()->create(['slug' => 'p', 'name' => 'P']);
        $city = $province->cities()->create(['slug' => 'c', 'name' => 'C']);
        $guild = $city->places()->create(['category' => 'adventurer_guild', 'slug' => 'guild', 'name' => 'Guild']);

        [$user, $char] = $this->player();
        $char->update(['city_id' => $city->id]);
        $quest = $this->mission('papan');

        // Papan misi guild memuat misi.
        $this->actingAs($user)->get(route('town.place', $guild->slug))->assertOk();

        // Ambil misi → diarahkan untuk memainkannya, jadi misi aktif.
        $this->actingAs($user)->post(route('town.mission.accept', $guild->slug), ['quest_slug' => 'papan'])
            ->assertRedirect(route('quests.play', 'papan'))
            ->assertSessionHas('success');

        $this->assertSame($quest->id, $char->fresh()->active_quest_id);
    }

    public function test_superadmin_can_update_rank_thresholds(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'superadmin';
        $admin->save();

        $this->actingAs($admin)->put(route('admin.ranks.update'), [
            'rules' => [['rank' => 'F', 'missions_required' => 7]],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(7, RankRule::where('rank', 'F')->value('missions_required'));
    }
}
