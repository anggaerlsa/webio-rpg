<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kota asal / lokasi karakter saat ini (home city). Nullable: dunia mungkin
// belum punya kota; karakter lama di-assign secara lazy saat membuka Kota.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('gold')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });
    }
};
