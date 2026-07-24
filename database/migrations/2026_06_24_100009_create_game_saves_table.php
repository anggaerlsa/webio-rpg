<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot')->default(1);
            $table->foreignId('quest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('current_node_key')->nullable();
            $table->json('state')->nullable(); // visited nodes, story flags, snapshot
            $table->timestamp('last_played_at')->nullable();
            $table->timestamps();

            $table->unique(['character_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_saves');
    }
};
