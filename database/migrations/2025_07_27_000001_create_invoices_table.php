<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('created_by');
            
            // Invoice details
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            
            // Status and payment
            $table->enum('status', ['draft', 'sent', 'viewed', 'paid', 'partial_paid', 'overdue', 'cancelled'])->default('draft');
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->json('payment_details')->nullable();
            
            // Client information
            $table->json('client_details')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            
            $table->timestamps();

            // Indexes
            $table->index(['project_id', 'status']);
            $table->index(['workspace_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index(['status', 'due_date']);
            $table->index('invoice_date');
            
            // Foreign keys
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoices');
    }
};