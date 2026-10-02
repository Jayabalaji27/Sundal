<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskChecklist;
use App\Models\TaskStage;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $projects = Project::with(['members.user', 'milestones', 'workspace.activeMembers.user'])->get();

        foreach ($projects as $project) {
            $projectMembers = $project->members->pluck('user')->filter();
            $milestones = $project->milestones;
            $workspace = $project->workspace;
            $stages = TaskStage::forWorkspace($workspace->id)->ordered()->get();

            if ($stages->isEmpty() || $projectMembers->isEmpty()) continue;

            // Create 12-18 tasks per project for better visibility
            $totalTasks = rand(12, 18);
            $isProjectCompleted = $project->status === 'completed';
            $doneStage = $stages->where('name', 'Done')->first();
            
            for ($taskIndex = 1; $taskIndex <= $totalTasks; $taskIndex++) {
                // 20% chance for unassigned tasks
                $assignedMember = rand(0, 4) === 0 ? null : $projectMembers->random();
                $milestone = $milestones->random();
                
                // For completed projects, all tasks go to Done stage
                if ($isProjectCompleted && $doneStage) {
                    $taskStage = $doneStage;
                    $progress = 100;
                } else {
                    // Distribute tasks across stages for active projects
                    $taskStage = $this->getTaskStageForIndex($stages, $taskIndex, $totalTasks);
                    $progress = $this->getProgressForStage($taskStage->name);
                }
                
                $task = Task::create([
                    'project_id' => $project->id,
                    'task_stage_id' => $taskStage->id,
                    'milestone_id' => $milestone->id,
                    'title' => $this->getTaskTitle($taskIndex),
                    'description' => $this->getTaskDescription($taskIndex),
                    'priority' => $this->getRandomPriority(),
                    'start_date' => now()->subDays(rand(1, 30)),
                    'end_date' => $this->getTaskEndDate($taskIndex, $totalTasks),
                    'assigned_to' => $assignedMember?->id,
                    'created_by' => $project->created_by,
                    'progress' => $progress,
                ]);
                    
                // Create 2-4 comments per task
                for ($c = 1; $c <= rand(2, 4); $c++) {
                    TaskComment::create([
                        'task_id' => $task->id,
                        'user_id' => $projectMembers->random()->id,
                        'comment' => $this->getTaskComment($c),
                        'created_at' => now()->subDays(rand(1, 15)),
                    ]);
                }

                // Create 3-6 checklist items per task
                for ($cl = 1; $cl <= rand(3, 6); $cl++) {
                    TaskChecklist::create([
                        'task_id' => $task->id,
                        'title' => $this->getChecklistItem($cl),
                        'is_completed' => $isProjectCompleted ? 1 : rand(0, 1),
                        'assigned_to' => rand(0, 1) ? $projectMembers->random()->id : null,
                        'order' => $cl,
                        'created_by' => $projectMembers->random()->id,
                    ]);
                }
            }
        }
    }

    private function getTaskTitle(int $index): string
    {
        $titles = [
            'Database Schema Design', 'User Authentication Implementation', 'API Endpoint Development',
            'Frontend Component Creation', 'Payment Gateway Integration', 'Security Vulnerability Assessment',
            'Performance Optimization', 'Unit Test Development', 'Documentation Update',
            'Code Review Process', 'Bug Fix Implementation', 'Feature Enhancement',
            'Data Migration Script', 'Third-party Integration', 'UI/UX Improvements'
        ];

        return $titles[($index - 1) % count($titles)];
    }

    private function getTaskDescription(int $index): string
    {
        $descriptions = [
            'Design and implement the database schema for the new feature module.',
            'Implement secure user authentication with multi-factor authentication support.',
            'Develop RESTful API endpoints with proper validation and error handling.',
            'Create reusable frontend components following design system guidelines.',
            'Integrate payment gateway with support for multiple payment methods.',
            'Conduct comprehensive security assessment and implement fixes.',
            'Optimize application performance and reduce loading times.',
            'Write comprehensive unit tests to ensure code quality.',
            'Update technical documentation and user guides.',
            'Conduct thorough code review and provide feedback.',
            'Identify and fix critical bugs reported by QA team.',
            'Enhance existing features based on user feedback.',
            'Create data migration scripts for production deployment.',
            'Integrate with third-party services and APIs.',
            'Improve user interface and user experience based on usability testing.'
        ];

        return $descriptions[($index - 1) % count($descriptions)];
    }

    private function getTaskComment(int $index): string
    {
        $comments = [
            'Great progress on this task! The implementation looks solid.',
            'I have some concerns about the approach. Can we discuss this?',
            'This is ready for testing. Please review when you have time.',
            'Found a few issues during testing. Details in the attached document.',
            'Excellent work! This exceeds the requirements.',
            'Need clarification on the business requirements before proceeding.',
            'The performance improvements are impressive. Well done!',
            'This needs some refactoring before we can merge it.'
        ];

        return $comments[($index - 1) % count($comments)];
    }

    private function getChecklistItem(int $index): string
    {
        $items = [
            'Review requirements and specifications',
            'Set up development environment',
            'Create initial code structure',
            'Implement core functionality',
            'Write unit tests',
            'Conduct code review',
            'Update documentation',
            'Deploy to staging environment',
            'Perform integration testing',
            'Get stakeholder approval'
        ];

        return $items[($index - 1) % count($items)];
    }

    private function getRandomPriority(): string
    {
        return collect(['low', 'medium', 'high', 'critical'])->random();
    }

    private function getTaskStageForIndex($stages, int $taskIndex, int $totalTasks)
    {
        // Distribute tasks: 40% To Do, 25% In Progress, 15% Review, 10% Blocked, 10% Done
        $percentage = ($taskIndex - 1) / $totalTasks;
        
        if ($percentage < 0.4) {
            return $stages->where('name', 'To Do')->first() ?? $stages->first();
        } elseif ($percentage < 0.65) {
            return $stages->where('name', 'In Progress')->first() ?? $stages->skip(1)->first();
        } elseif ($percentage < 0.8) {
            return $stages->where('name', 'Review')->first() ?? $stages->skip(2)->first();
        } elseif ($percentage < 0.9) {
            return $stages->where('name', 'Blocked')->first() ?? $stages->skip(3)->first();
        } else {
            return $stages->where('name', 'Done')->first() ?? $stages->last();
        }
    }

    private function getProgressForStage(string $stageName): int
    {
        $stageLower = strtolower($stageName);
        
        if (str_contains($stageLower, 'todo')) return rand(0, 15);
        if (str_contains($stageLower, 'progress')) return rand(20, 70);
        if (str_contains($stageLower, 'review')) return rand(70, 95);
        if (str_contains($stageLower, 'blocked')) return rand(25, 80);
        if (str_contains($stageLower, 'done')) return 100;
        
        return rand(0, 100);
    }
    
    private function getTaskEndDate(int $taskIndex, int $totalTasks)
    {
        // 25% of tasks should be overdue
        if ($taskIndex <= $totalTasks * 0.25) {
            return now()->subDays(rand(1, 30)); // Overdue by 1-30 days
        }
        
        // 50% of tasks have near future deadlines
        if ($taskIndex <= $totalTasks * 0.75) {
            return now()->addDays(rand(1, 30)); // Due in 1-30 days
        }
        
        // 25% of tasks have far future deadlines
        return now()->addDays(rand(31, 90)); // Due in 31-90 days
    }
}