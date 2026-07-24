<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('name');   // male, female
            $table->date('birth_date')->nullable()->after('gender'); // -> usia pemain
            $table->string('affiliation')->nullable()->after('class'); // adventurer, merchant
            $table->string('rank')->nullable()->after('affiliation');  // F, E, D, ...
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['gender', 'birth_date', 'affiliation', 'rank']);
        });
    }
};
