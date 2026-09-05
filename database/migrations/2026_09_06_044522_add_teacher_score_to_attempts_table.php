<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            // AI proposes `score` (already exists); a teacher can override
            // it here before it counts. Effective score anywhere downstream
            // is teacher_score ?? score - null means not yet validated.
            $table->unsignedTinyInteger('teacher_score')->nullable()->after('max_score');
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn('teacher_score');
        });
    }
};
