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
        Schema::table('attempt_diagnoses', function (Blueprint $table) {
            // The exact phrase from the student's answer that justifies this
            // dimension's finding - lets a teacher spot-check a diagnosis
            // without re-reading the whole answer.
            $table->text('evidence')->nullable()->after('tag');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attempt_diagnoses', function (Blueprint $table) {
            $table->dropColumn('evidence');
        });
    }
};
