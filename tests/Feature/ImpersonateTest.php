<?php
/**
 * Regression tests for QA report 2026-10-04, B1: "Login as Company"
 * (impersonation) returned 403 for every company, because the route sat
 * behind block.superadmin.workspace. The controller also had no
 * authorization of its own, so these cover who may impersonate whom.
 */

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    // block.superadmin.workspace stays on: it's the middleware under test.
    $this->withoutMiddleware([
        \App\Http\Middleware\CheckInstallation::class,
        \App\Http\Middleware\ShareGlobalSettings::class,
        \App\Http\Middleware\DemoModeMiddleware::class,
        \App\Http\Middleware\CheckPlanAccess::class,
        \App\Http\Middleware\CheckPlanLimits::class,
        \App\Http\Middleware\CheckModuleAccess::class,
        \App\Http\Middleware\EnsureEmailIsVerified::class,
    ]);
});

function impersonateSuperAdmin(): User
{
    $user = User::factory()->create(['type' => 'superadmin']);
    $user->assignRole('superadmin');

    return $user;
}

function impersonateCompany(): User
{
    $user = User::factory()->create(['type' => 'company']);
    $user->assignRole('company');

    return $user;
}

test('super admin can impersonate a company and return to admin', function () {
    $admin = impersonateSuperAdmin();
    $company = impersonateCompany();

    $this->actingAs($admin)
        ->get(route('impersonate.start', $company->id))
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($company);
    expect(session('impersonated_by'))->toBe($admin->id);

    $this->post(route('impersonate.leave'))->assertRedirect('/companies');

    $this->assertAuthenticatedAs($admin);
    expect(session()->has('impersonated_by'))->toBeFalse();
});

test('impersonating a suspended company explains why instead of a 403', function () {
    $admin = impersonateSuperAdmin();
    $company = impersonateCompany();
    $company->update(['status' => 'inactive']);

    $this->actingAs($admin)
        ->get(route('impersonate.start', $company->id))
        ->assertRedirect(route('companies.index'))
        ->assertSessionHas('error');

    $this->assertAuthenticatedAs($admin);
});

test('super admin cannot impersonate a non-company account', function () {
    $admin = impersonateSuperAdmin();
    $otherAdmin = impersonateSuperAdmin();

    $this->actingAs($admin)
        ->get(route('impersonate.start', $otherAdmin->id))
        ->assertForbidden();

    $this->assertAuthenticatedAs($admin);
});

test('a company cannot impersonate anyone, including super admin', function () {
    $admin = impersonateSuperAdmin();
    $company = impersonateCompany();
    $otherCompany = impersonateCompany();

    $this->actingAs($company)->get(route('impersonate.start', $admin->id))->assertForbidden();
    $this->actingAs($company)->get(route('impersonate.start', $otherCompany->id))->assertForbidden();

    $this->assertAuthenticatedAs($company);
    expect(session()->has('impersonated_by'))->toBeFalse();
});

test('super admin is still blocked from workspace-scoped routes', function () {
    $admin = impersonateSuperAdmin();

    $this->actingAs($admin)->get(route('zapier.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('api-keys.index'))->assertForbidden();
});
