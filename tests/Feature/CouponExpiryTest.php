<?php
/**
 * QA report 2026-10-04, CO1: expired-coupon behaviour couldn't be tested from
 * the UI (past expiry dates are rejected on create/edit). Covers the checkout
 * "apply coupon" endpoint; the model/pricing side is in QaRoleReportFixesTest.
 */

use App\Models\Coupon;
use App\Models\Plan;
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

    $this->company = User::factory()->create(['type' => 'company']);
    $this->company->assignRole('company');
    $this->plan = Plan::where('is_default', true)->first();
});

function couponExpiring(string $code, string $expiryDate): Coupon
{
    return Coupon::create([
        'name' => $code, 'type' => 'percentage', 'discount_amount' => 25, 'code' => $code,
        'code_type' => 'manual', 'status' => true, 'expiry_date' => $expiryDate, 'created_by' => test()->company->id,
    ]);
}

function applyCoupon(string $code)
{
    return test()->actingAs(test()->company)->postJson(route('coupons.validate'), [
        'coupon_code' => $code,
        'plan_id' => test()->plan->id,
        'amount' => 59,
    ]);
}

test('an expired coupon is rejected at checkout', function () {
    couponExpiring('EXPIRED25', now()->subDay()->toDateString());

    applyCoupon('EXPIRED25')->assertStatus(400)->assertJson(['valid' => false, 'message' => 'Coupon has expired']);
});

test('a coupon is still valid on its expiry date', function () {
    couponExpiring('TODAY25', now()->toDateString());

    applyCoupon('TODAY25')->assertOk()->assertJson(['valid' => true]);
});
