<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Plan;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('plans', 'sort_order')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->integer('sort_order')->default(0)->after('plan_type');
            });
        }

        // Backfill: preserve existing (id) order as the starting sort_order so
        // this migration doesn't itself reshuffle anything on deploy - admins
        // can then reorder from there.
        Plan::orderBy('id')->get()->each(function ($plan, $index) {
            $plan->update(['sort_order' => $index + 1]);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('plans', 'sort_order')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }
    }
};
