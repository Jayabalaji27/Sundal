<?php
/**
 * Regression tests for QA report 2026-10-04, C2: the Companies date filter
 * compared UTC dates while the list shows dates in the viewer's timezone, so
 * a company created 2026-10-04 02:13 IST (2026-10-03 20:43 UTC) showed as
 * "2026-10-04" but wasn't found by a 2026-10-04 filter.
 */

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->withoutMiddleware([
        \App\Http\Middleware\CheckInstallation::class,
        \App\Http\Middleware\ShareGlobalSettings::class,
        \App\Http\Middleware\DemoModeMiddleware::class,
        \App\Http\Middleware\CheckPlanAccess::class,
        \App\Http\Middleware\CheckPlanLimits::class,
        \App\Http\Middleware\CheckModuleAccess::class,
        \App\Http\Middleware\EnsureEmailIsVerified::class,
    ]);

    $this->admin = User::factory()->create(['type' => 'superadmin']);
    $this->admin->assignRole('superadmin');

    $company = User::factory()->create(['type' => 'company', 'name' => 'Late Night Co']);
    $company->assignRole('company');
    $company->forceFill(['created_at' => '2026-10-03 20:43:00'])->save(); // UTC
});

function companiesFound(array $query): array
{
    $names = [];
    test()->actingAs(test()->admin)
        ->get(route('companies.index', $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$names) {
            $names = collect($page->toArray()['props']['companies']['data'])->pluck('name')->all();
        });

    return $names;
}

test('the date filter uses the viewer timezone', function () {
    expect(companiesFound(['start_date' => '2026-10-04', 'end_date' => '2026-10-04', 'tz' => 'Asia/Kolkata']))
        ->toContain('Late Night Co')
        ->and(companiesFound(['start_date' => '2026-10-03', 'end_date' => '2026-10-03', 'tz' => 'Asia/Kolkata']))
        ->not->toContain('Late Night Co');
});

test('the end date includes the whole day, and without a timezone UTC applies', function () {
    expect(companiesFound(['start_date' => '2026-10-03', 'end_date' => '2026-10-03']))
        ->toContain('Late Night Co')
        ->and(companiesFound(['start_date' => '2026-10-04', 'end_date' => '2026-10-04', 'tz' => 'Not/AZone']))
        ->not->toContain('Late Night Co');
});
