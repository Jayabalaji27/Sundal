<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StorageConfigService
{
    /**
     * Get the active storage disk name
     */
    public static function getActiveDisk(): string
    {
        $config = self::getStorageConfig();
        return $config['disk'] ?? 'public';
    }

    /**
     * Get file validation rules based on settings
     */
    public static function getFileValidationRules(): array
    {
        $config = self::getStorageConfig();
        
        $allowedTypes = $config['allowed_file_types'] ?? '';
        // max_file_size_kb is stored in KB directly
        $maxKb = (int) ($config['max_file_size_kb'] ?? 2048);

        return [
            'mimes:' . $allowedTypes,
            'max:' . $maxKb
        ];
    }

    /**
     * Get complete storage configuration.
     * SaaS mode  → always use superadmin settings
     * Non-SaaS   → use company settings (resolved from current user hierarchy)
     * No cache   → always fresh from DB (avoids stale permission-denied cache files)
     */
    public static function getStorageConfig(): array
    {
        try {
            $user = app('auth')->user();
            if (!$user) {
                return self::getDefaultConfig();
            }

            $settingsUserId = self::resolveSettingsUserId($user);

            if (!$settingsUserId) {
                return self::getDefaultConfig();
            }

            return self::loadStorageConfigFromDB($settingsUserId);
        } catch (\Exception $e) {
            \Log::error('Error in getStorageConfig', ['error' => $e->getMessage()]);
            return self::getDefaultConfig();
        }
    }

    /**
     * Resolve which user's settings to use:
     * SaaS    → superadmin settings (storage set by superadmin, applies to all)
     * Non-SaaS → root company user settings (created_by = 0 or null)
     *
     * All workspace members (owner/manager/member/client) are type=company
     * with created_by pointing to their root company user.
     */
    private static function resolveSettingsUserId($user): ?int
    {
        if (isSaasMode()) {
            $superAdmin = \App\Models\User::where('type', 'superadmin')->first();
            return $superAdmin?->id;
        }

        // Non-SaaS: walk up created_by until we find the root company (created_by = 0 or null)
        return self::getRootCompanyId($user);
    }

    /**
     * Walk up created_by hierarchy to find root company user
     */
    private static function getRootCompanyId($user): ?int
    {
        // Root company has created_by = 0 or null
        if (empty($user->created_by) || $user->created_by == 0) {
            return $user->id;
        }

        $parent = \App\Models\User::find($user->created_by);
        if ($parent) {
            return self::getRootCompanyId($parent);
        }

        return $user->id;
    }

    /**
     * Load storage configuration from database.
     * SaaS    : workspace_id = null (superadmin global settings)
     * Non-SaaS: workspace_id = current user's current_workspace_id
     */
    private static function loadStorageConfigFromDB(?int $userId): array
    {
        try {
            if (!$userId) {
                return self::getDefaultConfig();
            }

            // Non-SaaS: fetch workspace-specific settings using current user's workspace
            $workspaceId = null;
            if (!isSaasMode()) {
                $user = app('auth')->user();
                $workspaceId = $user?->current_workspace_id ?? null;
            }

            $settings = DB::table('settings')
                ->where('user_id', $userId)
                ->where(function ($q) use ($workspaceId) {
                    if ($workspaceId) {
                        $q->where('workspace_id', $workspaceId);
                    } else {
                        $q->whereNull('workspace_id');
                    }
                })
                ->whereIn('key', [
                    'storage_type',
                    'storage_file_types',
                    'storage_max_upload_size',
                    'aws_access_key_id',
                    'aws_secret_access_key',
                    'aws_default_region',
                    'aws_bucket',
                    'aws_url',
                    'aws_endpoint',
                    'wasabi_access_key',
                    'wasabi_secret_key',
                    'wasabi_region',
                    'wasabi_bucket',
                    'wasabi_url',
                    'wasabi_root',
                ])
                ->pluck('value', 'key')
                ->toArray();

            // Non-SaaS fallback: if workspace settings empty, use global (workspace_id=null)
            if (!isSaasMode() && $workspaceId && empty($settings)) {
                $settings = DB::table('settings')
                    ->where('user_id', $userId)
                    ->whereNull('workspace_id')
                    ->whereIn('key', [
                        'storage_type', 'storage_file_types', 'storage_max_upload_size',
                        'aws_access_key_id', 'aws_secret_access_key', 'aws_default_region',
                        'aws_bucket', 'aws_url', 'aws_endpoint',
                        'wasabi_access_key', 'wasabi_secret_key', 'wasabi_region',
                        'wasabi_bucket', 'wasabi_url', 'wasabi_root',
                    ])
                    ->pluck('value', 'key')
                    ->toArray();
            }

            $storageType = $settings['storage_type'] ?? 'local';
            $diskName = match ($storageType) {
                'aws_s3'  => 's3',
                'wasabi'  => 'wasabi',
                default   => 'public',
            };

            return [
                'disk'               => $diskName,
                'allowed_file_types' => $settings['storage_file_types'] ?? 'jpg,png,webp,gif',
                'max_file_size_kb'   => (int) ($settings['storage_max_upload_size'] ?? 2048),
                's3' => [
                    'key'      => $settings['aws_access_key_id'] ?? '',
                    'secret'   => $settings['aws_secret_access_key'] ?? '',
                    'bucket'   => $settings['aws_bucket'] ?? '',
                    'region'   => $settings['aws_default_region'] ?? 'us-east-1',
                    'url'      => $settings['aws_url'] ?? '',
                    'endpoint' => $settings['aws_endpoint'] ?? '',
                ],
                'wasabi' => [
                    'key'    => $settings['wasabi_access_key'] ?? '',
                    'secret' => $settings['wasabi_secret_key'] ?? '',
                    'bucket' => $settings['wasabi_bucket'] ?? '',
                    'region' => $settings['wasabi_region'] ?? 'us-east-1',
                    'url'    => $settings['wasabi_url'] ?? '',
                    'root'   => $settings['wasabi_root'] ?? '',
                ],
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to load storage config from DB', ['error' => $e->getMessage()]);
            return self::getDefaultConfig();
        }
    }

    /**
     * Clear storage configuration cache (no-op since cache is disabled)
     */
    public static function clearCache(): void
    {
        // Cache is intentionally disabled to avoid stale permission issues
    }

    /**
     * Get default storage configuration
     */
    private static function getDefaultConfig(): array
    {
        return [
            'disk'               => 'public',
            'allowed_file_types' => 'jpg,png,webp,gif',
            'max_file_size_kb'   => 2048,
            's3'                 => [],
            'wasabi'             => [],
        ];
    }
}