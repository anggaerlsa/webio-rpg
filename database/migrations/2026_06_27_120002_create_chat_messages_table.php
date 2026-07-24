<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pesan chat: dunia (global) atau DM (terikat ke friendship). Fana — pesan lebih
// dari 30 menit otomatis dihapus (lihat ChatService::prune + perintah chat:prune).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // pengirim
            $table->string('scope'); // world | dm
            $table->foreignId('friendship_id')->nullable()->constrained()->cascadeOnDelete(); // DM
            $table->text('body');
            $table->timestamps();

            $table->index(['scope', 'id']);
            $table->index(['friendship_id', 'id']);
            $table->index('created_at'); // untuk prune by usia
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
