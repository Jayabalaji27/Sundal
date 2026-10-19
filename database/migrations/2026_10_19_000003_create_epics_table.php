<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Epics (as in Zoho Sprints and Jira): a large piece of work, such as a
 * feature or a module, that groups tasks and bugs across sprints. An item
 * belongs to at most one epic; deleting an epic keeps its items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 20)->default('#8b5cf6');
            // open | done
            $table->string('status', 20)->default('open');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('epic_id')->nullable()->after('milestone_id')->constrained('epics')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('epic_id');
        });
        Schema::dropIfExists('epics');
    }
};
