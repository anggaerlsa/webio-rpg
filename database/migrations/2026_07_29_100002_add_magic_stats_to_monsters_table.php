<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sisi monster dari pemisahan fisik/sihir. `attack_kind` menentukan serangan
// balik monster memakai jalur mana (dan karena itu pertahanan pemain yang mana).
// Default 0/'physical' → monster lama berperilaku persis seperti sebelumnya.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monsters', function (Blueprint $table) {
            $table->unsignedInteger('magic_attack')->default(0)->after('attack');
            $table->unsignedInteger('magic_defense')->default(0)->after('defense');
            $table->string('attack_kind')->default('physical')->after('magic_attack'); // physical | magic
        });
    }

    public function down(): void
    {
        Schema::table('monsters', function (Blueprint $table) {
            $table->dropColumn(['magic_attack', 'magic_defense', 'attack_kind']);
        });
    }
};
