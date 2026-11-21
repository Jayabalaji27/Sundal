<?php

use App\Models\EmailTemplate;
use App\Models\Plan;
use App\Models\UserEmailTemplate;
use App\Services\EmailTemplateService;
use Inertia\Testing\AssertableInertia as Assert;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

/** Owner on a plan roomy enough that limits don't interfere, plus a manager in the same workspace. */
function managerInviteSetup(): array
{
    $plan = Plan::create([
        'name' => 'Roomy ' . uniqid(), 'price' => 0, 'duration' => 'monthly',
        'max_users_per_workspace' => 50, 'max_clients_per_workspace' => 50,
        'max_managers_per_workspace' => 50, 'max_projects_per_workspace' => 50,
    ]);
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan->id, 'plan_is_active' => 1, 'plan_expire_date' => now()->addYear()]);
    $workspace = Workspace::create(['name' => 'Invite WS', 'slug' => 'inv-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    // Invited accounts are type 'company' in SaaS mode, so the manager is too.
    $manager = User::factory()->create(['type' => 'company', 'current_workspace_id' => $workspace->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $manager->id, 'role' => 'manager', 'status' => 'active']);
    $manager->assignRole('manager');

    return [$workspace, $manager];
}

test('a manager can invite someone as a member', function () {
    [$workspace, $manager] = managerInviteSetup();

    $this->actingAs($manager)
        ->post(route('workspace.invitations.store', $workspace), ['email' => 'new.member@example.com', 'role' => 'member'])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('error');

    expect(WorkspaceInvitation::where('workspace_id', $workspace->id)->where('email', 'new.member@example.com')->value('role'))
        ->toBe('member');
});

/** Mail server set up by the Super Admin only (Settings → Email Settings); workspaces have none. */
function configurePlatformMail(): void
{
    $superadmin = User::factory()->create(['type' => 'superadmin']);
    // Save as the Super Admin would: updateSetting() files settings under the logged-in
    // company user's workspace, so a test that already logged someone in would misfile them.
    auth()->guard()->forgetUser();
    foreach ([
        'email_driver' => 'smtp', 'email_host' => 'smtp.gmail.com', 'email_port' => '587',
        'email_username' => 'platform@gmail.com', 'email_password' => 'app-password',
        'email_encryption' => 'tls', 'email_from_address' => 'platform@gmail.com', 'email_from_name' => 'Sundal',
    ] as $key => $value) {
        updateSetting($key, $value, $superadmin->id, null);
    }
}

/** The owner's switch in Settings → Email Notification Settings → "Workspace Invitation". */
function setInvitationEmail(Workspace $workspace, bool $on): void
{
    $template = EmailTemplate::firstOrCreate(['name' => 'Workspace Invitation'], ['from' => 'x', 'user_id' => 1]);
    UserEmailTemplate::updateOrCreate(
        ['user_id' => $workspace->owner_id, 'workspace_id' => $workspace->id, 'template_id' => $template->id],
        ['is_active' => $on],
    );
}

test('invites use the platform mail settings and the owner\'s switch, whoever sends them', function (string $as) {
    configurePlatformMail();
    [$workspace, $manager] = managerInviteSetup();
    setInvitationEmail($workspace, true);
    $inviter = $as === 'owner' ? $workspace->owner : $manager;

    // The manager's own (never-set) choice used to decide, so their invites were never emailed.
    $this->mock(EmailTemplateService::class)->shouldReceive('sendTemplateEmailWithLanguage')->once();

    $this->actingAs($inviter)
        ->post(route('workspace.invitations.store', $workspace), ['email' => "invitee.$as@example.com", 'role' => 'member'])
        ->assertSessionMissing('warning')
        ->assertSessionHas('success');
})->with(['owner', 'manager']);

test('invites warn instead of claiming "sent" when the invitation email is turned off', function () {
    configurePlatformMail();
    [$workspace, $manager] = managerInviteSetup(); // the switch starts off for a new company
    $this->mock(EmailTemplateService::class)->shouldNotReceive('sendTemplateEmailWithLanguage');

    $this->actingAs($manager)
        ->post(route('workspace.invitations.store', $workspace), ['email' => 'off@example.com', 'role' => 'member'])
        ->assertSessionMissing('success')
        ->assertSessionHas('warning', fn ($w) => str_contains($w, 'turned off'))
        ->assertSessionHas('invitation_link');

    // Resend says the same instead of "Invitation resent successfully".
    $invitation = WorkspaceInvitation::where('email', 'off@example.com')->firstOrFail();
    $this->actingAs($manager)
        ->post(route('invitations.resend', $invitation))
        ->assertSessionMissing('success')
        ->assertSessionHas('warning', fn ($w) => str_contains($w, 'turned off'));
});

test('invites still warn when nobody has configured mail', function () {
    [$workspace, $manager] = managerInviteSetup();
    setInvitationEmail($workspace, true);

    $this->actingAs($manager)
        ->post(route('workspace.invitations.store', $workspace), ['email' => 'nomail@example.com', 'role' => 'member'])
        ->assertSessionHas('warning', fn ($w) => str_contains($w, 'not configured'));
});

test('the Team page says up front when invitation emails won\'t be sent', function () {
    [$workspace, $manager] = managerInviteSetup();
    $issue = fn () => $this->actingAs($manager)->get(route('team.index', $workspace))
        ->assertInertia(fn (Assert $page) => $page)->inertiaProps('invitationEmailIssue');

    expect($issue())->toBe('not_configured');
    configurePlatformMail();
    Cache::flush(); // the first load cached "no Super Admin yet" (platformSettingsUserId)
    expect($issue())->toBe('turned_off');
    setInvitationEmail($workspace, true);
    expect($issue())->toBeNull();
});

test('a manager still cannot invite a manager', function () {
    [$workspace, $manager] = managerInviteSetup();

    $this->actingAs($manager)
        ->post(route('workspace.invitations.store', $workspace), ['email' => 'new.manager@example.com', 'role' => 'manager'])
        ->assertForbidden();

    expect(WorkspaceInvitation::where('email', 'new.manager@example.com')->exists())->toBeFalse();
});
