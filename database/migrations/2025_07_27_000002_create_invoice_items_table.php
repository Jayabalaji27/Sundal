<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->unsignedBigInteger('timesheet_entry_id')->nullable();
            
            $table->string('type')->default('custom'); // custom, task, expense, time
            $table->string('description');

            $table->decimal('rate', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();

            // Indexes
            $table->index(['invoice_id', 'sort_order']);
            $table->index('task_id');
            $table->index('expense_id');
            
            // Foreign keys
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('set null');
            $table->foreign('expense_id')->references('id')->on('project_expenses')->onDelete('set null');
            $table->foreign('timesheet_entry_id')->references('id')->on('timesheet_entries')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoice_items');
    }
};