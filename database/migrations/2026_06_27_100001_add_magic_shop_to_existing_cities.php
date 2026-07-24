<?php

use App\Models\City;
use App\Models\Place;
use Illuminate\Database\Migrations\Migration;

// Pastikan tiap kota yang sudah ada punya satu Toko Sihir (tempat membeli buku skill/sihir).
// Idempoten: hanya menambah bila kota belum punya tempat berkategori magic_shop.
return new class extends Migration
{
    public function up(): void
    {
        City::query()->each(function (City $city) {
            $exists = $city->places()->where('category', 'magic_shop')->exists();
            if ($exists) {
                return;
            }
            $city->places()->create([
                'category' => 'magic_shop',
                'slug' => 'toko-sihir-'.$city->slug,
                'name' => 'Menara Mantra '.$city->name,
                'description' => 'Toko sihir '.$city->name.' — menjual buku skill & mantra bagi yang haus ilmu.',
            ]);
        });
    }

    public function down(): void
    {
        Place::where('category', 'magic_shop')->delete();
    }
};
