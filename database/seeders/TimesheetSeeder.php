<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\TimesheetApproval;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorkspaceMember;
use Carbon\Carbon;

class TimesheetSeeder extends Seeder
{
    public function run(): void
    {
        $workspaces = Workspace::with(['activeMembers', 'projects.tasks'])->get();

        foreach ($workspaces as $workspace) {
            $members = $workspace->activeMembers->where('role', '!=', 'client');
            
            if ($members->isEmpty()) {
                continue;
            }
            
            foreach ($members as $workspaceMember) {
                $user = User::find($workspaceMember->user_id);
                if (!$user || $user->type === 'client') continue;
                
                // Create timesheets for current month to next 2-3 months (12 weeks total)
                for ($weekOffset = 0; $weekOffset < 12; $weekOffset++) {
                    $startDate = Carbon::now()->addWeeks($weekOffset)->startOfWeek();
                    $endDate = Carbon::now()->addWeeks($weekOffset)->endOfWeek();
                    
                    $timesheet = Timesheet::create([
                        'user_id' => $user->id,
                        'workspace_id' => $workspace->id,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'status' => $this->getTimesheetStatus($weekOffset),
                        'total_hours' => 0,
                        'billable_hours' => 0,
                        'submitted_at' => $weekOffset < 2 ? $startDate->copy()->addDays(7) : null
                    ]);

                    // Get user's assigned projects
                    $userProjects = $workspace->projects()->whereHas('members', function($query) use ($user) {
                        $query->where('user_id', $user->id);
                    })->with('tasks')->get();
                    
                    if ($userProjects->isEmpty()) {
                        continue;
                    }
                    
                    // Create entries for each work day (Monday to Friday)
                    for ($day = 0; $day < 5; $day++) {
                        $workDate = $startDate->copy()->addDays($day);
                        $dailyHours = rand(6, 9); // 6-9 hours per day
                        $remainingHours = $dailyHours;
                        
                        // Distribute hours across projects
                        $projectsToWork = $userProjects->random(rand(1, min(3, $userProjects->count())));
                        
                        foreach ($projectsToWork as $index => $project) {
                            $isLastProject = ($index === $projectsToWork->count() - 1);
                            $hoursForProject = $isLastProject ? $remainingHours : rand(1, min(4, $remainingHours - 1));
                            
                            if ($hoursForProject <= 0) break;
                            
                            // Get tasks for this project
                            $projectTasks = $project->tasks()->where('assigned_to', $user->id)->get();
                            
                            if ($projectTasks->isNotEmpty()) {
                                // Distribute project hours across tasks
                                $tasksToWork = $projectTasks->random(rand(1, min(2, $projectTasks->count())));
                                $remainingProjectHours = $hoursForProject;
                                
                                foreach ($tasksToWork as $taskIndex => $task) {
                                    $isLastTask = ($taskIndex === $tasksToWork->count() - 1);
                                    $hoursForTask = $isLastTask ? $remainingProjectHours : rand(1, min(3, $remainingProjectHours - 1));
                                    
                                    if ($hoursForTask <= 0) break;
                                    
                                    TimesheetEntry::create([
                                        'timesheet_id' => $timesheet->id,
                                        'project_id' => $project->id,
                                        'task_id' => $task->id,
                                        'user_id' => $user->id,
                                        'date' => $workDate,
                                        'start_time' => $this->getStartTime($taskIndex),
                                        'end_time' => $this->getEndTime($taskIndex, $hoursForTask),
                                        'hours' => $hoursForTask,
                                        'description' => $this->getWorkDescription($task->title),
                                        'is_billable' => $this->isBillable($project, $task),
                                        'hourly_rate' => $this->getHourlyRate($workspaceMember->role)
                                    ]);
                                    
                                    $remainingProjectHours -= $hoursForTask;
                                }
                            } else {
                                // Create entry without specific task
                                TimesheetEntry::create([
                                    'timesheet_id' => $timesheet->id,
                                    'project_id' => $project->id,
                                    'task_id' => null,
                                    'user_id' => $user->id,
                                    'date' => $workDate,
                                    'start_time' => '09:00:00',
                                    'end_time' => Carbon::parse('09:00:00')->addHours($hoursForProject)->format('H:i:s'),
                                    'hours' => $hoursForProject,
                                    'description' => 'General work on ' . $project->title,
                                    'is_billable' => rand(0, 1),
                                    'hourly_rate' => $this->getHourlyRate($workspaceMember->role)
                                ]);
                            }
                            
                            $remainingHours -= $hoursForProject;
                        }
                    }

                    // Calculate totals
                    $timesheet->calculateTotals();
                    
                    // Create approval record for submitted/approved timesheets
                    if (in_array($timesheet->status, ['submitted', 'approved', 'rejected'])) {
                        $approvalStatus = $timesheet->status === 'submitted' ? 'pending' : ($timesheet->status === 'rejected' ? 'rejected' : 'approved');
                        
                        TimesheetApproval::create([
                            'timesheet_id' => $timesheet->id,
                            'approver_id' => $workspace->owner_id,
                            'status' => $approvalStatus,
                            'comments' => $this->getApprovalComment($timesheet->status),
                            'approved_at' => $approvalStatus !== 'pending' ? $startDate->copy()->addDays(8) : null
                        ]);
                    }
                }
            }
        }
    }
    
