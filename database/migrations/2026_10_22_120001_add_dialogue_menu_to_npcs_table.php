<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Dialog NPC jadi menu pilihan (pilih → jawab) + sapaan menurut sikap NPC
// (ketus / netral / hangat), jawaban "Minta maaf", dan pesan menolak melayani.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('npcs', function (Blueprint $table) {
            $table->text('greeting_cold')->nullable()->after('greeting');
            $table->text('greeting_warm')->nullable()->after('greeting_cold');
            $table->text('apology_reply')->nullable()->after('greeting_warm');
            $table->text('refusal_message')->nullable()->after('apology_reply');
            $table->json('choices')->nullable()->after('refusal_message');
            $table->dropColumn('lines');
        });
    }

    public function down(): void
    {
        Schema::table('npcs', function (Blueprint $table) {
            $table->json('lines')->nullable();
            $table->dropColumn(['greeting_cold', 'greeting_warm', 'apology_reply', 'refusal_message', 'choices']);
        });
    }
};
