<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ContractType;
use App\Models\Workspace;

class ContractTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Backfill default contract types for any existing workspace that
        // doesn't have any yet - mirrors TaskStageSeeder::run().
        Workspace::all()->each(function ($workspace) {
            if ($workspace->contractTypes()->count() === 0) {
                self::createDefaultTypesForWorkspace($workspace->id, $workspace->owner_id);
            }
        });
    }

    /**
     * New workspaces start with zero Contract Types, and only the Owner can
     * create them (contract_type_create is not granted to Manager/Member per
     * role-capabilities-plan.md - Manager gets contract create/edit but not
     * type management). Without this, the Contract Type dropdown is
     * legitimately empty for everyone until the Owner manually visits
     * Contract Type settings first. Seed a starter set so contract creation
     * works immediately; the Owner can still rename/add/remove afterward.
     */
    public static function createDefaultTypesForWorkspace($workspaceId, $createdBy): void
    {
        $defaultTypes = [
            ['name' => 'Service Agreement', 'color' => '#007bff', 'sort_order' => 1],
            ['name' => 'NDA', 'color' => '#6f42c1', 'sort_order' => 2],
            ['name' => 'Employment', 'color' => '#10b981', 'sort_order' => 3],
            ['name' => 'Retainer', 'color' => '#f59e0b', 'sort_order' => 4],
        ];

        foreach ($defaultTypes as $type) {
            ContractType::create([
                'workspace_id' => $workspaceId,
                'created_by' => $createdBy,
                'name' => $type['name'],
                'color' => $type['color'],
                'sort_order' => $type['sort_order'],
                'is_active' => true,
            ]);
        }
    }
}
