<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contacts')) {
            Schema::table('contacts', function (Blueprint $table) {
                if (Schema::hasColumn('contacts', 'status')) {
                    $table->dropColumn('status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contacts') && !Schema::hasColumn('contacts', 'status')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->enum('status', ['new', 'read', 'replied', 'closed'])->default('new');
            });
        }
    }
};
