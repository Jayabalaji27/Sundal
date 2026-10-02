<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('expense_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_expense_id');
            $table->unsignedBigInteger('approver_id');
            $table->enum('status', ['pending', 'approved', 'rejected', 'requires_info']);
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->integer('approval_level')->default(1);
            $table->timestamps();

            $table->index(['project_expense_id', 'status']);
            $table->index(['approver_id', 'status']);
            
            $table->foreign('project_expense_id')->references('id')->on('project_expenses')->onDelete('cascade');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('expense_approvals');
    }
};