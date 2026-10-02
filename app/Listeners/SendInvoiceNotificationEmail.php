<?php

namespace App\Listeners;

use App\Events\InvoiceCreated;
use App\Services\EmailTemplateService;
use Exception;

class SendInvoiceNotificationEmail
{
    public function __construct(
        private EmailTemplateService $emailService,
    ) {
    }

    public function handle(InvoiceCreated $event): void
    {
        $invoice = $event->invoice;
        
        // Only send notification if invoice has a client
        if (!$invoice->client) {
            return;
        }

        if (isEmailTemplateEnabled('Invoice Notification', createdBy())) {
            // Prepare email variables
            $variables = [
                '{client_name}' => $invoice->client->name ?? '-',
                '{invoice_number}' => $invoice->invoice_number ?? '-',
                '{invoice_title}' => $invoice->title ?? '-',
                '{project_name}' => $invoice->project->title ?? '-',
                '{total_amount}' => number_format($invoice->total_amount, 2) ?? '-',
                '{currency}' => $invoice->project->currency ?? 'USD',
                '{due_date}' => $invoice->due_date ? $invoice->due_date->format('M d, Y') : '-',
                '{workspace_name}' => $invoice->workspace->name ?? '-',
                '{creator_name}' => $invoice->creator->name ?? '-',
                '{company_name}' => config('app.name'),
                '{app_name}' => config('app.name'),
            ];

            try {
                session()->forget('email_error');

                $userLanguage = (auth()->user() && auth()->user()->lang) ? auth()->user()->lang : 'en';

                $this->emailService->sendTemplateEmailWithLanguage(
                    templateName: 'Invoice Notification',
                    variables: $variables,
                    toEmail: $invoice->client->email,
                    toName: $invoice->client->name,
                    language: $userLanguage
                );
            } catch (Exception $e) {
                $errorMessage = $e->getMessage();
                if (!str_contains($errorMessage, 'Too many emails per second') &&
                    !str_contains($errorMessage, '550 5.7.0') &&
                    !str_contains($errorMessage, 'rate limit')) {
                    session()->flash('email_error', 'Failed to send invoice notification email: ' . $errorMessage);
                }
            }
        }
    }
}