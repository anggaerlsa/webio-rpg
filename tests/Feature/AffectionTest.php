<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Country;
use App\Models\Item;
use App\Models\Npc;
use App\Models\Place;
use App\Models\Quest;
use App\Models\User;
use App\Services\AffectionService;
use App\Services\RankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Lottery;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AffectionTest extends TestCase
{
    use RefreshDatabase;

    private Place $forge;

    private Npc $garrick;

    private User $user;

    private Character $char;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create(['slug' => 'astoria', 'name' => 'Astoria']);
        $city = $country->provinces()->create(['slug' => 'astoria-wil', 'name' => 'Wilayah'])->cities()->create(['slug' => 'solgrave', 'name' => 'Solgrave']);
        $this->forge = $city->places()->create(['category' => 'blacksmith', 'slug' => 'tempa', 'name' => 'Tempa Baja Naga']);
        $this->garrick = $this->forge->npcs()->create([
            'slug' => 'garrick', 'name' => 'Garrick', 'role' => 'Pandai Besi', 'gender' => 'male',
            'greeting' => 'Netral.', 'greeting_cold' => 'Keluar.', 'greeting_warm' => 'Duduk dekat api.',
            'apology_reply' => 'Baja retak bisa ditempa ulang.', 'refusal_message' => 'Garrick membanting pintu.',
            'choices' => [
                ['label' => 'Kenapa Baja Naga?', 'reply' => 'Kakekku.', 'affection' => 0, 'min_level' => null],
                ['label' => 'Palumu seperti musik.', 'reply' => 'Telingamu bagus.', 'affection' => 2, 'min_level' => null],
                ['label' => 'Kurcaci lebih hebat.', 'reply' => 'Pergi ke Nidavellir.', 'affection' => -2, 'min_level' => null],
                ['label' => 'Kau tinggal sendirian?', 'reply' => 'Rahasia.', 'affection' => 0, 'min_level' => 'friend'],
            ],
        ]);

        $this->user = User::factory()->create();
        $this->char = $this->user->characters()->create([
            'name' => 'Hero', 'level' => 3, 'xp' => 0, 'hp' => 50, 'max_hp' => 50, 'sp' => 10, 'max_sp' => 30,
            'mp' => 5, 'max_mp' => 30, 'attack' => 10, 'defense' => 5, 'gold' => 500, 'city_id' => $city->id, 'is_alive' => true,
        ]);
    }

    private function talk(int|string $choice)
    {
        return $this->actingAs($this->user)->postJson(route('town.npc.talk', [$this->forge, $this->garrick]), ['choice' => $choice]);
    }

    private function affection(): int
    {
        return app(AffectionService::class)->personal($this->char->fresh(), $this->garrick);
    }

    public function test_a_liked_choice_on_the_first_talk_of_the_day_raises_affection_by_three(): void
    {
        $this->talk(1)->assertOk()->assertJson(['reply' => 'Telingamu bagus.', 'delta' => 3]);

        $this->assertSame(3, $this->affection());
    }

    public function test_a_choice_and_the_first_talk_bonus_count_once_per_day(): void
    {
        $this->talk(1)->assertJson(['delta' => 3]);
        $this->talk(1)->assertJson(['reply' => 'Telingamu bagus.', 'delta' => 0]);
        $this->talk(0)->assertJson(['delta' => 0]);
        $this->assertSame(3, $this->affection());

        $this->travel(1)->days();
        $this->talk(1)->assertJson(['delta' => 3]);
        $this->assertSame(6, $this->affection());
    }

    public function test_gains_and_losses_are_each_capped_at_five_per_day(): void
    {
        $choice = fn (int $i, int $effect) => ['label' => "P{$i}", 'reply' => "J{$i}", 'affection' => $effect, 'min_level' => null];
        $this->garrick->update(['choices' => [
            $choice(0, 2), $choice(1, 2), $choice(2, 2), $choice(3, 2),
            $choice(4, -2), $choice(5, -2), $choice(6, -2), $choice(7, -2),
        ]]);

        $this->talk(0)->assertJson(['delta' => 3]); // +1 obrolan pertama, +2
        $this->talk(1)->assertJson(['delta' => 2]); // total naik 5
        $this->talk(2)->assertJson(['delta' => 0]); // batas naik tercapai
        $this->talk(4)->assertJson(['delta' => -2]);
        $this->talk(5)->assertJson(['delta' => -2]);
        $this->talk(6)->assertJson(['delta' => -1]); // total turun 5
        $this->talk(7)->assertJson(['delta' => 0]);

        $this->assertSame(0, $this->affection());
    }

    private function setAffection(int $value): void
    {
        DB::table('character_npc')->updateOrInsert(
            ['character_id' => $this->char->id, 'npc_id' => $this->garrick->id],
            ['affection' => $value],
        );
    }

    public function test_a_locked_choice_opens_only_at_its_level(): void
    {
        $this->talk(3)->assertForbidden();

        $this->setAffection(40);
        $this->talk(3)->assertOk()->assertJson(['reply' => 'Rahasia.']);
    }

    public function test_a_disliked_hero_can_only_apologise_once_a_day(): void
    {
        $this->setAffection(-30);

        $this->talk(0)->assertStatus(422);
        $this->talk('apology')->assertOk()->assertJson(['reply' => 'Baja retak bisa ditempa ulang.', 'delta' => 2]);
        $this->talk('apology')->assertJson(['delta' => 0]);
        $this->assertSame(-28, $this->affection());
    }

    public function test_a_hated_hero_cannot_even_apologise(): void
    {
        $this->setAffection(-60);

        $this->talk('apology')->assertStatus(422);
        $this->talk(1)->assertStatus(422);
        $this->assertSame(-60, $this->affection());
    }

    public function test_only_a_disliked_hero_has_something_to_apologise_for(): void
    {
        $this->talk('apology')->assertStatus(422);
    }

    private function visit()
    {
        return $this->actingAs($this->user)->get(route('town.place', $this->forge));
    }

    public function test_reputation_lifts_affection_into_a_warm_greeting_and_unlocks_secrets(): void
    {
        $this->setAffection(30);
        DB::table('character_reputations')->insert([
            'character_id' => $this->char->id, 'country_id' => Country::first()->id, 'reputation' => 40,
        ]);

        $this->visit()->assertInertia(fn (Assert $page) => $page
            ->where('npcs.0.greeting', 'Duduk dekat api.')
            ->where('npcs.0.affection', ['level' => 'friend', 'label' => 'Teman', 'value' => 40])
            ->where('npcs.0.choices.3.reply', 'Rahasia.')
            ->where('npcs.0.can_apologize', false));
    }

    public function test_a_disliked_hero_gets_a_cold_greeting_and_only_an_apology(): void
    {
        $this->setAffection(-30);

        $this->visit()->assertInertia(fn (Assert $page) => $page
            ->where('npcs.0.greeting', 'Keluar.')
            ->where('npcs.0.affection.level', 'disliked')
            ->has('npcs.0.choices', 0)
            ->where('npcs.0.can_apologize', true));
    }

    public function test_friends_pay_less_and_disliked_heroes_pay_more(): void
    {
        $sword = Item::create(['slug' => 'pedang', 'name' => 'Pedang', 'type' => 'weapon', 'value' => 100]);

        $this->setAffection(40);
        $this->visit()->assertInertia(fn (Assert $page) => $page->where('stock.0.price', 95)->where('price_modifier', -5));
        $this->actingAs($this->user)->post(route('town.buy', $this->forge), ['item_id' => $sword->id]);
        $this->assertSame(405, $this->char->fresh()->gold);

        $this->setAffection(-30);
        $this->visit()->assertInertia(fn (Assert $page) => $page->where('stock.0.price', 110)->where('price_modifier', 10));
    }

    public function test_the_innkeeper_discounts_rest_for_close_friends(): void
    {
        $inn = $this->forge->city->places()->create(['category' => 'inn', 'slug' => 'singa-emas', 'name' => 'Singa Emas']);
        $rosalind = $inn->npcs()->create(['slug' => 'rosalind', 'name' => 'Rosalind', 'role' => 'Pemilik', 'gender' => 'female', 'greeting' => 'Halo.']);
        DB::table('character_npc')->insert(['character_id' => $this->char->id, 'npc_id' => $rosalind->id, 'affection' => 60]);
        $this->char->update(['hp' => 10]);

        $this->actingAs($this->user)->get(route('town.place', $inn))->assertInertia(fn (Assert $page) => $page->where('rest_cost', 27));
        $this->actingAs($this->user)->post(route('town.rest', $inn));
        $this->assertSame(473, $this->char->fresh()->gold); // 10 × level 3 = 30, −10%
    }

    public function test_a_merchant_who_hates_you_may_slam_the_door(): void
    {
        $this->setAffection(-60);

        Lottery::alwaysWin();
        $this->visit()->assertRedirect(route('town.show'))->assertSessionHas('error', 'Garrick membanting pintu.');

        Lottery::alwaysLose();
        $this->visit()->assertOk();
        Lottery::determineResultNormally();
    }

    private function completeMission(string $slug): void
    {
        $quest = Quest::create(['slug' => $slug, 'title' => $slug, 'min_level' => 1, 'affiliation' => 'adventurer']);
        $this->char->update(['active_quest_id' => $quest->id]);
        app(RankService::class)->complete($this->char->fresh(), $quest);
    }

    public function test_finishing_a_mission_earns_reputation_in_the_current_kingdom_up_to_100(): void
    {
        $astoria = Country::first();
        $service = app(AffectionService::class);

        $this->completeMission('misi-1');
        $this->assertSame(5, $service->reputation($this->char->fresh(), $astoria->id));

        DB::table('character_reputations')->where('character_id', $this->char->id)->update(['reputation' => 98]);
        $this->completeMission('misi-2');
        $this->assertSame(100, $service->reputation($this->char->fresh(), $astoria->id));
    }

    public function test_guild_npcs_never_refuse_service(): void
    {
        $guild = $this->forge->city->places()->create(['category' => 'adventurer_guild', 'slug' => 'guild', 'name' => 'Guild']);
        $rowan = $guild->npcs()->create(['slug' => 'rowan', 'name' => 'Rowan', 'role' => 'Kepala Guild', 'gender' => 'male', 'greeting' => 'Halo.']);
        DB::table('character_npc')->insert(['character_id' => $this->char->id, 'npc_id' => $rowan->id, 'affection' => -90]);

        Lottery::alwaysWin();
        $this->actingAs($this->user)->get(route('town.place', $guild))->assertOk();
        Lottery::determineResultNormally();
    }
}
