<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spells', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('element')->default('arcane'); // api, air, angin, tanah, cahaya, kegelapan, arcane
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('mana_cost')->default(0);
            $table->integer('power')->default(0);        // magnitude of damage/heal
            $table->unsignedInteger('min_level')->default(1);
            $table->json('effects')->nullable();         // {type: damage|heal|buff, ...}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spells');
    }
};
