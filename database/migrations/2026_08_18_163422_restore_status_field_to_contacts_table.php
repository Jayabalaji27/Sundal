<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// An earlier migration (2026_01_30_000004_remove_status_field_from_contacts_table)
// dropped `contacts.status`, but Contact.php and every method in
// ContactController.php (index/store/update/show/updateStatus/bulkUpdateStatus/export)
// still read and write that column, breaking contact creation, updates, filtering
// and export. Restore it, matching the pattern used to fix the same issue on
// newsletters in 2026_08_18_003525_restore_status_fields_to_newsletters_table.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contacts') && !Schema::hasColumn('contacts', 'status')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->enum('status', ['new', 'read', 'replied', 'closed'])->default('new')->after('message');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contacts') && Schema::hasColumn('contacts', 'status')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
