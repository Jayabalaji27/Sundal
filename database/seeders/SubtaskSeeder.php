<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;
use App\Models\TaskChecklist;

class SubtaskSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = Task::with(['project.members.user'])->get();

        foreach ($tasks as $task) {
            $projectMembers = $task->project->members->pluck('user')->filter();
            
            if ($projectMembers->isEmpty()) continue;

            // Create 2-4 additional subtasks per task (in addition to existing checklists)
            $subtaskCount = rand(2, 4);
            $existingChecklistCount = TaskChecklist::where('task_id', $task->id)->count();
            
            for ($i = 1; $i <= $subtaskCount; $i++) {
                TaskChecklist::create([
                    'task_id' => $task->id,
                    'title' => $this->getSubtaskTitle($task->title, $i),
                    'is_completed' => rand(0, 1),
                    'assigned_to' => rand(0, 1) ? $projectMembers->random()->id : null,
                    'due_date' => rand(0, 1) ? now()->addDays(rand(1, 14)) : null,
                    'order' => $existingChecklistCount + $i,
                    'created_by' => $projectMembers->random()->id,
                ]);
            }
        }
    }

    private function getSubtaskTitle(string $taskTitle, int $index): string
    {
        $subtaskTemplates = [
            'Research and analysis for: ',
            'Create wireframes for: ',
            'Implement core logic for: ',
            'Write unit tests for: ',
            'Code review for: ',
            'Update documentation for: ',
            'Deploy changes for: ',
            'Validate requirements for: '
        ];

        $template = $subtaskTemplates[($index - 1) % count($subtaskTemplates)];
        return $template . strtolower($taskTitle);
    }
}