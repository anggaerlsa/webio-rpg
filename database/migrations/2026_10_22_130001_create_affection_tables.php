<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Afeksi pribadi pemain ↔ NPC (−100…100) beserta catatan harian anti-spam,
// dan reputasi pemain per kerajaan (0…100) dari misi yang diselesaikan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_npc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('npc_id')->constrained()->cascadeOnDelete();
            $table->integer('affection')->default(0);
            $table->date('day')->nullable();
            $table->unsignedTinyInteger('gained_today')->default(0);
            $table->unsignedTinyInteger('lost_today')->default(0);
            $table->json('used_today')->nullable();
            $table->timestamps();
            $table->unique(['character_id', 'npc_id']);
        });

        Schema::create('character_reputations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('reputation')->default(0);
            $table->timestamps();
            $table->unique(['character_id', 'country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_reputations');
        Schema::dropIfExists('character_npc');
    }
};
