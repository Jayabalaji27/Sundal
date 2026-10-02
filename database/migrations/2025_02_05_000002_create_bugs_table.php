<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bugs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('bug_status_id')->constrained('bug_statuses')->onDelete('cascade');
            $table->unsignedBigInteger('milestone_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('severity', ['minor', 'major', 'critical', 'blocker'])->default('major');
            $table->text('steps_to_reproduce')->nullable();
            $table->text('expected_behavior')->nullable();
            $table->text('actual_behavior')->nullable();
            $table->string('environment')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->foreign('milestone_id')->references('id')->on('project_milestones')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['project_id', 'bug_status_id']);
            $table->index(['assigned_to', 'reported_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bugs');
    }
};