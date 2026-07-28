<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reputasi = akumulasi apresiasi yang diterima pesan-pesan karakter di Balai Warta.
// Murni sosial: tidak memberi XP, emas, atau stat tempur.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('reputation')->default(0)->after('rank_progress');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('reputation');
        });
    }
};
