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
            // quiz | homework - see engine.md §3. Distinguishes which path
            // an attempt came from; both write into this same table.
            $table->string('source')->default('quiz')->after('question_id');
            $table->text('raw_answer')->nullable()->after('is_correct');
            $table->float('confidence')->nullable()->after('raw_answer');
        });

        Schema::table('attempts', function (Blueprint $table) {
            // Quiz-only concepts (adaptive difficulty, mastery delta) don't
            // apply the same way to a homework attempt yet - see engine.md §5.
            $table->unsignedTinyInteger('difficulty_at_attempt')->nullable()->change();
            $table->float('mastery_before')->nullable()->change();
            $table->float('mastery_after')->nullable()->change();
            // A homework attempt's correctness isn't known until it's
            // diagnosed, unlike a quiz attempt (known immediately).
            $table->boolean('is_correct')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn(['source', 'raw_answer', 'confidence']);
        });

        Schema::table('attempts', function (Blueprint $table) {
            $table->unsignedTinyInteger('difficulty_at_attempt')->nullable(false)->change();
            $table->float('mastery_before')->nullable(false)->change();
            $table->float('mastery_after')->nullable(false)->change();
            $table->boolean('is_correct')->nullable(false)->change();
        });
    }
};
