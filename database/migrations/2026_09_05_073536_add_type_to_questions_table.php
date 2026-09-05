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
        Schema::table('questions', function (Blueprint $table) {
            // 'mcq' (quiz path, options/correct_option required) or
            // 'homework' (open-ended, no fixed options) - see engine.md §5.
            $table->string('type')->default('mcq')->after('skill_id');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->json('options')->nullable()->change();
            $table->string('correct_option')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->json('options')->nullable(false)->change();
            $table->string('correct_option')->nullable(false)->change();
            $table->dropColumn('type');
        });
    }
};
