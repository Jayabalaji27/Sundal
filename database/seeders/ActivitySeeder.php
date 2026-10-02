<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        $projects = Project::with(['members.user'])->get();

        foreach ($projects as $project) {
            $projectMembers = $project->members->pluck('user')->filter();
            
            if ($projectMembers->isEmpty()) continue;

            // Create 15-20 activities per project
            $activityCount = rand(15, 20);
            
            for ($i = 1; $i <= $activityCount; $i++) {
                $activityData = $this->getActivityData($project, $i);
                
                ProjectActivity::create([
                    'project_id' => $project->id,
                    'user_id' => $projectMembers->random()->id,
                    'action' => $activityData['action'],
                    'description' => $activityData['description'],
                    'metadata' => $activityData['metadata'],
                    'created_at' => now()->subDays(rand(1, 60)),
                ]);
            }
        }
    }

    private function getActivityData(Project $project, int $index): array
    {
        $activities = [
            // Budget activities
            [
                'action' => 'projectbudget_created',
                'description' => "Budget 'Project Development Budget' was created with amount 50000 USD",
                'metadata' => ['model_type' => 'App\\Models\\ProjectBudget', 'amount' => 50000, 'currency' => 'USD']
            ],
            [
                'action' => 'projectbudget_updated',
                'description' => "Budget 'Project Development Budget' was updated",
                'metadata' => ['model_type' => 'App\\Models\\ProjectBudget', 'changes' => ['total_budget' => 55000]]
            ],
            // Expense activities
            [
                'action' => 'projectexpense_created',
                'description' => "Expense 'Software Licenses' was submitted for 1,200.00 USD",
                'metadata' => ['model_type' => 'App\\Models\\ProjectExpense', 'amount' => 1200, 'title' => 'Software Licenses']
            ],
            [
                'action' => 'projectexpense_status_changed',
                'description' => "Expense 'Office Supplies' status changed from pending to approved",
                'metadata' => ['old_status' => 'pending', 'new_status' => 'approved', 'title' => 'Office Supplies']
            ],
            [
                'action' => 'projectexpense_updated',
                'description' => "Expense 'Travel Expenses' was updated",
                'metadata' => ['model_type' => 'App\\Models\\ProjectExpense', 'title' => 'Travel Expenses']
            ],
            // Task activities
            [
                'action' => 'task_created',
                'description' => "Task 'Database Schema Design' was created",
                'metadata' => ['model_type' => 'App\\Models\\Task', 'title' => 'Database Schema Design']
            ],
            [
                'action' => 'task_updated',
                'description' => "Task 'API Development' was updated",
                'metadata' => ['model_type' => 'App\\Models\\Task', 'title' => 'API Development']
            ],
            [
                'action' => 'task_stage_changed',
                'description' => "Task 'Frontend Implementation' moved from In Progress to Review",
                'metadata' => ['old_stage' => 'In Progress', 'new_stage' => 'Review', 'title' => 'Frontend Implementation']
            ],
            [
                'action' => 'task_assigned',
                'description' => "Task 'Testing & QA' was assigned to John Doe",
                'metadata' => ['assigned_to' => 'John Doe', 'title' => 'Testing & QA']
            ],
            // Timesheet activities
            [
                'action' => 'timesheet_created',
                'description' => "Timesheet for Jan 15 - Jan 21, 2024 was created (40 hours)",
                'metadata' => ['model_type' => 'App\\Models\\Timesheet', 'total_hours' => 40, 'period' => 'Jan 15 - Jan 21, 2024']
            ],
            [
                'action' => 'timesheet_status_changed',
                'description' => "Timesheet for Jan 22 - Jan 28, 2024 status changed from draft to submitted",
                'metadata' => ['old_status' => 'draft', 'new_status' => 'submitted', 'period' => 'Jan 22 - Jan 28, 2024']
            ],
            [
                'action' => 'timesheet_updated',
                'description' => "Timesheet for Jan 29 - Feb 04, 2024 was updated",
                'metadata' => ['model_type' => 'App\\Models\\Timesheet', 'period' => 'Jan 29 - Feb 04, 2024']
            ],
            // General project activities
            [
                'action' => 'project_created',
                'description' => "Project '{$project->title}' was created",
                'metadata' => ['model_type' => 'App\\Models\\Project', 'project_id' => $project->id]
            ],
            [
                'action' => 'milestone_created',
                'description' => 'New milestone added to project',
                'metadata' => ['milestone_name' => 'Project Planning & Analysis']
            ],
            [
                'action' => 'member_added',
                'description' => 'Team member added to project',
                'metadata' => ['role' => 'member']
            ],
            [
                'action' => 'note_added',
                'description' => 'Project note added: Weekly progress update',
                'metadata' => ['note_title' => 'Weekly progress update']
            ],
            [
                'action' => 'file_uploaded',
                'description' => 'New file uploaded to project',
                'metadata' => ['file_name' => 'project_requirements.pdf']
            ],
            [
                'action' => 'comment_added',
                'description' => 'Comment added to task discussion',
                'metadata' => ['task_title' => 'API Development']
            ],
            [
                'action' => 'progress_updated',
                'description' => "Project progress updated to {$project->progress}%",
                'metadata' => ['progress' => $project->progress]
            ],
            [
                'action' => 'client_assigned',
                'description' => 'Client assigned to project',
                'metadata' => ['client_role' => 'client']
            ]
        ];

        return $activities[($index - 1) % count($activities)];
    }
}