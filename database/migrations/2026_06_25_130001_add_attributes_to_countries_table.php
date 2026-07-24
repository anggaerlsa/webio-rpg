<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Atribut lore sebuah negara: jenis pemerintahan, ideologi, gelar & nama penguasa, ras dominan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('government_type')->nullable()->after('name'); // Kekaisaran, Kesultanan, Kerajaan
            $table->string('ideology')->nullable()->after('government_type'); // Imperialis, Teokratis, Tiran, dst.
            $table->string('ruler_title')->nullable()->after('ideology'); // Kaisar, Sultan, Raja, Ratu, Raja Iblis
            $table->string('ruler_name')->nullable()->after('ruler_title'); // mis. Edwin Astoria XII
            $table->string('dominant_race')->nullable()->after('ruler_name'); // Manusia, Iblis, Elf, Kurcaci
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn(['government_type', 'ideology', 'ruler_title', 'ruler_name', 'dominant_race']);
        });
    }
};
