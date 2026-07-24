<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Quest = Misi. Field misi-guild: guild penyelenggara + rank minimal untuk mengambil.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            // Guild penyelenggara: 'adventurer' | 'merchant' | null (quest cerita non-guild).
            $table->string('affiliation')->nullable()->after('description');
            // Rank minimal untuk mengambil misi ini (F..S). Null = tanpa syarat rank.
            $table->string('required_rank')->nullable()->after('affiliation');
        });
    }

    public function down(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->dropColumn(['affiliation', 'required_rank']);
        });
    }
};
