<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true) . ' Plan',
            'plan_type' => 'base',
            // Default true: most tests using this factory predate the add-on
            // gate and expect chat/kb/agent/meetings access like any ordinary
            // plan. Tests specifically covering add-on gating (CheckModuleAccessTest,
            // PlanAssignmentTest) override this explicitly.
            'legacy_addon_access' => true,
            'price' => fake()->randomFloat(2, 0, 200),
            'duration' => 'monthly',
            'description' => fake()->sentence(),
            'max_users_per_workspace' => 10,
            'max_clients_per_workspace' => 5,
            'max_managers_per_workspace' => 2,
            'max_projects_per_workspace' => 10,
            'workspace_limit' => 1,
            'storage_limit' => 1024,
            'is_trial' => 0,
            'trial_day' => 0,
            'is_plan_enable' => 'on',
            'is_default' => false,
        ];
    }
}
