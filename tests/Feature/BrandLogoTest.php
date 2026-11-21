<?php

use App\Models\User;
use App\Models\Workspace;
use App\Support\BrandLogo;
use Illuminate\Support\Facades\File;

function brandWorkspace(?string $logoDark = null): Workspace
{
    $owner = User::factory()->create(['type' => 'company']);
    $workspace = Workspace::create(['name' => 'Logo WS', 'slug' => 'logo-' . uniqid(), 'owner_id' => $owner->id, 'is_active' => true]);
    if ($logoDark !== null) {
        updateSetting('logoDark', $logoDark, $owner->id, $workspace->id);
    }

    return $workspace;
}

test('documents fall back to the bundled Sundal logo until the company uploads one', function () {
    $file = BrandLogo::file(brandWorkspace());

    expect($file)->toBe(realpath(public_path('images/logos/logo-dark.png')))
        ->and(BrandLogo::dataUri(brandWorkspace()))->toStartWith('data:image/png;base64,');
});

test('an uploaded logo is used, however its path was stored', function (string $stored) {
    $dir = storage_path('app/public/media');
    File::ensureDirectoryExists($dir);
    $name = 'brand-test-' . uniqid() . '.png';
    File::copy(public_path('images/logos/favicon.png'), "$dir/$name");

    try {
        $value = str_replace('NAME', $name, $stored);
        expect(BrandLogo::file(brandWorkspace($value)))->toBe(realpath("$dir/$name"));
    } finally {
        File::delete("$dir/$name");
    }
})->with([
    'storage path' => '/storage/media/NAME',
    'bare file name' => 'NAME',
    'full URL on this site' => 'https://sundal.example/storage/media/NAME?v=2',
]);

test('a logo that is not a local image gives no logo, never a wrong one or an outside file', function (string $stored) {
    expect(BrandLogo::file(brandWorkspace($stored)))->toBeNull()
        ->and(BrandLogo::dataUri(brandWorkspace($stored)))->toBeNull();
})->with([
    'hosted elsewhere' => 'https://cdn.example.com/acme/logo.png',
    'path traversal' => '/storage/../../.env',
    'not an image' => '/index.php',
    'svg (dompdf/Word cannot embed it reliably)' => '/images/logos/sundal-mark.svg',
]);

test('the invoice logo wins over the brand logo, as in the on-screen preview', function () {
    expect(BrandLogo::file(brandWorkspace(), '/images/logos/favicon.png'))
        ->toBe(realpath(public_path('images/logos/favicon.png')));
});

test('emails get an absolute URL to the dark-ink logo', function () {
    config(['app.url' => 'https://app.example']);

    expect(BrandLogo::url(brandWorkspace()))->toBe('https://app.example/images/logos/logo-dark.png')
        ->and(BrandLogo::url(brandWorkspace('acme.png')))->toBe('https://app.example/storage/media/acme.png')
        ->and(BrandLogo::url(brandWorkspace('https://cdn.example.com/acme.png')))->toBe('https://cdn.example.com/acme.png');
});
