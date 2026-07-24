<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quest_node_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monster_id')->constrained()->cascadeOnDelete();
            $table->integer('monster_hp');
            $table->integer('player_hp');
            $table->foreignId('current_question_id')->nullable()->constrained('combat_questions')->nullOnDelete();
            $table->json('asked_question_ids')->nullable(); // anti-replay / no-repeat
            $table->string('status')->default('active'); // active, won, lost
            $table->unsignedInteger('turn')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combat_sessions');
    }
};
