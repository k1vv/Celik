<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (student, assignment) PDF upload event - the grouping a
     * multi-question homework submission needs, since one uploaded file now
     * produces many per-question Attempts underneath it (see
     * attempts.submission_id in the next migration).
     */
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->string('photo_path');
            // pending: uploaded, not yet processed. extracted: Gemini
            // successfully segmented it into per-question answers. failed:
            // the extraction call itself errored (not a grading concern -
            // grading only runs on successfully extracted answers).
            $table->enum('extraction_status', ['pending', 'extracted', 'failed'])->default('pending');
            $table->timestamp('teacher_validated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
