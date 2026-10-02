<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\Workspace;
use App\Mail\CommonTemplateMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Exception;

class EmailTemplateService
{
    public function sendTemplateEmail(string $templateName, array $variables, string $toEmail, $business = null, string $toName = null)
    {
        try {
            // Get email template
            $template = EmailTemplate::where('name', $templateName)->first();
            
            if (!$template) {
                throw new Exception("Email template '{$templateName}' not found");
            }

            // Get user's language or default to 'en'
            $language = 'en'; // default
            
            // Get template content for the language
            $templateLang = $template->emailTemplateLangs()
                ->where('lang', $language)
                ->first();

            // Fallback to English if language not found
            if (!$templateLang) {
                $templateLang = $template->emailTemplateLangs()
                    ->where('lang', 'en')
                    ->first();
            }
            
            if (!$templateLang) {
                throw new Exception("No content found for template '{$templateName}'");
            }

            // Replace variables in subject and content
            $subject = $this->replaceVariables($templateLang->subject, $variables);
            $content = $this->replaceVariables($templateLang->content, $variables);
            $fromName = $this->replaceVariables($template->from, $variables);

            // Configure SMTP settings
            $this->configureBusinessSMTP($business);

            // Get final email settings
            $fromEmail = getSetting('email_from_address') ?: config('mail.from.address');
            $finalFromName = getSetting('email_from_name') ? $this->replaceVariables(getSetting('email_from_name'), $variables) : $fromName;

            // Send email using styled notification template
            Mail::send('emails.notification', [
                'subject' => $subject,
                'content' => $content
            ], function ($message) use ($subject, $toEmail, $toName, $fromEmail, $finalFromName) {
                $message->to($toEmail, $toName)
                    ->subject($subject)
                    ->from($fromEmail, $finalFromName);
            });

            return true;
        } catch (Exception $e) {
            \Log::error('Email sending failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function replaceVariables(string $content, array $variables): string
    {
        return str_replace(array_keys($variables), array_values($variables), $content);
    }

    public function sendTemplateEmailWithLanguage(string $templateName, array $variables, string $toEmail, string $toName = null, string $language = 'en', $business = null)
    {
        try {
            
            // Get email template
            $template = EmailTemplate::where('name', $templateName)->first();
            
            if (!$template) {
                throw new Exception("Email template '{$templateName}' not found");
            }
            
            // Get template content for the specified language
            $templateLang = $template->emailTemplateLangs()
                ->where('lang', $language)
                ->first();
            
            // Fallback to English if language not found
            if (!$templateLang) {
                $templateLang = $template->emailTemplateLangs()
                    ->where('lang', 'en')
                    ->first();
            }
            
            if (!$templateLang) {
                throw new Exception("No content found for template '{$templateName}'");
            }

            // Replace variables in subject and content
            $subject = $this->replaceVariables($templateLang->subject, $variables);
            $content = $this->replaceVariables($templateLang->content, $variables);
            $fromName = $this->replaceVariables($template->from, $variables);

            // Configure SMTP settings
            $this->configureBusinessSMTP($business);

            // Get final email settings
            $fromEmail = getSetting('email_from_address') ?: config('mail.from.address');
            $finalFromName = getSetting('email_from_name') ? $this->replaceVariables(getSetting('email_from_name'), $variables) : $fromName;

            // Send email using styled notification template
            Mail::send('emails.notification', [
                'subject' => $subject,
                'content' => $content
            ], function ($message) use ($subject, $toEmail, $toName, $fromEmail, $finalFromName) {
                $message->to($toEmail, $toName)
                    ->subject($subject)
                    ->from($fromEmail, $finalFromName);
            });
            
            return true;
        } catch (Exception $e) {
            \Log::error('Email sending failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function configureBusinessSMTP($business = null)
    {
        [$userId, $workspaceId] = $this->resolveMailScope($business);

        // Resolve mail settings against the WORKSPACE OWNER, not whichever
        // user happens to be currently authenticated. getSetting()'s default
        // auto-scoping resolves to the *acting* user's own settings (or their
        // creator's) - for a Manager sending a team invite, that is not the
        // same row WorkspaceInvitationController::store() validates against
        // ($workspace->owner_id) before dispatching this send, so the two
        // could disagree and the actual send would silently fail. Delegate to
        // MailConfigService, which already implements this scoping correctly
        // (and is what verification emails use) instead of duplicating it.
        if (! \App\Services\MailConfigService::isEmailConfigured($userId, $workspaceId)) {
            throw new Exception("Email settings not configured. Please configure email settings in system settings.");
        }

        \App\Services\MailConfigService::setDynamicConfig($userId, $workspaceId);
    }

    /**
     * Accepts a Workspace instance, a workspace id, or null, and resolves it
     * to [ownerUserId, workspaceId] for mail-settings lookup.
     */
    private function resolveMailScope($business): array
    {
        if ($business instanceof Workspace) {
            return [$business->owner_id, $business->id];
        }

        if ($business) {
            $workspace = Workspace::find($business);
            if ($workspace) {
                return [$workspace->owner_id, $workspace->id];
            }
        }

        return [null, null];
    }
}