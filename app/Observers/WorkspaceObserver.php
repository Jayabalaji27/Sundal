<?php

namespace App\Observers;

use App\Models\Workspace;
use Database\Seeders\TaskStageSeeder;
use Database\Seeders\ContractTypeSeeder;

class WorkspaceObserver
{
    public function created(Workspace $workspace): void
    {
        // Auto-create default task stages for new workspace (bugs are tasks of
        // type bug and move through the same stages, so there are no bug statuses)
        TaskStageSeeder::createDefaultStagesForWorkspace($workspace->id);

        // Auto-create default contract types for new workspace - only the
        // Owner can create Contract Types, so without this the Contract Type
        // dropdown stays empty (blocking contract creation for everyone)
        // until the Owner manually visits Contract Type settings first.
        ContractTypeSeeder::createDefaultTypesForWorkspace($workspace->id, $workspace->owner_id);
    }
}