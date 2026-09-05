<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Maps a wrong option's letter to the misconception it reveals,
            // e.g. {"B": "metal_inherently_colder"}. Authored with the item,
            // not derived at grading time - see engine.md.
            $table->json('distractor_map')->nullable()->after('correct_option');
            $table->string('kssm_code')->nullable()->after('distractor_map');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['distractor_map', 'kssm_code']);
        });
    }
};
