<?php
/**
 * The demo accounts panel on the login page (config/demo.php) only appears,
 * with its shared password, while DEMO_LOGIN_PANEL is on.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([
        \App\Http\Middleware\CheckInstallation::class,
        \App\Http\Middleware\ShareGlobalSettings::class,
        \App\Http\Middleware\DemoModeMiddleware::class,
    ]);
});

test('the login page hides demo accounts unless the panel is switched on', function () {
    config(['demo.login_panel' => false]);

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/login')->where('demoLogins', null));
});

test('the panel lists the configured accounts that exist, in order, with the shared password', function () {
    config([
        'demo.login_panel' => true,
        'demo.password' => 'Show@Me1',
        'demo.accounts' => [
            ['role' => 'Owner', 'email' => 'owner@demo.test'],
            ['role' => 'Manager', 'email' => 'missing@demo.test'],
            ['role' => 'Member', 'email' => 'member@demo.test'],
        ],
    ]);
    User::factory()->create(['email' => 'member@demo.test', 'name' => 'Mia Member']);
    User::factory()->create(['email' => 'owner@demo.test', 'name' => 'Omar Owner']);

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('demoLogins.password', 'Show@Me1')
            ->has('demoLogins.accounts', 2)
            ->where('demoLogins.accounts.0', ['role' => 'Owner', 'name' => 'Omar Owner', 'email' => 'owner@demo.test'])
            ->where('demoLogins.accounts.1.name', 'Mia Member'));
});
