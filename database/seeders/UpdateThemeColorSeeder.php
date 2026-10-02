<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class UpdateThemeColorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Update all existing theme color settings to green if they are currently blue
        Setting::where('key', 'themeColor')
            ->where('value', 'blue')
            ->update(['value' => 'green']);
            
        // Update all existing custom color settings to green hex if they are currently blue hex
        Setting::where('key', 'customColor')
            ->where('value', '#3b82f6')
            ->update(['value' => '#10b981']);
            
        // For any users who don't have theme color settings yet, create them
        $usersWithoutThemeColor = \DB::table('users')
            ->leftJoin('settings', function($join) {
                $join->on('users.id', '=', 'settings.user_id')
                     ->where('settings.key', '=', 'themeColor');
            })
            ->whereNull('settings.id')
            ->select('users.id', 'users.type', 'users.current_workspace_id')
            ->get();
            
        foreach ($usersWithoutThemeColor as $user) {
            $workspaceId = $user->type === 'superadmin' ? null : $user->current_workspace_id;
            
            Setting::create([
                'user_id' => $user->id,
                'workspace_id' => $workspaceId,
                'key' => 'themeColor',
                'value' => 'green'
            ]);
            
            Setting::create([
                'user_id' => $user->id,
                'workspace_id' => $workspaceId,
                'key' => 'customColor',
                'value' => '#10b981'
            ]);
        }
        
        $this->command->info('Theme color updated to green for all users.');
    }
}