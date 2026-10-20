<?php
/**
 * Media Library uploads (Project > Files and every other picker): uploads stay
 * listed, documents are allowed, and the picker gets the server's real limits.
 */

use App\Models\MediaItem;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

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
    Storage::fake('public');

    // SaaS mode: the platform admin's Storage settings apply to every workspace.
    $admin = User::factory()->create(['type' => 'superadmin']);
    foreach (['storage_type' => 'local', 'storage_file_types' => 'jpg,png,pdf,docx', 'storage_max_upload_size' => '2048'] as $key => $value) {
        Setting::create(['user_id' => $admin->id, 'workspace_id' => null, 'key' => $key, 'value' => $value]);
    }
});

/** Owner + workspace on the default plan. */
function mlOwner(): array
{
    $plan = Plan::where('is_default', true)->first();
    $owner = User::factory()->create(['type' => 'company', 'plan_id' => $plan?->id, 'plan_is_active' => 1]);
    $workspace = Workspace::create(['name' => 'ML WS', 'slug' => 'ml-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    $owner->update(['current_workspace_id' => $workspace->id]);
    $owner->assignRole('company');

    return [$owner, $workspace];
}

function mlUpload($test, User $user, UploadedFile ...$files)
{
    return $test->actingAs($user)->post(route('api.media.batch'), ['files' => $files], ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']);
}

test('an upload is saved to the workspace and still listed when the library is opened again', function () {
    [$owner, $ws] = mlOwner();

    mlUpload($this, $owner, UploadedFile::fake()->image('site-photo.png', 40, 40))->assertOk();

    $item = MediaItem::where('name', 'site-photo.png')->sole();
    expect($item->workspace_id)->toBe($ws->id)->and($item->user_id)->toBe($owner->id);
    expect(collect($this->actingAs($owner)->getJson(route('api.media.index'))->assertOk()->json())->pluck('name')->all())
        ->toBe(['site-photo']);
});

test('documents upload as well as images, and .jpeg is accepted when the settings list jpg', function () {
    [$owner] = mlOwner();

    mlUpload($this, $owner,
        UploadedFile::fake()->create('scope.pdf', 120, 'application/pdf'),
        UploadedFile::fake()->create('contract.docx', 80, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        UploadedFile::fake()->image('receipt.jpeg', 40, 40),
    )->assertOk()->assertJsonCount(3, 'data');

    $listed = collect($this->actingAs($owner)->getJson(route('api.media.index'))->json())->keyBy('file_name');
    expect($listed->keys()->sort()->values()->all())->toBe(['contract.docx', 'receipt.jpeg', 'scope.pdf'])
        // Only images get a thumbnail; documents point at the file itself.
        ->and($listed['scope.pdf']['thumb_url'])->toBe($listed['scope.pdf']['url'])
        ->and($listed['receipt.jpeg']['thumb_url'])->toContain('/conversions/receipt-thumb.jpg');
});

test('a file type outside the storage settings is refused with the allowed list', function () {
    [$owner] = mlOwner();

    mlUpload($this, $owner, UploadedFile::fake()->create('archive.zip', 10, 'application/zip'))
        ->assertUnprocessable()
        ->assertJsonPath('errors.0', 'Only these file types are allowed: JPG, PNG, PDF, DOCX, JPEG');
    expect(MediaItem::count())->toBe(0);
});

test('pages get the upload limits the server enforces, so the picker is not images-only', function () {
    [$owner, $ws] = mlOwner();
    $project = Project::create(['workspace_id' => $ws->id, 'title' => 'Files', 'status' => 'active', 'priority' => 'medium',
        'progress' => 0, 'created_by' => $owner->id, 'visibility' => 'private']);

    $this->actingAs($owner)->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->where('storageSettings.allowed_file_types', 'jpg,png,pdf,docx,jpeg')
            ->where('storageSettings.max_file_size_kb', 2048)
            ->where('allowed_file_types', 'jpg,png,pdf,docx,jpeg'));
});

test('an uploaded file can be attached to a project', function () {
    [$owner, $ws] = mlOwner();
    $project = Project::create(['workspace_id' => $ws->id, 'title' => 'Files', 'status' => 'active', 'priority' => 'medium',
        'progress' => 0, 'created_by' => $owner->id, 'visibility' => 'private']);

    $id = mlUpload($this, $owner, UploadedFile::fake()->create('scope.pdf', 120, 'application/pdf'))->json('data.0.id');
    $this->actingAs($owner)->post(route('project-attachments.store', $project), ['media_ids' => [$id]])->assertRedirect();

    expect(DB::table('project_attachments')->where('project_id', $project->id)->pluck('media_item_id')->all())->toBe([$id]);
});

test('contract attachments follow the Storage settings the upload dialog checks against', function () {
    [$owner, $ws] = mlOwner();
    $type = \App\Models\ContractType::firstOrCreate(['workspace_id' => $ws->id, 'name' => 'Std'], ['is_active' => true, 'created_by' => $owner->id]);
    $contract = \App\Models\Contract::create([
        'subject' => 'MSA', 'contract_type_id' => $type->id, 'contract_value' => 10, 'start_date' => now(),
        'end_date' => now()->addMonth(), 'status' => 'sent', 'workspace_id' => $ws->id, 'created_by' => $owner->id,
    ]);
    $upload = fn (UploadedFile ...$files) => $this->actingAs($owner)->post(route('contract-attachments.store', $contract), ['files' => $files]);

    // .jpeg goes with jpg; both files are stored.
    $upload(UploadedFile::fake()->image('signed-page.jpeg', 20, 20), UploadedFile::fake()->create('msa.pdf', 100, 'application/pdf'))
        ->assertSessionHasNoErrors();
    expect($contract->attachments()->count())->toBe(2);

    // Not in the Storage settings, or over their 2 MB limit: refused.
    $upload(UploadedFile::fake()->create('notes.txt', 1, 'text/plain'))->assertSessionHasErrors('files');
    $upload(UploadedFile::fake()->create('scan.pdf', 3000, 'application/pdf'))->assertSessionHasErrors('files');
    expect($contract->attachments()->count())->toBe(2);
});

test('the migration restores uploads saved without a workspace, only when the uploader has one workspace', function () {
    [$single, $ws] = mlOwner();
    [$multi, $first] = mlOwner();
    $second = Workspace::create(['name' => 'Other', 'slug' => 'other-' . uniqid(), 'owner_id' => $multi->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $second->id, 'user_id' => $multi->id, 'role' => 'owner', 'status' => 'active']);

    $orphan = function (User $user, string $name) {
        $item = MediaItem::create(['name' => $name]);
        $media = $item->addMedia(UploadedFile::fake()->image($name, 10, 10))->toMediaCollection('images');
        DB::table('media')->where('id', $media->id)->update(['user_id' => $user->id]);

        return $item;
    };
    $mine = $orphan($single, 'a.png');
    $ambiguous = $orphan($multi, 'b.png');

    (require database_path('migrations/2026_10_20_000001_restore_media_item_owners.php'))->up();

    expect($mine->fresh()->only(['workspace_id', 'user_id']))->toBe(['workspace_id' => $ws->id, 'user_id' => $single->id])
        ->and($ambiguous->fresh()->only(['workspace_id', 'user_id']))->toBe(['workspace_id' => null, 'user_id' => $multi->id]);
});
