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
        // Retrieval context for homework grading, keyed by KSSM code rather
        // than per-question - see engine.md §5. Small, hand-authored table
        // ("no vector DB needed at this scale"), not derived at request time.
        Schema::create('topic_groundings', function (Blueprint $table) {
            $table->id();
            $table->string('kssm_code')->unique();
            $table->text('syllabus_excerpt');
            $table->text('rubric');
            $table->json('tag_vocabulary'); // {"conceptual": [...], "method": [...], "reasoning": [...]}
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topic_groundings');
    }
};
