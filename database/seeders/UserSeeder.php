<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Plan;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (isSaasMode()) {
            // Create Super Admin User
            $superAdmin = User::firstOrCreate(
                ['email' => 'superadmin@example.com'],
                [
                    'name' => 'Super Admin',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'type' => 'superadmin',
                    'lang' => 'en'
                ]
            );
            $superAdmin->assignRole('superadmin');

            if (!Setting::where('user_id', $superAdmin->id)->exists()) {
                createDefaultSettings($superAdmin->id);
            }

            $defaultPlan = null;
            if (class_exists('App\Models\Plan')) {
                $defaultPlan = Plan::where('is_default', true)->first();
            }

            // Create the main company user
            $company = User::firstOrCreate(
                ['email' => 'company@example.com'],
                [
                    'name' => 'WorkDo',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'type' => 'company',
                    'lang' => 'en',
                    'plan_id' => $defaultPlan ? $defaultPlan->id : null,
                    'referral_code' => rand(100000, 999999),
                ]
            );
            $company->assignRole('company');

            if (!Setting::where('user_id', $company->id)->exists()) {
                $workspace = createDefaultWorkspace($company);
                createDefaultSettings($company->id, $workspace->id);
            }

            if ($defaultPlan) {
                User::where('type', 'company')
                    ->whereNull('plan_id')
                    ->update(['plan_id' => $defaultPlan->id]);
            }
        } else {
            // Non-SaaS mode: Create company owner
            $company = User::firstOrCreate(
                ['email' => 'company@example.com'],
                [
                    'name' => 'WorkDo',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'type' => 'company',
                    'lang' => 'en'
                ]
            );
            $company->assignRole('company');

            if (!Setting::where('user_id', $company->id)->exists()) {
                $workspace = createDefaultWorkspace($company);
                createDefaultSettings($company->id, $workspace->id);
            }
        }
    }
}
