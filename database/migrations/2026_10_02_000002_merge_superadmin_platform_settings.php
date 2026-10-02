<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Platform settings are now read and written under one canonical superadmin (the
 * oldest - see platformSettingsUserId()). Settings another superadmin account saved
 * before this change (e.g. Landing Page / User Registration switched off) are copied
 * onto the canonical account so they keep applying; where both accounts have a key,
 * the most recently updated value wins.
 */
return new class extends Migration
{
    public function up(): void
    {
        $superadminIds = DB::table('users')->where('type', 'superadmin')->orderBy('id')->pluck('id');
        if ($superadminIds->count() < 2) {
            return;
        }

        $canonicalId = $superadminIds->first();

        $rows = DB::table('settings')
            ->whereIn('user_id', $superadminIds->slice(1)->values())
            ->whereNull('workspace_id')
            ->orderBy('updated_at')
            ->get();

        foreach ($rows as $row) {
            $existing = DB::table('settings')
                ->where('user_id', $canonicalId)
                ->whereNull('workspace_id')
                ->where('key', $row->key)
                ->first();

            if (!$existing) {
                DB::table('settings')->insert([
                    'user_id' => $canonicalId,
                    'workspace_id' => null,
                    'key' => $row->key,
                    'value' => $row->value,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            } elseif ($row->updated_at && (!$existing->updated_at || $row->updated_at > $existing->updated_at)) {
                DB::table('settings')->where('id', $existing->id)->update([
                    'value' => $row->value,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        Cache::forget("settings_{$canonicalId}_");
        Cache::forget('settings_platform_user_id');
        Cache::forget('settings_default_user');
    }

    public function down(): void
    {
        // Data merge - nothing to undo.
    }
};
