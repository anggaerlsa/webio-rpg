<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('type')->default('physical'); // physical, dll.
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->integer('power')->default(1);          // damage dasar
            $table->unsignedInteger('level_req')->default(1);
            $table->boolean('is_default')->default(false); // dimiliki semua karakter (mis. Pukul)
            $table->json('effects')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
