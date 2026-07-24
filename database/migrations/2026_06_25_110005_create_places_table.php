<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tempat (bangunan/fasilitas) di dalam sebuah Kota: Guild, Pasar, Blacksmith, Penginapan, dst.
// "category" = jenis tetap (lihat App\Models\Place::CATEGORIES); "name" boleh custom (mis. "Rabbit Moon Inn").
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('category')->index(); // adventurer_guild, merchant_guild, city_hall, market, blacksmith, potion_shop, inn
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
