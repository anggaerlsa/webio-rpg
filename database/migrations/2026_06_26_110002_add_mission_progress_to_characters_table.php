<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Misi aktif (satu per waktu) + progres menuju rank berikutnya.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            // Misi yang sedang diambil/dikerjakan. Null = tidak ada (ambil di guild).
            $table->foreignId('active_quest_id')->nullable()->after('rank')
                ->constrained('quests')->nullOnDelete();
            // Jumlah misi selesai sejak rank saat ini (reset tiap naik rank).
            $table->unsignedInteger('rank_progress')->default(0)->after('active_quest_id');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_quest_id');
            $table->dropColumn('rank_progress');
        });
    }
};
