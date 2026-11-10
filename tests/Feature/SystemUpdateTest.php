<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

/**
 * System update page: the superadmin runs a new version's database changes
 * from the browser, for servers without a terminal.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->resultFile = storage_path('framework/system-update-last.json');
    @unlink($this->resultFile);
});

afterEach(function () {
    @unlink($this->resultFile);
    if (isset($this->migrationDir)) {
        array_map('unlink', glob($this->migrationDir . '/*.php'));
        @rmdir($this->migrationDir);
    }
});

function updateSuperAdmin(): User
{
    $admin = User::factory()->create(['type' => 'superadmin']);
    $admin->assignRole('superadmin');

    return $admin;
}

/** Registers one extra, empty migration so the database has a change waiting. */
function pendingMigration($test): string
{
    $test->migrationDir = storage_path('framework/testing-migrations-' . uniqid());
    mkdir($test->migrationDir, 0777, true);
    file_put_contents($test->migrationDir . '/2099_01_01_000000_system_update_test.php', <<<'PHP'
        <?php
        use Illuminate\Database\Migrations\Migration;
        return new class extends Migration {
            public function up(): void {}
            public function down(): void {}
        };
        PHP);
    app('migrator')->path($test->migrationDir);

    return '2099_01_01_000000_system_update_test';
}

test('only the superadmin can open or run it', function () {
    $company = User::factory()->create(['type' => 'company']);
    $company->assignRole('company');

    $this->get(route('system-update.show'))->assertRedirect(route('login'));
    $this->actingAs($company)->get(route('system-update.show'))->assertForbidden();
    $this->actingAs($company)->post(route('system-update.run'))->assertForbidden();
    expect(file_exists($this->resultFile))->toBeFalse();

    $this->actingAs(updateSuperAdmin())->get(route('system-update.show'))
        ->assertOk()
        ->assertSee('The database is up to date.')
        ->assertSee('Back up the database first');
});

test('a waiting change sends the superadmin there; running it applies it, then permissions, roles and caches', function () {
    $admin = updateSuperAdmin();
    $migration = pendingMigration($this);

    $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('system-update.show'));
    $this->actingAs($admin)->get(route('system-update.show'))
        ->assertOk()
        ->assertSee('1 database change is waiting.')
        ->assertSee($migration);

    $this->actingAs($admin)->post(route('system-update.run'))->assertRedirect(route('system-update.show'));

    $result = json_decode(file_get_contents($this->resultFile), true);
    expect($result['ok'])->toBeTrue()
        ->and(array_column($result['steps'], 'ok', 'label'))->toMatchArray([
            'Database changes' => true, 'Permissions' => true, 'Roles' => true, 'Permission cache' => true, 'Caches' => true,
        ])
        ->and($result['pending_after'])->toBe([])
        ->and($result['steps'][0]['output'])->toContain($migration);
    $this->assertDatabaseHas('migrations', ['migration' => $migration]);

    $this->actingAs($admin)->get(route('system-update.show'))
        ->assertSee('The database is up to date.')
        ->assertSee('finished');
});

test('a second run while one is going is refused', function () {
    $lock = fopen(storage_path('framework/system-update.lock'), 'c');
    flock($lock, LOCK_EX);

    try {
        $this->actingAs(updateSuperAdmin())->post(route('system-update.run'))
            ->assertRedirect(route('system-update.show'))
            ->assertSessionHas('system_update_error');
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    expect(file_exists($this->resultFile))->toBeFalse();
});

test('the installer\'s /update wizard is off', function () {
    pendingMigration($this);

    $this->get('/update')->assertNotFound();
});
