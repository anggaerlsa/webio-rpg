<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monsters', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('image')->nullable();
            $table->integer('max_hp');
            $table->unsignedInteger('attack');
            $table->unsignedInteger('defense')->default(0);
            $table->unsignedInteger('xp_reward')->default(0);
            $table->unsignedInteger('gold_reward')->default(0);
            $table->json('loot')->nullable(); // [{item_slug, chance, qty}]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monsters');
    }
};
