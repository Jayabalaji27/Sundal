<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BugStatus;
use App\Models\Workspace;

class BugStatusSeeder extends Seeder
{
    public function run(): void
    {
        $defaultStatuses = [
            ['name' => 'New', 'color' => '#ef4444', 'order' => 1, 'is_default' => true],
            ['name' => 'In Progress', 'color' => '#f59e0b', 'order' => 2],
            ['name' => 'Testing', 'color' => '#3b82f6', 'order' => 3],
            ['name' => 'Resolved', 'color' => '#10b981', 'order' => 4],
            ['name' => 'Closed', 'color' => '#6b7280', 'order' => 5],
        ];

        // Create default statuses for all existing workspaces that don't have bug statuses yet
        Workspace::all()->each(function ($workspace) use ($defaultStatuses) {
            if ($workspace->bugStatuses()->count() === 0) {
                foreach ($defaultStatuses as $status) {
                    BugStatus::create([
                        'workspace_id' => $workspace->id,
                        'name' => $status['name'],
                        'color' => $status['color'],
                        'order' => $status['order'],
                        'is_default' => isset($status['is_default']) ? $status['is_default'] : false
                    ]);
                }
            }
        });
    }

    public static function createDefaultStatusesForWorkspace($workspaceId): void
    {
        $defaultStatuses = [
            ['name' => 'New', 'color' => '#ef4444', 'order' => 1,'is_default' => true],
            ['name' => 'In Progress', 'color' => '#f59e0b', 'order' => 2],
            ['name' => 'Testing', 'color' => '#3b82f6', 'order' => 3],
            ['name' => 'Resolved', 'color' => '#10b981', 'order' => 4],
            ['name' => 'Closed', 'color' => '#6b7280', 'order' => 5],
        ];

        foreach ($defaultStatuses as $status) {
            BugStatus::create([
                'workspace_id' => $workspaceId,
                'name' => $status['name'],
                'color' => $status['color'],
                'order' => $status['order'],
                'is_default' => isset($status['is_default']) ? $status['is_default'] : false
            ]);
        }
    }
}