<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('contracts_attachments')) {

            Schema::create('contracts_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->foreignId('contract_id')->constrained()->onDelete('cascade');
            $table->string('files')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'contract_id']);
            $table->index(['contract_id']);
        });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('contracts_attachments');
    }
};