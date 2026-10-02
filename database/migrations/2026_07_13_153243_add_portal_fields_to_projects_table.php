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
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('portal_enabled')->default(false)->after('is_public');
            $table->string('portal_token', 48)->unique()->nullable()->after('portal_enabled');
            $table->text('portal_message')->nullable()->after('portal_token'); // welcome message
        });
    }
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['portal_enabled','portal_token','portal_message']);
        });
    }
};
