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
        // ClientPortalController::submitBug() inserts reported_by = null for
        // anonymous portal submissions, but this column was required — every
        // portal bug report was failing with a "column cannot be null" error.
        Schema::table('bugs', function (Blueprint $table) {
            $table->foreignId('reported_by')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bugs', function (Blueprint $table) {
            $table->foreignId('reported_by')->nullable(false)->change();
        });
    }
};
