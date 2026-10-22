<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NPC = penghuni sebuah Tempat (pandai besi, pemilik penginapan, ...): potret,
// sapaan, dan beberapa baris dialog yang tampil di halaman tempat.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('npcs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('role');
            $table->string('gender');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->text('greeting');
            $table->json('lines')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('npcs');
    }
};
