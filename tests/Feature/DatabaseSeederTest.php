<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_akun_demo_tidak_lahir_di_produksi(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('quests', ['slug' => 'goblin-cave']);
    }

    public function test_akun_demo_tetap_ada_di_luar_produksi(): void
    {
        $this->artisan('db:seed')->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'admin@webio.test', 'role' => 'superadmin']);
    }
}
