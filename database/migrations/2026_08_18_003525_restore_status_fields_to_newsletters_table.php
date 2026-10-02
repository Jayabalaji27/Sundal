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
        // 2026_01_30_000003_remove_status_fields_from_newsletters_table dropped
        // these columns, but NewsletterController (index filtering, store,
        // update, toggle-status, export) and the newsletters/index.tsx UI never
        // stopped depending on them — every create/update/filter call 500s with
        // "Unknown column 'status'". Restore the columns the rest of the
        // feature already assumes exist.
        Schema::table('newsletters', function (Blueprint $table) {
            if (!Schema::hasColumn('newsletters', 'subscribed_at')) {
                $table->timestamp('subscribed_at')->nullable();
            }
            if (!Schema::hasColumn('newsletters', 'unsubscribed_at')) {
                $table->timestamp('unsubscribed_at')->nullable();
            }
            if (!Schema::hasColumn('newsletters', 'status')) {
                $table->enum('status', ['subscribed', 'unsubscribed'])->default('subscribed');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            if (Schema::hasColumn('newsletters', 'subscribed_at')) {
                $table->dropColumn('subscribed_at');
            }
            if (Schema::hasColumn('newsletters', 'unsubscribed_at')) {
                $table->dropColumn('unsubscribed_at');
            }
            if (Schema::hasColumn('newsletters', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
