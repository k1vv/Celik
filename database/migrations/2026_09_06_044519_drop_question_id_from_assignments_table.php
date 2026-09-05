<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assignment is now many-to-many with Question via assignment_questions
     * (see that migration) - a teacher's PDF upload can bundle several
     * questions, mirroring how PracticeSet already bundles quiz questions.
     * The old one-question-per-assignment shape (unique question_id) is
     * dropped rather than kept alongside, since nothing using it is
     * committed yet (see PROGRESS.md).
     */
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('question_id');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('question_id')->constrained()->cascadeOnDelete()->unique();
        });
    }
};
