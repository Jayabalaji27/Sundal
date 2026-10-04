<?php
/**
 * Regression tests for QA report 2026-10-04, P1: a yearly price left empty
 * ("20% discount") was saved as a fixed number, so it no longer followed the
 * monthly price after an edit (customers then saw "Save up to 34%").
 */

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

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
});

/** The fields the plan form submits, as the edit form pre-fills them. */
function planPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'P1 Plan',
        'price' => 49,
        'yearly_price' => null,
        'duration' => 'monthly',
        'max_users_per_workspace' => 5,
        'max_clients_per_workspace' => 5,
        'max_managers_per_workspace' => 1,
        'max_projects_per_workspace' => 5,
        'workspace_limit' => 1,
        'storage_limit' => 1,
    ], $overrides);
}

test('an auto yearly price follows the monthly price when the plan is edited', function () {
    $this->actingAs($this->admin)->post(route('plans.store'), planPayload())->assertSessionHasNoErrors();
    $plan = Plan::where('name', 'P1 Plan')->firstOrFail();
    expect((float) $plan->yearly_price)->toBe(470.4);

    // The edit form sends the stored yearly price back unchanged.
    $this->actingAs($this->admin)
        ->put(route('plans.update', $plan), planPayload(['price' => 59, 'yearly_price' => 470.4]))
        ->assertSessionHasNoErrors();

    expect((float) $plan->fresh()->yearly_price)->toBe(566.4)
        ->and($plan->fresh()->yearlySavingsPercent())->toBe(20);
});

test('lowering the monthly price does not trip the yearly price limit', function () {
    $this->actingAs($this->admin)->post(route('plans.store'), planPayload())->assertSessionHasNoErrors();
    $plan = Plan::where('name', 'P1 Plan')->firstOrFail();

    $this->actingAs($this->admin)
        ->put(route('plans.update', $plan), planPayload(['price' => 10, 'yearly_price' => 470.4]))
        ->assertSessionHasNoErrors();

    expect((float) $plan->fresh()->yearly_price)->toBe(96.0);
});

test('a yearly price the admin set stays as set when the monthly price changes', function () {
    $this->actingAs($this->admin)->post(route('plans.store'), planPayload(['yearly_price' => 500]))->assertSessionHasNoErrors();
    $plan = Plan::where('name', 'P1 Plan')->firstOrFail();

    $this->actingAs($this->admin)
        ->put(route('plans.update', $plan), planPayload(['price' => 59, 'yearly_price' => 500]))
        ->assertSessionHasNoErrors();

    expect((float) $plan->fresh()->yearly_price)->toBe(500.0);
});
