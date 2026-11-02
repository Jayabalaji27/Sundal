<?php

namespace App\Services\Reports;

use App\Mail\ReportMail;
use App\Models\User;
use App\Services\IntegrationLog;
use App\Services\MailConfigService;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Sends a report through the workspace owner's mail settings (falling back to
 * the platform's), the same scoping invitations and notifications use.
 */
class ReportMailer
{
    /** @throws RuntimeException with a message that can be shown to the user. */
    public function send(RenderedReport $file, string $title, string $to, User $sender, ?string $note = null): void
    {
        $workspace = $sender->currentWorkspace;

        if (!MailConfigService::isEmailConfigured($workspace?->owner_id, $workspace?->id)) {
            throw new RuntimeException(__("Email isn't set up for this workspace yet. Add your mail server in Settings > Email Settings, then try again."));
        }

        MailConfigService::setDynamicConfig($workspace?->owner_id, $workspace?->id);

        try {
            Mail::to($to)->send(new ReportMail($file, $title, $sender->name, $workspace?->name ?? config('app.name'), $note));
        } catch (\Throwable $e) {
            IntegrationLog::failed('mail', 'send', $e, null, ['template' => 'report', 'to' => IntegrationLog::maskEmail($to)]);

            throw new RuntimeException(__("The report couldn't be sent. Check the email settings and try again."), 0, $e);
        }
    }
}
