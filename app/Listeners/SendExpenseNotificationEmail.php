<?php

namespace App\Listeners;

use App\Events\ExpenseCreated;
use App\Services\EmailTemplateService;
use Exception;

class SendExpenseNotificationEmail
{
    public function __construct(
        private EmailTemplateService $emailService,
    ) {
    }

    public function handle(ExpenseCreated $event): void
    {
        $expense = $event->expense;
        $project = $expense->project;
        $createdBy = $expense->submitter;

        // Get project clients
        $clients = $project->clients;

        if ($clients->isEmpty()) {
            return;
        }

        if (isEmailTemplateEnabled('Expense Notification', createdBy())) {
            // Prepare email variables
            $variables = [
                '{expense_title}' => $expense->title ?? '-',
                '{project_name}' => $project->title ?? '-',
                '{expense_amount}' => number_format($expense->amount, 2) ?? '-',
                '{expense_category}' => $expense->budgetCategory->name ?? '-',
                '{expense_date}' => $expense->expense_date ? $expense->expense_date->format('M d, Y') : '-',
                '{created_by_name}' => $createdBy->name ?? '-',
                '{expense_description}' => $expense->description ?? '-',
                '{company_name}' => config('app.name'),
                '{app_name}' => config('app.name'),
            ];

            foreach ($clients as $client) {
                try {
                    session()->forget('email_error');

                    $userLanguage = (auth()->user() && auth()->user()->lang) ? auth()->user()->lang : 'en';

                    $this->emailService->sendTemplateEmailWithLanguage(
                        templateName: 'Expense Notification',
                        variables: $variables,
                        toEmail: $client->email,
                        toName: $client->name,
                        language: $userLanguage
                    );
                } catch (Exception $e) {
                    $errorMessage = $e->getMessage();
                    if (!str_contains($errorMessage, 'Too many emails per second') &&
                        !str_contains($errorMessage, '550 5.7.0') &&
                        !str_contains($errorMessage, 'rate limit')) {
                        session()->flash('email_error', 'Failed to send expense notification email: ' . $errorMessage);
                    }
                }
            }
        }
    }
}