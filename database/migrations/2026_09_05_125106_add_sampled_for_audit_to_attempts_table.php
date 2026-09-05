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
        Schema::table('attempts', function (Blueprint $table) {
            // Trusted but spot-checked, distinct from needs_review
            // (untrusted) - a random slice audited regardless of confidence,
            // so systematic confidently-wrong grading can eventually be
            // caught and CONFIDENCE_THRESHOLD calibrated against real
            // teacher grading.
            $table->boolean('sampled_for_audit')->default(false)->after('needs_review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn('sampled_for_audit');
        });
    }
};
