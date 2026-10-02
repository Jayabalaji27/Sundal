<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Invoice;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('invoices') &&
            Schema::hasColumn('invoices', 'status')
        ) {
            // Update existing records first
            Invoice::where('status', 'PartialPaid')
                ->update(['status' => 'partial_paid']);

            // Update enum definition
            Schema::table('invoices', function (Blueprint $table) {
                $table->enum('status', [
                    'draft',
                    'sent',
                    'viewed',
                    'paid',
                    'partial_paid',
                    'overdue',
                    'cancelled',
                ])->default('draft')->change();
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('invoices') &&
            Schema::hasColumn('invoices', 'status')
        ) {
            // Revert data
            Invoice::where('status', 'partial_paid')
                ->update(['status' => 'PartialPaid']);

            // Revert enum definition
            Schema::table('invoices', function (Blueprint $table) {
                $table->enum('status', [
                    'draft',
                    'sent',
                    'viewed',
                    'paid',
                    'PartialPaid',
                    'overdue',
                    'cancelled',
                ])->default('draft')->change();
            });
        }
    }
};