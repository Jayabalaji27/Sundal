<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contracts_notes')) {

            Schema::create('contracts_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->text('note');
            $table->boolean('is_pinned')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('contracts_notes');
    }
};