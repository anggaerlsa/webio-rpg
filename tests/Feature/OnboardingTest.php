<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('game:import'); // butuh item kartu tanda
    }

    public function test_creating_a_character_captures_profile_and_keeps_commoner_job(): void
    {
        $user = User::factory()->create(['job' => 'Commoner']);

        $this->actingAs($user)->post('/character', [
            'name' => 'Aria',
            'gender' => 'female',
            'birth_date' => '2002-05-10',
            'class' => 'Mage',
        ])->assertRedirect(route('dashboard'));

        $char = $user->fresh()->character;
        $this->assertNotNull($char);
        $this->assertSame('female', $char->gender);
        $this->assertSame('Mage', $char->class);
        $this->assertNull($char->affiliation);
        $this->assertSame('Commoner', $user->fresh()->job);

        // Dashboard masih bisa dibuka (modal onboarding muncul di klien).
        $this->actingAs($user->fresh())->get('/dashboard')->assertOk();
    }

    public function test_character_creation_validates_profile_and_class(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/character', ['name' => 'X'])
            ->assertSessionHasErrors(['gender', 'birth_date', 'class']);

        $this->actingAs($user->fresh())->post('/character', [
            'name' => 'X', 'gender' => 'female', 'birth_date' => '2000-01-01', 'class' => 'Rogue',
        ])->assertSessionHasErrors('class'); // hanya Warrior / Mage
    }

    public function test_joining_adventurer_guild_sets_job_rank_and_grants_card(): void
    {
        $user = User::factory()->create(['job' => 'Commoner']);
        $this->actingAs($user)->post('/character', [
            'name' => 'Aria', 'gender' => 'male', 'birth_date' => '2000-01-01', 'class' => 'Warrior',
        ]);

        $this->actingAs($user->fresh())->post('/character/affiliation', ['affiliation' => 'adventurer'])
            ->assertRedirect(route('dashboard'));

        $char = $user->fresh()->character;
        $this->assertSame('adventurer', $char->affiliation);
        $this->assertSame('F', $char->rank);
        $this->assertSame('adventurer', $user->fresh()->job);
        $this->assertTrue($char->items()->where('slug', 'kartu-tanda-petualang')->exists());
    }

    public function test_joining_merchant_guild_grants_merchant_card(): void
    {
        $user = User::factory()->create(['job' => 'Commoner']);
        $this->actingAs($user)->post('/character', [
            'name' => 'Bro', 'gender' => 'male', 'birth_date' => '2000-01-01', 'class' => 'Warrior',
        ]);

        $this->actingAs($user->fresh())->post('/character/affiliation', ['affiliation' => 'merchant'])
            ->assertRedirect(route('dashboard'));

        $char = $user->fresh()->character;
        $this->assertSame('merchant', $char->affiliation);
        $this->assertSame('merchant', $user->fresh()->job);
        $this->assertTrue($char->items()->where('slug', 'kartu-tanda-dagang')->exists());
    }

    public function test_new_warrior_receives_a_training_sword_already_equipped(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/character', [
            'name' => 'Aria', 'gender' => 'female', 'birth_date' => '2000-01-01', 'class' => 'Warrior',
        ])->assertRedirect(route('dashboard'));

        $char = $user->fresh()->character;
        $weapon = $char->items()->where('slug', 'pedang-latihan')->first();

        $this->assertNotNull($weapon);
        $this->assertTrue((bool) $weapon->pivot->equipped);
        // Bonus senjata masuk ke stat efektif.
        $this->assertSame($char->attack + 1, app(\App\Services\EquipmentService::class)->effective($char)['attack']);
    }

    public function test_new_mage_receives_a_training_staff_instead(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/character', [
            'name' => 'Borin', 'gender' => 'male', 'birth_date' => '2000-01-01', 'class' => 'Mage',
        ])->assertRedirect(route('dashboard'));

        $char = $user->fresh()->character;
        $this->assertTrue($char->items()->where('slug', 'tongkat-latihan')->exists());
        $this->assertFalse($char->items()->where('slug', 'pedang-latihan')->exists());
    }
}
