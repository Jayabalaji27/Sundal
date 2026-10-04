<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Workspace;
use Illuminate\Support\Facades\Config;

class MailConfigService
{
    public static function setDynamicConfig($userId = null, $workspaceId = null)
    {
        // If workspace is provided, use workspace owner's SMTP settings
        if ($workspaceId) {
            $workspace = Workspace::find($workspaceId);
            if ($workspace) {
                $userId = $workspace->owner_id;
            }
        }
        
        $settings = self::getMailSettings($userId, $workspaceId);
        
        // Validate SMTP settings before applying
        if (!self::isValidMailConfig($settings)) {
            $settings['driver'] = 'log';
        }

        Config::set([
            'mail.default' => $settings['driver'],
            'mail.mailers.smtp.host' => $settings['host'],
            'mail.mailers.smtp.port' => $settings['port'],
            'mail.mailers.smtp.encryption' => $settings['encryption'] === 'none' ? null : $settings['encryption'],
            'mail.mailers.smtp.username' => $settings['username'],
            'mail.mailers.smtp.password' => $settings['password'],
            'mail.from.address' => $settings['fromAddress'],
            'mail.from.name' => $settings['fromName'],
        ]);
    }
    
    /**
     * Whether usable (non-placeholder) mail settings exist for this
     * user/workspace. Callers use this to decide whether to attempt sending
     * an email at all, rather than letting it silently fall back to the
     * 'log' driver via setDynamicConfig().
     */
    public static function isEmailConfigured($userId = null, $workspaceId = null): bool
    {
        return self::isValidMailConfig(self::getMailSettings($userId, $workspaceId));
    }

    private static function getMailSettings($userId = null, $workspaceId = null)
    {
        // Try to get user/workspace specific settings first
        if ($userId) {
            $userSettings = Setting::getUserSettings($userId, $workspaceId);
            if (!empty($userSettings['email_host'])) {
                return [
                    'driver' => $userSettings['email_driver'] ?? 'smtp',
                    'host' => $userSettings['email_host'],
                    'port' => $userSettings['email_port'] ?? '587',
                    'username' => $userSettings['email_username'] ?? '',
                    'password' => $userSettings['email_password'] ?? '',
                    'encryption' => $userSettings['email_encryption'] ?? 'tls',
                    'fromAddress' => $userSettings['email_from_address'] ?? $userSettings['email_username'] ?? 'noreply@example.com',
                    'fromName' => $userSettings['email_from_name'] ?? config('app.name')
                ];
            }
        }
        
        // Fallback to global settings
        return [
            'driver' => getSetting('email_driver', 'smtp'),
            'host' => getSetting('email_host', 'smtp.example.com'),
            'port' => getSetting('email_port', '587'),
            'username' => getSetting('email_username', ''),
            'password' => getSetting('email_password', ''),
            'encryption' => getSetting('email_encryption', 'tls'),
            'fromAddress' => getSetting('email_from_address', 'noreply@example.com'),
            'fromName' => getSetting('email_from_name', config('app.name'))
        ];
    }
    
    private static function isValidMailConfig($settings)
    {
        // Skip validation for non-SMTP drivers
        if ($settings['driver'] !== 'smtp') {
            return true;
        }
        
        // Check for example/placeholder values that indicate misconfiguration
        $invalidHosts = ['smtp.example.com', 'example.com', 'localhost', '127.0.0.1'];

        if (empty($settings['host']) || in_array($settings['host'], $invalidHosts)) {
            return false;
        }
        
        if (empty($settings['fromAddress']) || strpos($settings['fromAddress'], 'example.com') !== false) {
            return false;
        }
        
        // In non-SaaS mode, be more lenient with authentication requirements
        // Some SMTP servers don't require authentication (like local mail servers)
        if (!isSaasMode()) {
            // Only require username/password if host suggests external SMTP service
            $externalSmtpHosts = ['smtp.gmail.com', 'smtp.outlook.com', 'smtp.yahoo.com', 'smtp.mail.yahoo.com', 'smtp.live.com'];
            $requiresAuth = false;
            
            foreach ($externalSmtpHosts as $externalHost) {
                if (strpos($settings['host'], $externalHost) !== false) {
                    $requiresAuth = true;
                    break;
                }
            }
            
            if ($requiresAuth && (empty($settings['username']) || empty($settings['password']))) {
                return false;
            }
        } else {
            // In SaaS mode, always require username and password for SMTP
            if (empty($settings['username']) || empty($settings['password'])) {
                return false;
            }
        }
        
        return true;
    }
}