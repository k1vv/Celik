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
        // One row per (attempt, dimension, tag) - see engine.md section 3/6.
        // Only written when that dimension surfaces a gap; a fully correct
        // attempt has no rows here.
        Schema::create('attempt_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->string('dimension'); // conceptual | curriculum | method | reasoning
            $table->string('tag');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attempt_diagnoses');
    }
};
