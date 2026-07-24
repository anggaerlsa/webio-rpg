<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\City;
use App\Models\Country;
use App\Models\Item;
use App\Models\Place;
use App\Models\Skill;
use App\Models\Spell;
use App\Models\User;
use App\Services\LearningService;
use App\Services\TownService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningTest extends TestCase
{
    use RefreshDatabase;

    private function learning(): LearningService
    {
        return app(LearningService::class);
    }

    /**
     * @return array{0: User, 1: Character}
     */
    private function player(int $level = 1): array
    {
        $user = User::factory()->create();
        $char = $user->characters()->create([
            'name' => 'Hero', 'level' => $level, 'xp' => 0, 'hp' => 50, 'max_hp' => 50,
            'attack' => 10, 'defense' => 5, 'gold' => 0, 'is_alive' => true,
        ]);

        return [$user, $char];
    }

    private function skillBook(string $skillSlug, int $levelReq = 1): array
    {
        $skill = Skill::create(['slug' => $skillSlug, 'name' => 'Tebas', 'type' => 'physical', 'power' => 4, 'level_req' => $levelReq, 'is_default' => false]);
        $book = Item::create(['slug' => 'buku-'.$skillSlug, 'name' => 'Buku '.$skillSlug, 'type' => 'book', 'stats' => ['teaches_skill' => $skillSlug], 'value' => 80]);

        return [$skill, $book];
    }

    private function spellBook(string $spellSlug, int $minLevel = 1): array
    {
        $spell = Spell::create(['slug' => $spellSlug, 'name' => 'Bola Api', 'element' => 'api', 'power' => 5, 'mana_cost' => 4, 'min_level' => $minLevel]);
        $book = Item::create(['slug' => 'buku-'.$spellSlug, 'name' => 'Gulungan '.$spellSlug, 'type' => 'book', 'stats' => ['teaches_spell' => $spellSlug], 'value' => 100]);

        return [$spell, $book];
    }

    public function test_reading_a_skill_book_learns_the_skill_and_consumes_it(): void
    {
        [, $char] = $this->player(level: 2);
        [$skill, $book] = $this->skillBook('tebas', levelReq: 2);
        $char->items()->attach($book->id, ['quantity' => 1]);

        $this->learning()->learn($char, $book);

        $this->assertTrue($char->skills()->whereKey($skill->id)->exists());
        $this->assertSame(0, $char->items()->count()); // buku habis
    }

    public function test_reading_a_spell_book_learns_the_spell(): void
    {
        [, $char] = $this->player(level: 1);
        [$spell, $book] = $this->spellBook('bola-api', minLevel: 1);
        $char->items()->attach($book->id, ['quantity' => 1]);

        $this->learning()->learn($char, $book);

        $this->assertTrue($char->spells()->whereKey($spell->id)->exists());
    }

    public function test_learning_is_blocked_below_required_level(): void
    {
        [, $char] = $this->player(level: 1);
        [, $book] = $this->spellBook('kilat', minLevel: 3);
        $char->items()->attach($book->id, ['quantity' => 1]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->learning()->learn($char, $book);
    }

    public function test_learning_an_already_known_ability_is_rejected(): void
    {
        [, $char] = $this->player(level: 2);
        [$skill, $book] = $this->skillBook('tebas', levelReq: 1);
        $char->skills()->attach($skill->id);   // sudah dikuasai
        $char->items()->attach($book->id, ['quantity' => 1]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->learning()->learn($char, $book);
    }

    public function test_a_plain_item_is_not_a_book(): void
    {
        $potion = Item::create(['slug' => 'potion', 'name' => 'Potion', 'type' => 'consumable', 'stats' => ['heal' => 30], 'value' => 25]);

        $this->assertNull($this->learning()->teaches($potion));
    }

    public function test_magic_shop_stocks_books(): void
    {
        $country = Country::create(['slug' => 'n', 'name' => 'N']);
        $province = $country->provinces()->create(['slug' => 'p', 'name' => 'P']);
        $city = $province->cities()->create(['slug' => 'c', 'name' => 'C']);
        $shop = $city->places()->create(['category' => 'magic_shop', 'slug' => 'sihir', 'name' => 'Toko Sihir']);
        [, $book] = $this->spellBook('bola-api');

        $town = app(TownService::class);
        $this->assertTrue($town->isShop($shop));
        $this->assertTrue($town->stock($shop)->contains('id', $book->id));
    }

    public function test_learn_via_http(): void
    {
        [$user, $char] = $this->player(level: 2);
        [$skill, $book] = $this->skillBook('tebas', levelReq: 2);
        $char->items()->attach($book->id, ['quantity' => 1]);

        $this->actingAs($user)->post(route('character.learn'), ['item_id' => $book->id])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertTrue($char->skills()->whereKey($skill->id)->exists());
    }
}
