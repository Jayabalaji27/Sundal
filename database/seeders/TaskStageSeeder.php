<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TaskStage;
use App\Models\Workspace;

class TaskStageSeeder extends Seeder
{
    public function run(): void
    {
        $defaultStages = [
            ['name' => 'To Do', 'color' => '#ef4444', 'order' => 1, 'is_default' => true],
            ['name' => 'In Progress', 'color' => '#f59e0b', 'order' => 2],
            ['name' => 'Review', 'color' => '#3b82f6', 'order' => 3],
            ['name' => 'Blocked', 'color' => '#a855f7', 'order' => 4], // New stage added
            ['name' => 'Done', 'color' => '#10b981', 'order' => 5],
        ];

        // Create default stages for all existing workspaces that don't have stages yet
        Workspace::all()->each(function ($workspace) use ($defaultStages) {
            if ($workspace->taskStages()->count() === 0) {
                foreach ($defaultStages as $stage) {
                    TaskStage::create([
                        'workspace_id' => $workspace->id,
                        'name' => $stage['name'],
                        'color' => $stage['color'],
                        'order' => $stage['order'],
                        'is_default' => (isset($stage['is_default'])) ? true : false
                    ]);
                }
            }
        });
    }

    public static function createDefaultStagesForWorkspace($workspaceId): void
    {
        $defaultStages = [
            ['name' => 'To Do', 'color' => '#ef4444', 'order' => 1, 'is_default' => true],
            ['name' => 'In Progress', 'color' => '#f59e0b', 'order' => 2],
            ['name' => 'Review', 'color' => '#3b82f6', 'order' => 3],
            ['name' => 'Blocked', 'color' => '#a855f7', 'order' => 4],
            ['name' => 'Done', 'color' => '#10b981', 'order' => 5],
        ];

        foreach ($defaultStages as $stage) {
            TaskStage::create([
                'workspace_id' => $workspaceId,
                'name' => $stage['name'],
                'color' => $stage['color'],
                'order' => $stage['order'],
               'is_default' => (isset($stage['is_default'])) ? true : false
            ]);
        }
    }
}