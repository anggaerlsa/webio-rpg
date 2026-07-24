<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Riwayat misi yang sudah diselesaikan tiap karakter (misi selesai = hilang dari papan guild).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_quest', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['character_id', 'quest_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_quest');
    }
};
