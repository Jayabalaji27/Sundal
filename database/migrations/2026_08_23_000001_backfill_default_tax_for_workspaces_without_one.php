<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every workspace created before default-tax seeding was added to
 * WorkspaceService::createWorkspace() has zero rows in `taxes`, which left
 * the Invoice creation form's Tax dropdown empty ("No taxes configured")
 * with no obvious way to notice a Tax needed to be added first under
 * Settings > Taxes. Backfill one so existing workspaces behave the same as
 * new ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $workspaceIdsWithoutTax = DB::table('workspaces')
            ->whereNotIn('id', function ($query) {
                $query->select('workspace_id')->from('taxes');
            })
            ->pluck('id');

        foreach ($workspaceIdsWithoutTax as $workspaceId) {
            DB::table('taxes')->insert([
                'name' => 'Standard Tax',
                'rate' => 0,
                'workspace_id' => $workspaceId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('taxes')->where('name', 'Standard Tax')->where('rate', 0)->delete();
    }
};
