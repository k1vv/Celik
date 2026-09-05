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
            // Set for a photographed/handwritten submission (image or PDF -
            // see engine.md §5); null for a typed answer, where raw_answer
            // is used instead. Never both.
            $table->string('photo_path')->nullable()->after('raw_answer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
