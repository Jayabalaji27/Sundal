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

    /**
     * Whether this workspace sends through its own mail server (Settings → Email Settings)
     * rather than the platform's.
     */
    public static function usesOwnServer($userId, $workspaceId): bool
    {
        return self::workspaceMailSettings($userId, $workspaceId) !== null;
    }

    /**
     * True when a mail host is, or resolves to, this server or a private network
     * (loopback, 10/8, 172.16/12, 192.168/16, link-local, ULA ...). A company's mail server
     * must be public: otherwise "Send test email" would let any company probe the
     * platform's internal network. Unresolvable names are allowed (the send just fails).
     */
    public static function isPrivateHost(string $host): bool
    {
        $host = strtolower(trim($host, " \t\n\r\0\x0B[]"));
        if ($host === '' || $host === 'localhost' || preg_match('/\.(localhost|local|internal|lan)$/', $host)) {
            return true;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : array_merge(@gethostbynamel($host) ?: [], array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'));

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The workspace's own SMTP settings when they are complete and usable, else null.
     * In SaaS mode company-level settings are SMTP only and never point inside the
     * server's network, whatever was saved (also covers settings stored before these
     * rules existed).
     */
    private static function workspaceMailSettings($userId, $workspaceId): ?array
    {
        if (!$userId) {
            return null;
        }

        $userSettings = Setting::getUserSettings($userId, $workspaceId);
        if (empty($userSettings['email_host'])) {
            return null;
        }

        $workspaceMail = [
            'driver' => $userSettings['email_driver'] ?? 'smtp',
            'host' => $userSettings['email_host'],
            'port' => $userSettings['email_port'] ?? '587',
            'username' => $userSettings['email_username'] ?? '',
            'password' => $userSettings['email_password'] ?? '',
            'encryption' => $userSettings['email_encryption'] ?? 'tls',
            'fromAddress' => $userSettings['email_from_address'] ?? $userSettings['email_username'] ?? 'noreply@example.com',
            'fromName' => $userSettings['email_from_name'] ?? config('app.name')
        ];

        // SaaS only: a self-hosted install's company owns the server and may use sendmail
        // or an internal relay.
        $restricted = isSaasMode() && (int) $userId !== (int) platformSettingsUserId();
        if ($restricted && ($workspaceMail['driver'] !== 'smtp' || self::isPrivateHost($workspaceMail['host']))) {
            return null;
        }

        return self::isValidMailConfig($workspaceMail) ? $workspaceMail : null;
    }

    /**
     * Platform mail server only: account emails that come from the platform itself
     * (sign-up verification, password reset), not from a company.
     */
    public static function usePlatform(): void
    {
        self::setDynamicConfig(null, null);
    }

    /**
     * SaaS mode: a company's emails go only through its own mail server (Settings → Email
     * Settings). With none set up they aren't sent; the platform's server is not lent out.
     */
    private static function companyOnly($userId): bool
    {
        return isSaasMode() && $userId && (int) $userId !== (int) platformSettingsUserId();
    }

    private static function getMailSettings($userId = null, $workspaceId = null)
    {
        // The workspace's own mail server first.
        if ($workspaceMail = self::workspaceMailSettings($userId, $workspaceId)) {
            return $workspaceMail;
        }

        // A company without one: unconfigured (placeholder values fail isValidMailConfig,
        // so setDynamicConfig() falls back to the 'log' driver and callers that check
        // isEmailConfigured() show "set up your mail server" instead of sending).
        if (self::companyOnly($userId)) {
            return [
                'driver' => 'smtp', 'host' => '', 'port' => '587', 'username' => '', 'password' => '',
                'encryption' => 'tls', 'fromAddress' => '', 'fromName' => config('app.name'),
            ];
        }

        // The platform's settings (platform emails, or a self-hosted install's company). In
        // SaaS mode name the Super Admin explicitly: with no user, getSetting() reads the
        // *logged-in* user's settings, which would be a company's while one is signed in.
        // (Non-SaaS mode keeps null: getSetting() then resolves the company user itself.)
        $platformUserId = isSaasMode() ? platformSettingsUserId() : null;

        return [
            'driver' => getSetting('email_driver', 'smtp', $platformUserId),
            'host' => getSetting('email_host', 'smtp.example.com', $platformUserId),
            'port' => getSetting('email_port', '587', $platformUserId),
            'username' => getSetting('email_username', '', $platformUserId),
            'password' => getSetting('email_password', '', $platformUserId),
            'encryption' => getSetting('email_encryption', 'tls', $platformUserId),
            'fromAddress' => getSetting('email_from_address', 'noreply@example.com', $platformUserId),
            'fromName' => getSetting('email_from_name', config('app.name'), $platformUserId)
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