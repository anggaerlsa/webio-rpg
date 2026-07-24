<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('class')->nullable();
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('xp')->default(0);
            $table->integer('hp');
            $table->integer('max_hp');
            $table->unsignedInteger('attack')->default(10);
            $table->unsignedInteger('defense')->default(5);
            $table->unsignedInteger('gold')->default(0);
            $table->string('avatar')->nullable();
            $table->json('attributes')->nullable();
            $table->boolean('is_alive')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};
