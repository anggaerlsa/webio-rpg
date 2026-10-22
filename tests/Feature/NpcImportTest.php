<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Npc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class NpcImportTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        File::delete($this->paths);

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $data */
    private function writeNpc(array $data): void
    {
        $path = database_path("content/npcs/{$data['slug']}.json");
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->paths[] = $path;
    }

    public function test_import_attaches_npcs_to_their_place_and_skips_missing_places(): void
    {
        $country = Country::create(['slug' => 'uji', 'name' => 'Uji']);
        $city = $country->provinces()->create(['slug' => 'uji-wil', 'name' => 'Wilayah'])->cities()->create(['slug' => 'kota-uji', 'name' => 'Kota Uji']);
        $forge = $city->places()->create(['category' => 'blacksmith', 'slug' => 'tempa-uji', 'name' => 'Tempa Uji']);

        $choice = ['label' => 'Halo?', 'reply' => 'Hm.', 'affection' => 0, 'min_level' => null];
        $this->writeNpc([
            'place' => 'tempa-uji', 'slug' => 'pandai-uji', 'name' => 'Pandai Uji', 'role' => 'Pandai Besi', 'gender' => 'male',
            'greeting' => 'Hm.', 'greeting_cold' => 'Keluar.', 'greeting_warm' => 'Duduk.', 'choices' => [$choice],
        ]);
        $this->writeNpc([
            'place' => 'tempat-yang-tak-ada', 'slug' => 'npc-yatim', 'name' => 'Yatim', 'role' => 'Pengelana', 'gender' => 'female', 'greeting' => 'Halo.',
        ]);

        $this->artisan('game:import')->assertSuccessful();

        $npc = Npc::where('slug', 'pandai-uji')->firstOrFail();
        $this->assertSame($forge->id, $npc->place_id);
        $this->assertSame('Keluar.', $npc->greeting_cold);
        $this->assertSame([$choice], $npc->choices);
        $this->assertNull(Npc::where('slug', 'npc-yatim')->first());
    }
}
