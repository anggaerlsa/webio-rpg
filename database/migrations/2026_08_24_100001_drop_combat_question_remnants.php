<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuang sisa dua fitur yang sudah mati:
 *
 * 1. **Combat berbasis pertanyaan** (pensiun Juni 2026 saat combat diganti model
 *    serangan): tabel `combat_questions` + kolom `combat_sessions.current_question_id`
 *    & `asked_question_ids`. Tak ada satu pun kode yang membacanya lagi.
 * 2. **`node_choices.is_auto`** — flag yang tidak pernah meneruskan adegan secara
 *    otomatis, dan datanya membuktikan ia hanya bernilai true di node berpilihan
 *    tunggal (jadi tak membawa informasi apa pun).
 *
 * Isi `combat_questions` di lingkungan pengembangan (6 baris prompt era kuis)
 * diarsipkan lebih dulu ke `storage/app/combat_questions-arsip-2026-08-24.json`
 * karena tidak ada di file konten mana pun. `down()` mengembalikan STRUKTUR-nya,
 * bukan datanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite menolak DROP COLUMN untuk kolom yang masih disebut definisi FK,
        // dan sejak Laravel 11 tak ada lagi jalur rebuild otomatis — jadi tabelnya
        // dibangun ulang manual, DENGAN menyalin sesi yang sedang berjalan.
        if (DB::getDriverName() === 'sqlite') {
            $keep = ['id', 'character_id', 'quest_node_id', 'monster_id', 'monster_hp',
                'player_hp', 'status', 'turn', 'created_at', 'updated_at'];

            Schema::rename('combat_sessions', 'combat_sessions_lama');
            $this->createSessionsTable();
            $columns = implode(', ', $keep);
            DB::statement("INSERT INTO combat_sessions ({$columns}) SELECT {$columns} FROM combat_sessions_lama");
            Schema::drop('combat_sessions_lama');
        } else {
            Schema::table('combat_sessions', function (Blueprint $table) {
                $table->dropForeign(['current_question_id']);
            });
            Schema::table('combat_sessions', function (Blueprint $table) {
                $table->dropColumn(['current_question_id', 'asked_question_ids']);
            });
        }

        Schema::dropIfExists('combat_questions');

        Schema::table('node_choices', function (Blueprint $table) {
            $table->dropColumn('is_auto');
        });
    }

    /** Bentuk `combat_sessions` setelah dua kolom era kuis dibuang. */
    private function createSessionsTable(): void
    {
        Schema::create('combat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quest_node_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monster_id')->constrained()->cascadeOnDelete();
            $table->integer('monster_hp');
            $table->integer('player_hp');
            $table->string('status')->default('active'); // active, won, lost
            $table->unsignedInteger('turn')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('node_choices', function (Blueprint $table) {
            $table->boolean('is_auto')->default(false);
        });

        Schema::create('combat_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monster_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->default('tactical'); // tactical, trivia
            $table->text('prompt');
            $table->json('options');
            $table->unsignedTinyInteger('correct_index');
            $table->integer('player_damage_on_wrong')->nullable();
            $table->integer('monster_damage_on_correct')->nullable();
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::table('combat_sessions', function (Blueprint $table) {
            $table->foreignId('current_question_id')->nullable()->constrained('combat_questions')->nullOnDelete();
            $table->json('asked_question_ids')->nullable();
        });
    }
};
