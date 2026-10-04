<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Saving Branding settings stored the bundled default logos as full URLs
 * (https://<host>/images/logos/logo-dark.png), pinning them to the domain
 * they were saved on. Store those as plain paths, as the defaults are.
 * Only values pointing at the bundled /images/logos/ files are changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->whereIn('key', ['logoDark', 'logoLight', 'favicon'])
            ->where('value', 'like', 'http%/images/logos/%')
            ->orderBy('id')
            ->each(function ($setting) {
                if (!preg_match('#^https?://[^/]+(/images/logos/.+)$#i', $setting->value, $m)) {
                    return;
                }
                DB::table('settings')->where('id', $setting->id)->update(['value' => $m[1]]);
            });
    }

    public function down(): void
    {
        // Paths work on every domain; there is nothing to restore.
    }
};
