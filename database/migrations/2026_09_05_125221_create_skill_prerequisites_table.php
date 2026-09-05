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
        // A DAG over Skill - hand-authored the same way distractor_map is,
        // see engine.md §8: encode the curriculum-expert judgment once
        // ("Specific Heat Capacity depends on Thermal Equilibrium"), reuse
        // it forever in GapSummaryService (root cause vs. symptom) and
        // AdaptivePracticeService (practice the root cause first).
        Schema::create('skill_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prerequisite_skill_id')->constrained('skills')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['skill_id', 'prerequisite_skill_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skill_prerequisites');
    }
};
