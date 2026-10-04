<?php
/**
 * QA report 2026-10-04, CP3: a page's URL must not change when its title is
 * edited, so existing links to /page/<slug> keep working.
 */

use App\Models\LandingPageCustomPage;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

test('renaming a custom page keeps its slug', function () {
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

    $page = LandingPageCustomPage::create([
        'title' => 'Help Center', 'slug' => 'help-center', 'content' => '<p>Help</p>', 'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('landing-page.custom-pages.update', $page), [
            'title' => 'Support Hub', 'content' => '<p>Help</p>', 'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($page->fresh()->slug)->toBe('help-center');
    $this->get(route('custom-page.show', 'help-center'))->assertOk();
});
