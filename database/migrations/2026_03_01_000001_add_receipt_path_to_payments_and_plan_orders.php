<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments') && !Schema::hasColumn('payments', 'receipt_path')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('receipt_path')->nullable()->after('notes');
            });
        }

        if (Schema::hasTable('plan_orders') && !Schema::hasColumn('plan_orders', 'receipt_path')) {
            Schema::table('plan_orders', function (Blueprint $table) {
                $table->string('receipt_path')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'receipt_path')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('receipt_path');
            });
        }

        if (Schema::hasColumn('plan_orders', 'receipt_path')) {
            Schema::table('plan_orders', function (Blueprint $table) {
                $table->dropColumn('receipt_path');
            });
        }
    }
};
