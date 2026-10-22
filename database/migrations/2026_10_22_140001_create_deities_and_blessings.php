<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kuil: dewa-dewi (boleh terhubung ke akun GM) dan berkah yang bisa diminta
// pemain — satu berkah aktif per karakter, sekali doa per 24 jam.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('title');
            $table->string('domain')->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('blessing')->nullable();
            $table->text('blessing_message')->nullable();
            $table->timestamps();
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->string('blessing')->nullable();
            $table->foreignId('blessing_deity_id')->nullable()->constrained('deities')->nullOnDelete();
            $table->timestamp('blessing_until')->nullable();
            $table->timestamp('last_prayed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('blessing_deity_id');
            $table->dropColumn(['blessing', 'blessing_until', 'last_prayed_at']);
        });
        Schema::dropIfExists('deities');
    }
};
