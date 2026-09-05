<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            // Nullable: an ordinary adaptive-practice attempt isn't part of
            // any teacher-authored set. Set when a quiz answer is submitted
            // from within a PracticeSet, so per-set progress can be computed.
            $table->foreignId('practice_set_id')->nullable()->after('question_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('practice_set_id');
        });
    }
};
