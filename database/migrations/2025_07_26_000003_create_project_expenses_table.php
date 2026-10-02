<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('project_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('budget_category_id')->nullable();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('submitted_by');
            $table->decimal('amount', 15, 2);
            $table->decimal('approved_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->date('expense_date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('vendor')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'requires_info'])->default('pending');
            $table->boolean('is_recurring')->default(false);
            $table->boolean('receipt_required')->default(false);
            $table->decimal('receipt_threshold', 10, 2)->nullable();
            $table->json('approval_workflow')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status'], 'idx_project_status');
            $table->index(['budget_category_id', 'status']);
            $table->index(['submitted_by', 'status']);
            $table->index(['status', 'created_at'], 'idx_status_created_at');
            $table->index('expense_date');
            
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('budget_category_id')->references('id')->on('budget_categories')->onDelete('set null');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('set null');
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('project_expenses');
    }
};