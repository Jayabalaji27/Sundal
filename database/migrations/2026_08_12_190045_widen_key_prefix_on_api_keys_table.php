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
        // ApiKey::generate() produces a 12-char prefix ("swt_" + 8 random chars),
        // but this column was created as varchar(8) — every key creation was
        // failing with a "Data too long" error. Widen with headroom.
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('key_prefix', 20)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('key_prefix', 8)->change();
        });
    }
};
