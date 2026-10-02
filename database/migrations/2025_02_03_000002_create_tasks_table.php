<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('task_stage_id')->constrained('task_stages')->onDelete('cascade');
            $table->unsignedBigInteger('milestone_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->integer('progress')->default(0);
            $table->timestamps();

            $table->foreign('milestone_id')->references('id')->on('project_milestones')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->index(['project_id', 'task_stage_id']);
            $table->index(['assigned_to', 'created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};