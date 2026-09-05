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
            // A low-confidence homework diagnosis is flagged instead of
            // trusted - see engine.md §5. While true, attempt_diagnoses
            // rows are withheld so the dashboard doesn't aggregate an
            // unreviewed guess as if it were certain.
            $table->boolean('needs_review')->default(false)->after('confidence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn('needs_review');
        });
    }
};
