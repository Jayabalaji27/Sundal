<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                if (!Schema::hasColumn('projects', 'shared_settings')) {
                    $table->json('shared_settings')->nullable()->after('is_public');
                }
                if (!Schema::hasColumn('projects', 'password')) {
                    $table->string('password')->nullable()->after('shared_settings');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                if (Schema::hasColumn('projects', 'shared_settings')) {
                    $table->dropColumn('shared_settings');
                }
                if (Schema::hasColumn('projects', 'password')) {
                    $table->dropColumn('password');
                }
            });
        }
    }
};