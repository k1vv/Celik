<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            // Homework only - a rubric-based mark, distinct from is_correct
            // (a partially-correct answer can still score above zero). Quiz
            // attempts stay null: correctness alone is the signal there.
            $table->unsignedTinyInteger('score')->nullable()->after('is_correct');
            $table->unsignedTinyInteger('max_score')->nullable()->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn(['score', 'max_score']);
        });
    }
};
