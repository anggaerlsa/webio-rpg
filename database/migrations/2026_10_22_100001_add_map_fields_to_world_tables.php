<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Peta dunia bertingkat: posisi kerajaan di peta benua & posisi kota/benteng
// di peta kerajaan, dalam persen (0–100) supaya tetap pas di layar mana pun.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('map_image')->nullable()->after('image');
            $table->decimal('map_x', 5, 2)->nullable()->after('map_image');
            $table->decimal('map_y', 5, 2)->nullable()->after('map_x');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->string('type')->default('city')->after('name');
            $table->decimal('map_x', 5, 2)->nullable()->after('image');
            $table->decimal('map_y', 5, 2)->nullable()->after('map_x');
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn(['map_image', 'map_x', 'map_y']);
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn(['type', 'map_x', 'map_y']);
        });
    }
};
