<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_set_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamps();
            $table->unique(['practice_set_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_set_questions');
    }
};
