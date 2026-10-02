<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        // Raw SQL: doctrine/dbal (required by Blueprint::change()) isn't installed.
        DB::statement('ALTER TABLE contracts MODIFY client_id BIGINT UNSIGNED NULL');

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        DB::statement('ALTER TABLE contracts MODIFY client_id BIGINT UNSIGNED NOT NULL');

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
