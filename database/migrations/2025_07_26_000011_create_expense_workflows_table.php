<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('expense_workflows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_expense_id');
            $table->integer('step');
            $table->unsignedBigInteger('approver_id');
            $table->enum('status', ['pending', 'approved', 'rejected', 'waiting', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['project_expense_id', 'step']);
            $table->index(['approver_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('expense_workflows');
    }
};