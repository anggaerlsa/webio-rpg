<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Resource karakter selain HP: SP (stamina, untuk serangan fisik) & MP (magic, untuk sihir).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('sp')->default(30)->after('max_hp');
            $table->unsignedInteger('max_sp')->default(30)->after('sp');
            $table->unsignedInteger('mp')->default(30)->after('max_sp');
            $table->unsignedInteger('max_mp')->default(30)->after('mp');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['sp', 'max_sp', 'mp', 'max_mp']);
        });
    }
};
