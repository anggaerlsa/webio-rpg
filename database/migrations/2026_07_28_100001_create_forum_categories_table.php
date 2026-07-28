<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Kategori Balai Warta (forum). Struktural — dikelola Panel Dewa, bukan konten JSON.
// `scope` disiapkan untuk ekspansi (global | country | city | guild); slice 1 semuanya global.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('scope')->default('global');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_locked')->default(false); // terkunci: hanya Dewa boleh buat topik
            $table->string('min_rank')->nullable();       // rank minimal untuk membuat topik
            $table->timestamps();

            $table->index(['position', 'id']);
        });

        // Kategori awal — bisa diubah/ditambah lewat Panel Dewa.
        $defaults = [
            ['warta-kerajaan', 'Warta Kerajaan', 'Maklumat resmi dari para Dewa. Hanya Dewa yang membuka topik di sini.', true],
            ['kedai-minum', 'Kedai Minum', 'Obrolan bebas antar petualang sambil menenggak ale.', false],
            ['papan-strategi', 'Papan Strategi', 'Taktik pertarungan, susunan perlengkapan, dan bocoran misi.', false],
            ['balai-rekrutmen', 'Balai Rekrutmen', 'Cari rekan seperjalanan atau umumkan dirimu mencari kelompok.', false],
            ['ruang-keluhan', 'Ruang Keluhan', 'Laporkan keanehan dunia (bug) dan sampaikan saran.', false],
        ];
        foreach ($defaults as $i => [$slug, $name, $description, $locked]) {
            DB::table('forum_categories')->insert([
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'scope' => 'global',
                'position' => $i,
                'is_locked' => $locked,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_categories');
    }
};
