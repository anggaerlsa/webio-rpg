<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Atribut RPG kini berbasis 1: nilai 1 = baseline (efek 0). Default kolom 5 → 1,
// dan karakter lama (yang masih memakai placeholder 5) di-rebaseline ke 1.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('strength')->default(1)->change();
            $table->unsignedInteger('agility')->default(1)->change();
            $table->unsignedInteger('dexterity')->default(1)->change();
            $table->unsignedInteger('intelligence')->default(1)->change();
            $table->unsignedInteger('vitality')->default(1)->change();
            $table->unsignedInteger('luck')->default(1)->change();
        });

        // Rebaseline data dev: turunkan stat lama (placeholder 5) ke 1.
        DB::table('characters')->update([
            'strength' => 1, 'agility' => 1, 'dexterity' => 1,
            'intelligence' => 1, 'vitality' => 1, 'luck' => 1,
        ]);
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('strength')->default(5)->change();
            $table->unsignedInteger('agility')->default(5)->change();
            $table->unsignedInteger('dexterity')->default(5)->change();
            $table->unsignedInteger('intelligence')->default(5)->change();
            $table->unsignedInteger('vitality')->default(5)->change();
            $table->unsignedInteger('luck')->default(5)->change();
        });
    }
};
