<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every table records who created a row. These three had no creator column
 * (see "Who created or changed a record" in the AI Assistant plan).
 * Nullable: existing rows have no known creator.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'sprint_tasks' => 'created_by',
        'contracts_attachments' => 'uploaded_by',
        'contacts' => 'created_by',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->foreignId($column)->nullable()->constrained('users')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->dropConstrainedForeignId($column);
                });
            }
        }
    }
};
