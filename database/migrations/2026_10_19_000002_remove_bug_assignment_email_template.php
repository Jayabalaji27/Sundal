<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bugs are tasks of type "bug", so assigning one sends the Task Assignment
 * email. The Bug Assignment template can no longer be sent; remove it so it
 * doesn't sit in Settings › Email Templates. Its translations and per-user
 * switches go with it (foreign keys cascade).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')->where('name', 'Bug Assignment')->delete();
    }

    /** Nothing to restore: the template has no sender any more. */
    public function down(): void
    {
    }
};
