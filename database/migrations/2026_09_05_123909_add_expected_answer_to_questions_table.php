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
            // Objective sub-part (if any), e.g. a numeric result - a
            // deterministic check, no AI needed for correctness itself,
            // see engine.md §5. Null for fully open-ended questions.
            $table->string('expected_answer')->nullable()->after('kssm_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('expected_answer');
        });
    }
};
