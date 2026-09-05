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
        // A curriculum grouping ("Heat") that Skill rows belong to. Scoped
        // to Subject, not Classroom - Skill is already a shared, subject-
        // level bank reused across every classroom of that subject (mastery
        // tracking and skill_prerequisites both depend on that sharing), so
        // Module mirrors Skill/Classroom's existing plain-FK-to-Subject
        // shape rather than being owned by one classroom.
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
