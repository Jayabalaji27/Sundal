<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\ProjectNote;
use App\Models\ProjectClient;
use App\Models\Workspace;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $workspaces = Workspace::with(['activeMembers.user', 'owner'])->get();

        foreach ($workspaces as $workspace) {
            $workspaceMembers = $workspace->activeMembers;
            if ($workspaceMembers->isEmpty()) continue;

            $owner = $workspace->owner;
            $clients = $workspaceMembers->where('role', 'client')->pluck('user')->filter();
            $members = $workspaceMembers->where('role', 'member')->pluck('user')->filter();
            $managers = $workspaceMembers->where('role', 'manager')->pluck('user')->filter();
            $allMembers = $workspaceMembers->whereIn('role', ['owner', 'manager', 'member'])->pluck('user')->filter();
            
            // More flexible conditions - create projects if we have at least some members
            if ($allMembers->count() < 3) continue;

            $projectCount = $workspace->name === 'WorkDo\'s Workspace' ? 20 : 4;
            
            for ($i = 1; $i <= $projectCount; $i++) {
                // 10% of projects should be completed (2-3 out of 24 total)
                $isCompleted = $i <= ceil($projectCount * 0.1);
                
                $project = Project::create([
                    'workspace_id' => $workspace->id,
                    'title' => $this->getProjectTitle($i),
                    'description' => $this->getProjectDescription($i),
                    'status' => $isCompleted ? 'completed' : $this->getRandomStatus(),
                    'priority' => $this->getRandomPriority(),
                    'start_date' => now()->subDays(rand(1, 60)),
                    'deadline' => now()->addDays(rand(30, 120)),
                    'estimated_hours' => rand(80, 400),
                    'progress' => $isCompleted ? 100 : rand(10, 95),
                    'is_public' => rand(0, 1),
                    'created_by' => $owner->id,
                ]);
                
                // Assign clients if available
                if ($clients->count() > 0) {
                    $clientCount = rand(1, min(3, $clients->count()));
                    foreach ($clients->random($clientCount) as $client) {
                        ProjectClient::create([
                            'project_id' => $project->id,
                            'user_id' => $client->id,
                            'assigned_at' => now(),
                            'assigned_by' => $owner->id,
                        ]);
                    }
                }

                // Assign managers if available
                if ($managers->count() > 0) {
                    $managerCount = rand(1, min(2, $managers->count()));
                    foreach ($managers->random($managerCount) as $manager) {
                        ProjectMember::create([
                            'project_id' => $project->id,
                            'user_id' => $manager->id,
                            'role' => 'manager',
                            'assigned_at' => now(),
                            'assigned_by' => $owner->id,
                        ]);
                    }
                }

                // Assign members if available
                if ($members->count() > 0) {
                    $memberCount = rand(1, min(8, $members->count()));
                    foreach ($members->random($memberCount) as $member) {
                        ProjectMember::create([
                            'project_id' => $project->id,
                            'user_id' => $member->id,
                            'role' => 'member',
                            'assigned_at' => now(),
                            'assigned_by' => $owner->id,
                        ]);
                    }
                }

                // Create milestones
                foreach ($this->getMilestoneNames() as $index => $name) {
                    ProjectMilestone::create([
                        'project_id' => $project->id,
                        'title' => $name,
                        'description' => $this->getMilestoneDescription($name),
                        'due_date' => now()->addDays(rand(15 + ($index * 20), 30 + ($index * 20))),
                        'status' => $isCompleted ? 'completed' : $this->getRandomMilestoneStatus($index + 1),
                        'progress' => $isCompleted ? 100 : $this->getMilestoneProgress($index + 1),
                        'order' => $index + 1,
                        'created_by' => $owner->id,
                    ]);
                }

                // Create notes
                for ($n = 1; $n <= rand(3, 5); $n++) {
                    ProjectNote::create([
                        'project_id' => $project->id,
                        'title' => $this->getNoteTitle($n),
                        'content' => $this->getNoteContent($n),
                        'is_pinned' => $n === 1,
                        'created_by' => $allMembers->random()->id,
                    ]);
                }

                $project->logActivity('created', "Project '{$project->title}' was created", [], $owner->id);
            }
        }
    }

    private function getProjectTitle(int $index): string
    {
        $titles = ['E-Commerce Platform', 'Mobile Banking App', 'Customer Portal', 'API Gateway', 'Security Audit', 'Performance Suite', 'Admin Dashboard', 'Payment Integration', 'Analytics System', 'Cloud Migration', 'Legacy Modernization', 'AI Chatbot', 'Inventory System', 'CRM Enhancement', 'Data Warehouse'];
        return $titles[($index - 1) % count($titles)];
    }

    private function getProjectDescription(int $index): string
    {
        return 'Project description for ' . $this->getProjectTitle($index);
    }

    private function getMilestoneNames(): array
    {
        return ['Planning', 'Development', 'Testing', 'Deployment'];
    }

    private function getMilestoneDescription(string $name): string
    {
        return $name . ' phase';
    }

    private function getMilestoneProgress(int $order): int
    {
        return match($order) {
            1 => rand(80, 100),
            2 => rand(60, 90), 
            3 => rand(30, 70),
            4 => rand(0, 40),
        };
    }

    private function getNoteTitle(int $index): string
    {
        return 'Note ' . $index;
    }

    private function getNoteContent(int $index): string
    {
        return 'Content for note ' . $index;
    }

    private function getRandomStatus(): string
    {
        return ['planning', 'active', 'on_hold', 'completed'][array_rand(['planning', 'active', 'on_hold', 'completed'])];
    }

    private function getRandomPriority(): string
    {
        return ['low', 'medium', 'high', 'urgent'][array_rand(['low', 'medium', 'high', 'urgent'])];
    }

    private function getRandomMilestoneStatus(int $order): string
    {
        return match($order) {
            1 => 'completed',
            2 => 'in_progress',
            3 => 'in_progress', 
            4 => 'pending',
        };
    }
}