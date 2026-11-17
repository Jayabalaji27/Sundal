<?php

/**
 * Team page → "Set password": the workspace owner gives a teammate a temporary
 * password (for when the reset email doesn't arrive), the teammate must choose
 * their own at next sign-in, and no owner can take over another company's user.
 */

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

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
    Cache::flush();
});

function spwOwner(): array
{
    $plan = Plan::where('is_default', true)->first();
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1]);
    $workspace = Workspace::create(['name' => 'SP WS', 'slug' => 'sp-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    return [$owner, $workspace];
}

function spwMember(Workspace $workspace, string $role): User
{
    $user = User::factory()->create(['type' => 'company', 'current_workspace_id' => $workspace->id, 'password' => 'old-password-123']);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
    $user->assignRole($role);

    return $user;
}

function spwPayload(string $password = 'Temp-Pass-2026'): array
{
    return ['password' => $password, 'password_confirmation' => $password];
}

test('the owner sets a temporary password, the member is flagged and signed out everywhere', function () {
    [$owner, $workspace] = spwOwner();
    $member = spwMember($workspace, 'member');
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert(['id' => 'member-session', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($owner)
        ->put(route('team.set-password', [$workspace->id, $member->id]), spwPayload())
        ->assertSessionHas('success');

    $member->refresh();
    expect(Hash::check('Temp-Pass-2026', $member->password))->toBeTrue()
        ->and($member->must_change_password)->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $member->id)->exists())->toBeFalse();
});

test('managers, members and clients cannot set passwords', function (string $role) {
    [, $workspace] = spwOwner();
    $actor = spwMember($workspace, $role);
    $target = spwMember($workspace, 'member');

    $this->actingAs($actor)
        ->put(route('team.set-password', [$workspace->id, $target->id]), spwPayload())
        ->assertForbidden();

    expect(Hash::check('old-password-123', $target->fresh()->password))->toBeTrue();
})->with(['manager', 'member', 'client']);

test('the owner cannot set a password for someone outside the workspace', function () {
    [$owner, $workspace] = spwOwner();
    [, $otherWorkspace] = spwOwner();
    $stranger = spwMember($otherWorkspace, 'member');

    $this->actingAs($owner)
        ->put(route('team.set-password', [$workspace->id, $stranger->id]), spwPayload())
        ->assertNotFound();
});

test('a teammate who also works for another company is refused', function () {
    [$owner, $workspace] = spwOwner();
    [, $otherWorkspace] = spwOwner();
    $shared = spwMember($workspace, 'member');
    WorkspaceMember::create(['workspace_id' => $otherWorkspace->id, 'user_id' => $shared->id, 'role' => 'member', 'status' => 'active']);

    $this->actingAs($owner)
        ->put(route('team.set-password', [$workspace->id, $shared->id]), spwPayload())
        ->assertSessionHas('error');

    expect(Hash::check('old-password-123', $shared->fresh()->password))->toBeTrue()
        ->and($shared->fresh()->must_change_password)->toBeFalse();
});

test('the password must be confirmed and at least 8 characters', function () {
    [$owner, $workspace] = spwOwner();
    $member = spwMember($workspace, 'member');

    $this->actingAs($owner)
        ->put(route('team.set-password', [$workspace->id, $member->id]), ['password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors('password');
    $this->actingAs($owner)
        ->put(route('team.set-password', [$workspace->id, $member->id]), ['password' => 'Temp-Pass-2026', 'password_confirmation' => 'different-1'])
        ->assertSessionHasErrors('password');
});

test('a flagged user is sent to choose a new password before anything else', function () {
    [, $workspace] = spwOwner();
    $member = spwMember($workspace, 'member');
    $member->forceFill(['must_change_password' => true])->save();

    $this->actingAs($member)->get(route('dashboard'))->assertRedirect(route('password.change'));
    $this->actingAs($member)->get(route('password.change'))->assertOk();
});

test('choosing a new password clears the flag; reusing the temporary one is refused', function () {
    [, $workspace] = spwOwner();
    $member = spwMember($workspace, 'member');
    $member->forceFill(['password' => 'Temp-Pass-2026', 'must_change_password' => true])->save();

    $this->actingAs($member)
        ->put(route('password.change.update'), spwPayload('Temp-Pass-2026'))
        ->assertSessionHasErrors('password');
    expect($member->fresh()->must_change_password)->toBeTrue();

    $this->actingAs($member)
        ->put(route('password.change.update'), spwPayload('My-Own-Pass-99'))
        ->assertRedirect(route('dashboard'));

    $member->refresh();
    expect($member->must_change_password)->toBeFalse()
        ->and(Hash::check('My-Own-Pass-99', $member->password))->toBeTrue();
    $this->actingAs($member)->get(route('password.change'))->assertRedirect(route('dashboard'));
});
