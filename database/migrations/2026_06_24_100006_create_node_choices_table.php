<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quest_node_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('next_node_key')->nullable();
            $table->json('requirements')->nullable(); // {min_level, has_item, min_gold, flag}
            $table->json('effects')->nullable();       // {hp, xp, gold, give_item, take_item, flags}
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_auto')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_choices');
    }
};
