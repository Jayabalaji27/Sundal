<?php
/**
 * Regression test for QA report 2026-10-04, R3: Super Admin's "Upgrade Plan"
 * on Companies left no plan-order record, and ignored the dialog's
 * Monthly/Yearly choice.
 */

use App\Models\Plan;
use App\Models\PlanOrder;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(PlanSeeder::class);
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

test('an admin plan change is recorded as a $0 approved order for the chosen cycle', function () {
    $admin = User::factory()->create(['type' => 'superadmin']);
    $admin->assignRole('superadmin');
    $company = User::factory()->create(['type' => 'company']);
    $company->assignRole('company');
    $plan = Plan::where('is_default', true)->first();

    $this->actingAs($admin)
        ->from(route('companies.index'))
        ->put(route('companies.upgrade-plan', $company), ['plan_id' => $plan->id, 'duration' => 'yearly'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $order = PlanOrder::where('user_id', $company->id)->sole();
    expect($order->plan_id)->toBe($plan->id)
        ->and($order->status)->toBe('approved')
        ->and($order->billing_cycle)->toBe('yearly')
        ->and((float) $order->final_price)->toBe(0.0)
        ->and($order->payment_method)->toBe('admin')
        ->and($order->processed_by)->toBe($admin->id)
        ->and($company->fresh()->plan_expire_date->isAfter(now()->addMonths(11)))->toBeTrue();
});
