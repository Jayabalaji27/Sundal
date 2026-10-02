<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The Spatie 'owner' role was never assigned to any user (dead code left
        // over from RoleSeeder.php); real workspace ownership is tracked via the
        // separate workspace_members.role column. Deleting the role row here also
        // cascades to role_has_permissions/model_has_roles via FK constraints.
        Role::where('name', 'owner')->where('guard_name', 'web')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->updateOrInsert(
            ['name' => 'owner', 'guard_name' => 'web'],
            [
                'label' => 'Owner',
                'description' => 'Owner with read-only access',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
};
