<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectClient;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class ProjectClientSeeder extends Seeder
{
    public function run(): void
    {
        $workspaces = Workspace::all();
        
        if ($workspaces->isEmpty()) {
            $this->command->info('No workspaces found. Please run WorkspaceSeeder first.');
            return;
        }

        foreach ($workspaces as $workspace) {
            $projects = $workspace->projects()->get();
            
            if ($projects->isEmpty()) {
                continue;
            }

            // Get clients for this workspace
            $clients = $workspace->users()
                ->whereHas('roles', function($q) {
                    $q->where('name', 'client');
                })
                ->get();

            // If no clients with role, get any users from the workspace
            if ($clients->isEmpty()) {
                $clients = $workspace->users()->get();
            }

            if ($clients->isEmpty()) {
                continue;
            }

            foreach ($projects as $project) {
                // Assign 1-2 clients to each project
                $clientCount = random_int(1, min(2, $clients->count()));
                $selectedClients = $clients->random($clientCount);

                foreach ($selectedClients as $client) {
                    // Check if relationship already exists
                    $exists = ProjectClient::where('project_id', $project->id)
                        ->where('user_id', $client->id)
                        ->exists();

                    if (!$exists) {
                        ProjectClient::create([
                            'project_id' => $project->id,
                            'user_id' => $client->id,
                            'assigned_at' => now(),
                            'assigned_by' => $project->created_by,
                        ]);
                    }
                }
            }
        }

        $this->command->info('Project client assignments completed successfully.');
    }
}