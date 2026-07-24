<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Ambang naik rank: berapa misi harus diselesaikan untuk naik DARI rank ini.
// Diatur oleh Panel Dewa (superadmin). Rank tertinggi (S) tidak punya baris.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rank_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rank')->unique(); // F, E, D, C, B, A
            $table->unsignedInteger('missions_required');
            $table->timestamps();
        });

        // Default tunable (admin bisa ubah lewat panel).
        $defaults = ['F' => 3, 'E' => 5, 'D' => 8, 'C' => 12, 'B' => 18, 'A' => 25];
        foreach ($defaults as $rank => $required) {
            DB::table('rank_rules')->insert([
                'rank' => $rank,
                'missions_required' => $required,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rank_rules');
    }
};
