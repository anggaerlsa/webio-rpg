<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combat_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monster_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->default('tactical'); // tactical, trivia
            $table->text('prompt');
            $table->json('options'); // ["A","B","C","D"]
            $table->unsignedTinyInteger('correct_index');
            $table->integer('player_damage_on_wrong')->nullable();   // overrides monster.attack
            $table->integer('monster_damage_on_correct')->nullable(); // overrides character.attack
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combat_questions');
    }
};
