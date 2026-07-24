<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Atribut RPG dasar. Disimpan dengan nama lengkap; label UI ringkas: STR/AGI/DEX/INT/VIT/LUK.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('strength')->default(5)->after('defense');     // STR — damage fisik
            $table->unsignedInteger('agility')->default(5)->after('strength');     // AGI — menghindar
            $table->unsignedInteger('dexterity')->default(5)->after('agility');    // DEX — kritikal
            $table->unsignedInteger('intelligence')->default(5)->after('dexterity'); // INT — damage sihir
            $table->unsignedInteger('vitality')->default(5)->after('intelligence'); // VIT — pertahanan
            $table->unsignedInteger('luck')->default(5)->after('vitality');        // LUK — kritikal & emas
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['strength', 'agility', 'dexterity', 'intelligence', 'vitality', 'luck']);
        });
    }
};
