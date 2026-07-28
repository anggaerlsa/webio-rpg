<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Penanda kunjungan terakhir ke Balai Warta — dasar hitungan lencana "balasan baru"
// di sidebar (tanpa tabel notifikasi tersendiri).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('forum_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('forum_seen_at');
        });
    }
};
