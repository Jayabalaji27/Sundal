<?php

use App\Mail\TestMail;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\MailConfigService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['app.is_saas' => true]);
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
    Cache::flush();
});

/** A company owner with a workspace, the way a SaaS signup looks. */
function emailCompany(): array
{
    $owner = User::factory()->create(['type' => 'company']);
    $workspace = Workspace::create(['name' => 'AdeptView', 'slug' => 'adeptview-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    return [$owner->fresh(), $workspace];
}

/** The Super Admin's platform mail server (saved while nobody is logged in, as the admin would). */
function platformMail(): void
{
    $superadmin = User::factory()->create(['type' => 'superadmin']);
    auth()->guard()->forgetUser();
    foreach ([
        'email_driver' => 'smtp', 'email_host' => 'smtp.gmail.com', 'email_port' => '587',
        'email_username' => 'productsundal@gmail.com', 'email_password' => 'platform-app-pw',
        'email_encryption' => 'tls', 'email_from_address' => 'productsundal@gmail.com', 'email_from_name' => 'Sundal',
    ] as $key => $value) {
        updateSetting($key, $value, $superadmin->id, null);
    }
    Cache::flush();
}

function ownServer(array $overrides = []): array
{
    return array_merge([
        'provider' => 'smtp', 'driver' => 'smtp', 'host' => 'smtp.gmail.com', 'port' => '587',
        'username' => 'hr@adeptview.com', 'password' => 'company-app-pw', 'encryption' => 'tls',
        'fromAddress' => 'hr@adeptview.com', 'fromName' => 'AdeptView HR',
    ], $overrides);
}

test('a company that sets up its own mail server sends from its own address', function () {
    platformMail();
    [$owner, $workspace] = emailCompany();

    $this->actingAs($owner)->post(route('settings.email.update'), ownServer())
        ->assertSessionHasNoErrors();

    MailConfigService::setDynamicConfig($owner->id, $workspace->id);
    expect(MailConfigService::usesOwnServer($owner->id, $workspace->id))->toBeTrue()
        ->and(config('mail.from.address'))->toBe('hr@adeptview.com')
        ->and(config('mail.mailers.smtp.username'))->toBe('hr@adeptview.com');
});

test('without its own server a company\'s emails are not sent, even when the platform has one', function () {
    platformMail();
    [$owner, $workspace] = emailCompany();

    MailConfigService::setDynamicConfig($owner->id, $workspace->id);
    expect(MailConfigService::isEmailConfigured($owner->id, $workspace->id))->toBeFalse()
        ->and(config('mail.default'))->toBe('log'); // never the platform's smtp
});

test('disconnecting removes the company\'s server and its emails stop', function () {
    platformMail();
    [$owner, $workspace] = emailCompany();
    $this->actingAs($owner)->post(route('settings.email.update'), ownServer());

    $this->actingAs($owner)->post(route('settings.email.update'), ['disconnect' => true])
        ->assertSessionHas('success');

    expect(Setting::where('user_id', $owner->id)->where('workspace_id', $workspace->id)->where('key', 'like', 'email\_%')->count())->toBe(0)
        ->and(MailConfigService::isEmailConfigured($owner->id, $workspace->id))->toBeFalse();
});

test('account emails (password reset, verification) still come from the platform', function () {
    platformMail();
    [$owner] = emailCompany(); // no company mail server

    MailConfigService::usePlatform();
    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.from.address'))->toBe('productsundal@gmail.com');

    \Illuminate\Support\Facades\Notification::fake();
    $this->post(route('password.email'), ['email' => $owner->email]);
    \Illuminate\Support\Facades\Notification::assertSentTo($owner, \Illuminate\Auth\Notifications\ResetPassword::class);
    expect(config('mail.from.address'))->toBe('productsundal@gmail.com');
});

test('emails a company user triggers (task, invoice, meeting ...) use that company\'s server', function () {
    platformMail();
    [$owner, $workspace] = emailCompany();
    $this->actingAs($owner)->post(route('settings.email.update'), ownServer());

    // Listeners that don't name a workspace: resolved from the signed-in user's workspace.
    $scope = (new ReflectionMethod(\App\Services\EmailTemplateService::class, 'resolveMailScope'))
        ->invoke(app(\App\Services\EmailTemplateService::class), null);
    expect($scope)->toBe([$owner->id, $workspace->id]);

    // The Super Admin's own sends stay platform emails.
    $superadmin = User::where('type', 'superadmin')->first();
    $this->actingAs($superadmin);
    $scope = (new ReflectionMethod(\App\Services\EmailTemplateService::class, 'resolveMailScope'))
        ->invoke(app(\App\Services\EmailTemplateService::class), null);
    expect($scope)->toBe([null, null]);
});

test('a company\'s server must be public SMTP on a mail port', function (array $bad, string $field) {
    [$owner] = emailCompany();

    $this->actingAs($owner)->post(route('settings.email.update'), ownServer($bad))
        ->assertSessionHasErrors($field);
})->with([
    'the server itself' => [['host' => '127.0.0.1'], 'host'],
    'private network' => [['host' => '10.0.0.5'], 'host'],
    'localhost name' => [['host' => 'localhost'], 'host'],
    'internal name' => [['host' => 'mail.internal'], 'host'],
    'not a mail port' => [['port' => '3306'], 'port'],
    'not SMTP' => [['driver' => 'sendmail'], 'driver'],
    'no password yet' => [['password' => ''], 'password'],
]);

test('settings saved before these rules that point inside the network are ignored', function () {
    platformMail();
    [$owner, $workspace] = emailCompany();
    foreach (['email_driver' => 'smtp', 'email_host' => '192.168.1.10', 'email_port' => '25', 'email_username' => 'x',
              'email_password' => 'y', 'email_from_address' => 'x@adeptview.com'] as $key => $value) {
        Setting::create(['user_id' => $owner->id, 'workspace_id' => $workspace->id, 'key' => $key, 'value' => $value]);
    }
    Cache::flush();

    expect(MailConfigService::usesOwnServer($owner->id, $workspace->id))->toBeFalse();
    MailConfigService::setDynamicConfig($owner->id, $workspace->id);
    expect(config('mail.default'))->toBe('log'); // not sent at all, rather than to the internal host
});

test('only the workspace owner can change its email settings', function () {
    [$owner, $workspace] = emailCompany();
    $other = User::factory()->create(['type' => 'company', 'current_workspace_id' => $workspace->id]);
    $other->assignRole('company');

    $this->actingAs($other)->post(route('settings.email.update'), ownServer())->assertForbidden();
});

test('the test email needs the company\'s own server', function () {
    platformMail();
    [$owner] = emailCompany();
    Mail::fake();

    $this->actingAs($owner)->post(route('settings.email.test'), ['email' => 'me@example.com'])
        ->assertSessionHasErrors('error');
    Mail::assertNothingSent();

    $this->actingAs($owner)->post(route('settings.email.update'), ownServer());
    $this->actingAs($owner)->post(route('settings.email.test'), ['email' => 'me@example.com'])
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'through your mail server'));
    Mail::assertSent(TestMail::class, 1);
});

test('the Super Admin can still use any server for the platform email', function () {
    $superadmin = User::factory()->create(['type' => 'superadmin']);
    $superadmin->assignRole('superadmin');

    $this->actingAs($superadmin)->post(route('settings.email.update'), ownServer(['host' => 'relay.internal', 'port' => '2526']))
        ->assertSessionHasNoErrors();
});
