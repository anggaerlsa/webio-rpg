<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quest_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('type')->default('narrative'); // narrative, choice, combat, reward, ending
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('image')->nullable();
            $table->foreignId('monster_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable(); // reward/ending data, on_win_node_key, on_lose_node_key
            $table->timestamps();

            $table->unique(['quest_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_nodes');
    }
};
