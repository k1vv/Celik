<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            // Nullable: the existing single-question POST /homework path
            // (and every quiz attempt) isn't part of any Submission. Set
            // when this Attempt was produced by grading one question inside
            // a multi-question PDF submission - mirrors practice_set_id's
            // existing nullable-FK pattern for PracticeSet.
            $table->foreignId('submission_id')->nullable()->after('practice_set_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submission_id');
        });
    }
};
