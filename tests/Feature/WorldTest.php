<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorldTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        $user = User::factory()->create();
        $user->role = 'superadmin';
        $user->job = 'Dewa Pencipta';
        $user->save();

        return $user;
    }

    public function test_regular_players_are_forbidden_from_world(): void
    {
        $user = User::factory()->create(); // role defaults to "player"

        $this->actingAs($user)->get('/admin/world/countries')->assertForbidden();
    }

    public function test_superadmin_can_open_world_pages(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->get(route('admin.world.countries.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.world.countries.create'))->assertOk();
    }

    public function test_creating_a_country_auto_creates_a_capital_city(): void
    {
        $admin = $this->superadmin();

        $res = $this->actingAs($admin)->post(route('admin.world.countries.store'), [
            'slug' => 'astoria', 'name' => 'Kerajaan Astoria', 'capital_name' => 'Kota Eldoria',
        ]);

        $country = Country::where('slug', 'astoria')->firstOrFail();
        $capital = $country->capitalCity();

        $this->assertNotNull($capital);
        $this->assertSame('Kota Eldoria', $capital->name);
        $this->assertSame(1, $country->provinces()->count()); // provinsi implisit sebagai wadah
        // Setelah membuat negara, langsung diarahkan ke ibukota untuk mengisi tempat.
        $res->assertRedirect(route('admin.world.cities.show', $capital));
    }

    public function test_capital_defaults_to_country_name_when_blank(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post(route('admin.world.countries.store'), [
            'slug' => 'borealis', 'name' => 'Borealis',
        ])->assertRedirect();

        $capital = Country::where('slug', 'borealis')->firstOrFail()->capitalCity();
        $this->assertSame('Borealis', $capital->name);
    }

    public function test_full_location_hierarchy_can_be_created(): void
    {
        $admin = $this->superadmin();

        // Negara
        $this->actingAs($admin)->post(route('admin.world.countries.store'), [
            'slug' => 'astoria', 'name' => 'Kerajaan Astoria',
        ])->assertRedirect();
        $country = Country::where('slug', 'astoria')->firstOrFail();

        // Provinsi (di-scope oleh negara)
        $this->actingAs($admin)->post(route('admin.world.countries.provinces.store', $country->id), [
            'slug' => 'utara', 'name' => 'Provinsi Utara',
        ])->assertRedirect();
        $province = Province::where('slug', 'utara')->firstOrFail();
        $this->assertSame($country->id, $province->country_id);

        // Kota (di-scope oleh provinsi)
        $this->actingAs($admin)->post(route('admin.world.provinces.cities.store', $province->id), [
            'slug' => 'eldoria', 'name' => 'Kota Eldoria',
        ])->assertRedirect();
        $city = City::where('slug', 'eldoria')->firstOrFail();
        $this->assertSame($province->id, $city->province_id);

        // Desa (di-scope oleh kota)
        $this->actingAs($admin)->post(route('admin.world.cities.villages.store', $city->id), [
            'slug' => 'mata-air', 'name' => 'Desa Mata Air',
        ])->assertRedirect();
        $village = Village::where('slug', 'mata-air')->firstOrFail();
        $this->assertSame($city->id, $village->city_id);

        // Tempat: penginapan dengan nama custom (di-scope oleh kota)
        $this->actingAs($admin)->post(route('admin.world.cities.places.store', $city->id), [
            'category' => 'inn', 'slug' => 'rabbit-moon-inn', 'name' => 'Rabbit Moon Inn',
        ])->assertRedirect(route('admin.world.cities.show', $city->id));

        $this->assertDatabaseHas('places', [
            'city_id' => $city->id, 'category' => 'inn', 'slug' => 'rabbit-moon-inn', 'name' => 'Rabbit Moon Inn',
        ]);

        // Halaman drill-down kota memuat tempat & desanya.
        $this->actingAs($admin)->get(route('admin.world.cities.show', $city->id))->assertOk();
    }

    public function test_place_category_must_be_valid(): void
    {
        $admin = $this->superadmin();
        $country = Country::create(['slug' => 'a', 'name' => 'A']);
        $province = $country->provinces()->create(['slug' => 'p', 'name' => 'P']);
        $city = $province->cities()->create(['slug' => 'c', 'name' => 'C']);

        $this->actingAs($admin)->post(route('admin.world.cities.places.store', $city->id), [
            'category' => 'kategori-ngawur', 'slug' => 'x', 'name' => 'X',
        ])->assertSessionHasErrors('category');
    }

    public function test_country_slug_must_be_unique_and_well_formed(): void
    {
        $admin = $this->superadmin();
        Country::create(['slug' => 'astoria', 'name' => 'Astoria']);

        $this->actingAs($admin)->post(route('admin.world.countries.store'), [
            'slug' => 'astoria', 'name' => 'Duplikat',
        ])->assertSessionHasErrors('slug');

        $this->actingAs($admin)->post(route('admin.world.countries.store'), [
            'slug' => 'Bukan Slug!', 'name' => 'X',
        ])->assertSessionHasErrors('slug');
    }

    public function test_deleting_a_country_cascades_to_all_descendants(): void
    {
        $admin = $this->superadmin();

        $country = Country::create(['slug' => 'astoria', 'name' => 'Astoria']);
        $province = $country->provinces()->create(['slug' => 'utara', 'name' => 'Utara']);
        $city = $province->cities()->create(['slug' => 'eldoria', 'name' => 'Eldoria']);
        $city->villages()->create(['slug' => 'mata-air', 'name' => 'Mata Air']);
        $city->places()->create(['category' => 'inn', 'slug' => 'rabbit-moon-inn', 'name' => 'Rabbit Moon Inn']);

        $this->actingAs($admin)->delete(route('admin.world.countries.destroy', $country->id))
            ->assertRedirect(route('admin.world.countries.index'));

        $this->assertDatabaseMissing('countries', ['id' => $country->id]);
        $this->assertDatabaseMissing('provinces', ['id' => $province->id]);
        $this->assertDatabaseMissing('cities', ['id' => $city->id]);
        $this->assertDatabaseMissing('villages', ['slug' => 'mata-air']);
        $this->assertDatabaseMissing('places', ['slug' => 'rabbit-moon-inn']);
    }
}
