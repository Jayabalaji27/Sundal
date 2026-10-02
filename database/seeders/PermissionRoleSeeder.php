<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Lightweight seeder used only by tests — permissions + roles, nothing else. */
class PermissionRoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }
}
