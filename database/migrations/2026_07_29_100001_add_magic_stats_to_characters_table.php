<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pemisahan serangan fisik vs sihir: `attack`/`defense` kini khusus fisik,
// `magic_attack`/`magic_defense` khusus sihir. Nilai dasar sengaja dibuat sama
// dengan padanan fisiknya (10/5) supaya karakter lama tidak berubah kekuatannya —
// yang berpengaruh ke damage hanya BONUS dari perlengkapan di atas nilai dasar.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('magic_attack')->default(10)->after('attack');
            $table->unsignedInteger('magic_defense')->default(5)->after('defense');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['magic_attack', 'magic_defense']);
        });
    }
};
