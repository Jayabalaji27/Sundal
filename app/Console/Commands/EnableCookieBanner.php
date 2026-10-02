<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class EnableCookieBanner extends Command
{
    protected $signature = 'cookie:enable';
    protected $description = 'Enable cookie banner for testing';

    public function handle()
    {
        // Get the first superadmin or company user
        $user = User::whereIn('type', ['superadmin', 'company'])->first();
        
        if (!$user) {
            $this->error('No superadmin or company user found');
            return 1;
        }

        $workspaceId = null;
        if ($user->type === 'company') {
            $workspaceId = $user->current_workspace_id;
        }

        // Enable cookie logging
        updateSetting('enableLogging', '1', $user->id, $workspaceId);
        
        $this->info('Cookie banner enabled successfully!');
        $this->info("User: {$user->name} ({$user->type})");
        $this->info("Workspace ID: " . ($workspaceId ?? 'null'));
        
        return 0;
    }
}