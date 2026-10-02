<?php

namespace App\Listeners;

use App\Events\ContractCreated;
use App\Services\EmailTemplateService;
use Exception;

class SendContractCreatedEmail
{
    public function __construct(
        private EmailTemplateService $emailService,
    ) {
    }

    public function handle(ContractCreated $event): void
    {
        $contract = $event->contract;
        $contract->load(['client', 'creator', 'contractType']);

        if (!$contract->client || !$contract->client->email) {
            return;
        }

        if (isEmailTemplateEnabled('New Contract', createdBy())) {
            $variables = [
                '{client_name}' => $contract->client->name,
                '{contract_id}' => $contract->contract_id,
                '{contract_subject}' => $contract->subject,
                '{contract_description}' => $contract->description ?? '-',
                '{contract_value}' => number_format($contract->contract_value, 2),
                '{currency}' => $contract->currency,
                '{contract_type}' => $contract->contractType->name ?? '-',
                '{start_date}' => $contract->start_date ? date('M d, Y', strtotime($contract->start_date)) : '-',
                '{end_date}' => $contract->end_date ? date('M d, Y', strtotime($contract->end_date)) : '-',
                '{status}' => ucfirst($contract->status),
                '{creator_name}' => $contract->creator->name ?? 'System',
                '{app_name}' => config('app.name'),
            ];

            try {
                $userLanguage = auth()->user()?->lang ?? 'en';

                $this->emailService->sendTemplateEmailWithLanguage(
                    templateName: 'New Contract',
                    variables: $variables,
                    toEmail: $contract->client->email,
                    toName: $contract->client->name,
                    language: $userLanguage
                );
                
                $contract->update(['status' => 'sent', 'sent_at' => now()]);
                session()->flash('success', 'Contract email sent successfully');
            } catch (Exception $e) {
                \Log::error('Contract email failed: ' . $e->getMessage());
                session()->flash('error', 'Failed to send contract email');
            }
        }
    }
}
