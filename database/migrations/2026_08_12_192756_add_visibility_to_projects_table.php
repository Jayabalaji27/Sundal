<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->enum('visibility', ['private', 'workspace'])->default('private')->after('is_public');
        });

        // Backfill: existing projects were de facto visible workspace-wide (any
        // workspace member could see them). Defaulting them to 'private' here
        // would silently hide projects members could already see. Only NEW
        // projects going forward get the private-by-default behavior.
        DB::table('projects')->update(['visibility' => 'workspace']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
