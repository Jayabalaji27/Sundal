<?php
/**
 * QA report 2026-10-04, B4: Login History should list login and logout events
 * with a role label. Logins were recorded; logouts were not.
 */

use App\Models\LoginHistory;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

test('logging in and out are both recorded', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/logout');

    $events = LoginHistory::where('user_id', $user->id)->orderBy('id')->get()
        ->map(fn ($row) => $row->details['event'] ?? null)->all();

    expect($events)->toBe(['login', 'logout']);
});

test('super admin can open the login history page', function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->withoutMiddleware([
        \App\Http\Middleware\CheckInstallation::class,
        \App\Http\Middleware\CheckPlanAccess::class,
        \App\Http\Middleware\CheckPlanLimits::class,
        \App\Http\Middleware\EnsureEmailIsVerified::class,
    ]);
    $admin = User::factory()->create(['type' => 'superadmin']);
    $admin->assignRole('superadmin');

    $this->actingAs($admin)->get(route('users.all-logs'))->assertOk();
});
