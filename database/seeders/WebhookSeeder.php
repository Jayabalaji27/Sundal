<?php

namespace Database\Seeders;

use App\Models\Webhook;
use App\Models\User;
use Illuminate\Database\Seeder;

class WebhookSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('type', 'company')->get();

        if ($users->isEmpty()) {
            $this->command->warn('No company users found. Please seed users first.');
            return;
        }

        $webhooks = [
            [
                'user_id' => $users->first()->id,
                'workspace_id' => $users->first()->current_workspace_id,
                'module' => 'Workspace Invitation',
                'method' => 'POST',
                'url' => 'https://example.com/webhooks/workspace-invitation'
            ],
            [
                'user_id' => $users->first()->id,
                'workspace_id' => $users->first()->current_workspace_id,
                'module' => 'New Project',
                'method' => 'POST',
                'url' => 'https://example.com/webhooks/new-project'
            ],
            [
                'user_id' => $users->first()->id,
                'workspace_id' => $users->first()->current_workspace_id,
                'module' => 'New Task',
                'method' => 'POST',
                'url' => 'https://example.com/webhooks/new-task'
            ],
            [
                'user_id' => $users->skip(1)->first()->id,
                'workspace_id' => $users->skip(1)->first()->current_workspace_id,
                'module' => 'New Budget',
                'method' => 'POST',
                'url' => 'https://company2.com/webhooks/new-budget'
            ],
            [
                'user_id' => $users->skip(1)->first()->id,
                'workspace_id' => $users->skip(1)->first()->current_workspace_id,
                'module' => 'New Invoice',
                'method' => 'POST',
                'url' => 'https://company2.com/webhooks/new-invoice'
            ]

        ];

        foreach ($webhooks as $webhookData) {
            Webhook::updateOrCreate($webhookData);
        }

        $this->command->info('Webhooks seeded successfully!');
    }
}