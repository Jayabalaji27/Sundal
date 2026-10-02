<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contracts')) {

            Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_id')->unique();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('contract_type_id');
            $table->decimal('contract_value', 15, 2)->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['pending', 'sent', 'accept', 'decline', 'expired'])->default('pending');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->json('assigned_users')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('created_by');
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->longText('company_signature')->nullable();
            $table->longText('client_signature')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();

            $table->foreign('contract_type_id')->references('id')->on('contracts_types')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            
            $table->index(['workspace_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};