<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pertemanan antar user (1 akun = 1 karakter). Permintaan → diterima.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('addressee_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending | accepted
            $table->timestamps();

            $table->unique(['requester_id', 'addressee_id']);
            $table->index('addressee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
