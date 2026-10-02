<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (Schema::hasColumn('invoices', 'discount_amount')) {
                    $table->dropColumn('discount_amount');
                }

                if (Schema::hasColumn('invoices', 'tax_rate')) {
                    $table->json('tax_rate')->change();
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('invoices', 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 2)->default(0);
                }

                if (Schema::hasColumn('invoices', 'tax_rate')) {
                    $table->decimal('tax_rate', 5, 2)->default(0)->change();
                }
            });
        }
    }
};