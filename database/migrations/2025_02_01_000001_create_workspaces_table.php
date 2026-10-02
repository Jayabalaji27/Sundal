<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Timesheet settings
            $table->boolean('timesheet_enabled')->default(true);
            $table->enum('timesheet_approval_required', ['none', 'manager', 'admin'])->default('manager');
            $table->boolean('timesheet_auto_submit')->default(false);
            $table->integer('timesheet_reminder_days')->default(3);
            $table->time('default_work_start')->default('09:00:00');
            $table->time('default_work_end')->default('17:00:00');
            
            // Budget settings
            $table->json('budget_settings')->nullable();
            
            $table->timestamps();
            
            $table->index(['owner_id']);
            $table->index(['slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};