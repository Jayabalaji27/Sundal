<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('todos')) {
            Schema::create('todos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('created_by');
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
                $table->enum('status', ['pending', 'in_progress', 'completed', 'overdue'])->default('pending');
                $table->date('due_date')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'status']);
                $table->index(['created_by', 'status']);
                $table->index('priority');
                $table->index('due_date');
                $table->index('created_at');

                $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('todos');
    }
};
