<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {     
        if (config('app.is_saas')) {
            // Free plan (always created)
            $freePlan = [
                'name' => 'Free',
                'price' => 0,
                'yearly_price' => 0,
                'duration' => 'monthly',
                'duration' => 'monthly',
                'description' => 'Basic plan for small businesses just getting started.',
                'max_users_per_workspace' => 2,
                'max_clients_per_workspace' => 2,
                'max_managers_per_workspace' => 1,
                'max_projects_per_workspace' => 3,
                'workspace_limit' => 1,
                'enable_chatgpt' => 'off',
                'storage_limit' => 1,
                'is_trial' => "off",
                'trial_day' => 0,
                'is_plan_enable' => 'on',
                'is_default' => true
            ];

            $plans = [$freePlan];

            // Add other plans only in demo mode
            if (config('app.is_demo')) {
                $plans[] = [
                    'name' => 'Starter',
                    'price' => 19.99,
                    'yearly_price' => 191.90,
                    'duration' => 'monthly',
                    'description' => 'Perfect for small businesses looking to grow their online presence.',
                    'max_users_per_workspace' => 10,
                    'max_clients_per_workspace' => 10,
                    'max_managers_per_workspace' => 2,
                    'max_projects_per_workspace' => 10,
                    'workspace_limit' => 3,
                    'enable_chatgpt' => 'off',
                    'storage_limit' => 5,
                    'is_trial' => 'off',
                    'trial_day' => 7,
                    'is_plan_enable' => 'on',
                    'is_default' => false
                ];

                $plans[] = [
                    'name' => 'Pro',
                    'price' => 49.99,
                    'yearly_price' => 479.90,
                    'duration' => 'monthly',
                    'description' => 'Ideal for growing businesses with multiple stores and advanced needs.',
                    'max_users_per_workspace' => 50,
                    'max_clients_per_workspace' => 30,
                    'max_managers_per_workspace' => 5,
                    'max_projects_per_workspace' => 25,
                    'workspace_limit' => 10,
                    'enable_chatgpt' => 'on',
                    'storage_limit' => 50,
                    'is_trial' => 'off',
                    'trial_day' => 14,
                    'is_plan_enable' => 'on',
                    'is_default' => false
                ];
            }

            foreach ($plans as $planData) {
                // Check if plan with this name already exists
                $existingPlan = Plan::where('name', $planData['name'])->first();

                if (!$existingPlan) {
                    Plan::create($planData);
                }
            }
        }
    }
}
