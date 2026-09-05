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
        // A teacher's release of one Question to one Classroom - the link
        // between "homework a student can see" and "the classroom they're
        // in" that Question deliberately doesn't have itself (it's a shared
        // bank, see engine.md §6). unique() on question_id makes "one
        // assignment per question" a DB constraint rather than just an
        // assumption - reused-question assignments are future work.
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('title'); // "Homework 1" - teacher-facing label, not the question body
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
