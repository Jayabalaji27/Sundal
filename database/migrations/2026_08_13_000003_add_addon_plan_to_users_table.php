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
        // Mirrors the plan_id/plan_expire_date/plan_is_active triplet, which
        // only exists on SaaS-mode installs (see 0001_01_01_000000_create_users_table.php).
        if (!Schema::hasColumn('users', 'plan_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('addon_plan_id')->nullable()->after('plan_id')->constrained('plans')->nullOnDelete();
            $table->date('addon_expire_date')->nullable()->after('plan_expire_date');
            $table->integer('addon_is_active')->default(0)->after('plan_is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('users', 'addon_plan_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['addon_plan_id']);
            $table->dropColumn(['addon_plan_id', 'addon_expire_date', 'addon_is_active']);
        });
    }
};