    private function getTimesheetStatus(int $weekOffset): string
    {
        return match($weekOffset) {
            0 => 'draft', // Current week
            1 => rand(0, 1) === 0 ? 'submitted' : 'draft',
            2, 3 => rand(0, 2) === 0 ? 'submitted' : 'draft',
            default => 'draft' // Future weeks are draft
        };
    }
    
    private function getStartTime(int $taskIndex): string
    {
        $startTimes = ['09:00:00', '10:30:00', '13:00:00', '14:30:00', '16:00:00'];
        return $startTimes[$taskIndex % count($startTimes)];
    }
    
    private function getEndTime(int $taskIndex, float $hours): string
    {
        $startTime = $this->getStartTime($taskIndex);
        return Carbon::parse($startTime)->addHours($hours)->format('H:i:s');
    }
    
    private function getWorkDescription(string $taskTitle): string
    {
        $descriptions = [
            'Development work on: ',
            'Testing and debugging: ',
            'Code review for: ',
            'Documentation update for: ',
            'Bug fixes in: ',
            'Feature implementation: ',
            'Research and analysis: '
        ];
        
        return $descriptions[array_rand($descriptions)] . $taskTitle;
    }
    
    private function isBillable($project, $task): bool
    {
        // Most development work is billable, some internal tasks are not
        $nonBillableKeywords = ['meeting', 'training', 'documentation', 'internal'];
        
        foreach ($nonBillableKeywords as $keyword) {
            if (stripos($task->title, $keyword) !== false) {
                return rand(0, 1) === 0; // 50% chance for these tasks
            }
        }
        
        return rand(0, 10) > 1; // 90% chance for regular tasks
    }
    
    private function getHourlyRate(string $role): float
    {
        return match($role) {
            'owner' => rand(100, 150),
            'admin' => rand(80, 120),
            'member' => rand(50, 80),
            'client' => rand(40, 70),
            default => rand(40, 70)
        };
    }
    
    private function getApprovalComment(string $status): ?string
    {
        if ($status === 'rejected') {
            $comments = [
                'Please provide more detailed descriptions for time entries.',
                'Some entries need clarification on billable hours.',
                'Missing time entries for project meetings.'
            ];
            return $comments[array_rand($comments)];
        }
        
        if ($status === 'approved') {
            $comments = [
                'Timesheet approved. Good detailed descriptions.',
                'All entries look accurate. Approved.',
                'Timesheet approved with no issues.',
                null, null // Sometimes no comment
            ];
            return $comments[array_rand($comments)];
        }
        
        return null;
    }
}