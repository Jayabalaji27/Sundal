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
        if (!Schema::hasTable('plans')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->string('plan_type', 20)->default('base')->after('name');

            // Grandfathering flag: plans that existed before the add-on gate
            // shipped keep granting AI/Knowledge Base/Chat/Meetings access
            // exactly as they do today (permission-only gating), so existing
            // companies are not silently locked out. New plans created after
            // this ships default to false — the add-on gate applies to them.
            $table->boolean('legacy_addon_access')->default(false)->after('plan_type');
        });

        // Every plan that already existed before this migration ran is
        // grandfathered in, preserving today's access for existing companies.
        DB::table('plans')->update(['legacy_addon_access' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('plans')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['plan_type', 'legacy_addon_access']);
        });
    }
};
