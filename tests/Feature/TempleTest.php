<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Deity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TempleTest extends TestCase
{
    use RefreshDatabase;

    private Deity $aurelia;

    private Deity $arkatros;

    private User $user;

    private Character $char;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aurelia = Deity::create([
            'slug' => 'aurelia', 'name' => 'Aurelia', 'title' => 'Dewi Cinta Kasih', 'domain' => 'Cinta',
            'description' => 'Senyumnya menghangatkan hati.', 'blessing' => 'affection_opposite', 'blessing_message' => 'Kelopak mawar berjatuhan.',
        ]);
        $this->arkatros = Deity::create(['slug' => 'arkatros', 'name' => 'Arkatros', 'title' => 'Dewa Pencipta', 'domain' => 'Penciptaan', 'description' => 'Dunia inilah suaranya.']);

        $this->user = User::factory()->create();
        $this->char = $this->user->characters()->create([
            'name' => 'Hero', 'gender' => 'female', 'level' => 1, 'xp' => 0, 'hp' => 50, 'max_hp' => 50,
            'attack' => 10, 'defense' => 5, 'gold' => 0, 'is_alive' => true,
        ]);
    }

    private function pray(Deity $deity)
    {
        return $this->actingAs($this->user->fresh())->post(route('temple.pray', $deity));
    }

    public function test_praying_grants_a_one_hour_blessing_and_a_day_long_cooldown(): void
    {
        $this->pray($this->aurelia)->assertRedirect()->assertSessionHas('success', 'Kelopak mawar berjatuhan.');

        $char = $this->char->fresh();
        $this->assertSame('affection_opposite', $char->blessing);
        $this->assertEqualsWithDelta(now()->addHour()->timestamp, $char->blessing_until->timestamp, 2);

        $this->pray($this->aurelia)->assertSessionHas('error');

        $this->travel(25)->hours();
        $this->pray($this->aurelia)->assertSessionHas('success');
    }

    public function test_a_game_master_without_a_blessing_cannot_be_prayed_to(): void
    {
        $this->pray($this->arkatros)->assertSessionHas('error');

        $this->assertNull($this->char->fresh()->last_prayed_at);
    }

    public function test_a_genderless_hero_is_refused_without_spending_the_daily_prayer(): void
    {
        $this->char->update(['gender' => null]);
        $this->pray($this->aurelia)->assertSessionHas('error');
        $this->assertNull($this->char->fresh()->last_prayed_at);

        $this->char->update(['gender' => 'male']);
        $this->pray($this->aurelia)->assertSessionHas('success');
    }

    public function test_the_temple_lists_the_gods_and_the_heros_blessing_state(): void
    {
        $gm = User::factory()->create(['name' => 'Arkatros']);
        $this->arkatros->update(['user_id' => $gm->id]);

        $this->actingAs($this->user)->get(route('temple.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Temple/Index')
                ->has('deities', 2)
                ->where('deities.0.slug', 'arkatros')
                ->where('deities.0.is_gm', true)
                ->where('deities.0.blessing', null)
                ->where('deities.1.slug', 'aurelia')
                ->where('deities.1.blessing', 'Afeksi lawan jenis ×2 (1 jam)')
                ->where('active_blessing', null)
                ->where('cooldown_until', null));

        $this->pray($this->aurelia);

        $this->actingAs($this->user->fresh())->get(route('temple.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('active_blessing.deity', 'Aurelia')
                ->where('active_blessing.label', 'Afeksi lawan jenis ×2 (1 jam)')
                ->has('active_blessing.until')
                ->has('cooldown_until'));
    }

    private function superadmin(): User
    {
        $user = User::factory()->create(['name' => 'GM']);
        $user->role = 'superadmin';
        $user->save();

        return $user;
    }

    public function test_superadmin_can_add_a_god_linked_to_a_gm_account(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post(route('admin.deities.store'), [
            'slug' => 'dewa-hujan', 'name' => 'Dewa Hujan', 'title' => 'Dewa Hujan', 'domain' => 'Air',
            'description' => 'Ia yang menurunkan hujan.', 'user_id' => $admin->id, 'blessing' => null,
        ])->assertRedirect(route('admin.deities.index'));

        $this->assertSame($admin->id, Deity::where('slug', 'dewa-hujan')->value('user_id'));
    }

    public function test_a_god_can_only_grant_a_known_blessing(): void
    {
        $this->actingAs($this->superadmin())->post(route('admin.deities.store'), [
            'slug' => 'x', 'name' => 'X', 'title' => 'Y', 'blessing' => 'abadi-selamanya',
        ])->assertSessionHasErrors('blessing');
    }

    public function test_players_cannot_manage_gods(): void
    {
        $this->actingAs($this->user)->get(route('admin.deities.index'))->assertForbidden();
    }

    public function test_import_links_gods_to_gm_accounts_by_email(): void
    {
        $gm = User::factory()->create(['email' => 'gm-uji@example.com']);
        $path = database_path('content/deities/dewa-uji.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'slug' => 'dewa-uji', 'name' => 'Dewa Uji', 'title' => 'Dewa Ujian', 'user_email' => 'gm-uji@example.com',
        ]));

        try {
            $this->artisan('game:import')->assertSuccessful();
        } finally {
            File::delete($path);
        }

        $this->assertSame($gm->id, Deity::where('slug', 'dewa-uji')->value('user_id'));
    }
}
