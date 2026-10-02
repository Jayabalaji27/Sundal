<?php

namespace App\Providers;


use App\Events\UserCreated;
use App\Events\WorkspaceInvited;
use App\Events\ProjectMemberAssigned;
use App\Events\TaskAssigned;
use App\Events\BugAssigned;
use App\Events\ExpenseCreated;
use App\Events\InvoiceCreated;
use App\Events\ProjectCreated;
use App\Events\TaskCreated;
use App\Events\TaskStageUpdated;
use App\Events\TaskCommentAdded;
use App\Events\MilestoneCreated;
use App\Events\MilestoneStatusUpdated;
use App\Events\InvoiceStatusUpdated;
use App\Events\UserInvited;
use App\Events\ExpenseApprovalRequested;
use App\Events\BudgetCreated;
use App\Events\ContractCreated;
use App\Events\ZoomMeetingCreated;
use App\Events\GoogleMeetingCreated;
use App\Events\TodoCreated;
use App\Events\TodoStatusUpdated;
use App\Events\TodoCommentAdded;
use App\Listeners\SendUserCreatedEmail;
use App\Listeners\SendContractCreatedEmail;
use App\Listeners\SendWorkspaceInvitationEmail;
use App\Listeners\SendProjectAssignmentEmail;
use App\Listeners\SendTaskAssignmentEmail;
use App\Listeners\SendBugAssignmentEmail;
use App\Listeners\SendExpenseNotificationEmail;
use App\Listeners\SendInvoiceNotificationEmail;
use App\Listeners\SendZoomMeetingNotificationEmail;
use App\Listeners\SendGoogleMeetingNotificationEmail;
use App\Listeners\SendTodoCreatedEmail;
use App\Listeners\SendTodoStatusUpdatedEmail;
use App\Listeners\SendTodoCommentEmail;
use App\Listeners\SendNewProjectSlackNotification;
use App\Listeners\SendNewTaskSlackNotification;
use App\Listeners\SendTaskStageUpdateSlackNotification;
use App\Listeners\SendTaskCommentSlackNotification;
use App\Listeners\SendNewMilestoneSlackNotification;
use App\Listeners\SendMilestoneStatusUpdateSlackNotification;
use App\Listeners\SendNewInvoiceSlackNotification;
use App\Listeners\SendInvoiceStatusUpdateSlackNotification;
use App\Listeners\SendUserInvitedSlackNotification;
use App\Listeners\SendExpenseApprovalSlackNotification;
use App\Listeners\SendNewBudgetSlackNotification;
use App\Listeners\SendNewProjectTelegramNotification;
use App\Listeners\SendNewTaskTelegramNotification;
use App\Listeners\SendTaskStageUpdateTelegramNotification;
use App\Listeners\SendNewMilestoneTelegramNotification;
use App\Listeners\SendMilestoneStatusUpdateTelegramNotification;
use App\Listeners\SendTaskCommentTelegramNotification;
use App\Listeners\SendNewInvoiceTelegramNotification;
use App\Listeners\SendInvoiceStatusUpdateTelegramNotification;
use App\Listeners\SendExpenseApprovalTelegramNotification;
use App\Listeners\SendNewBudgetTelegramNotification;
use App\Listeners\WebhookUserCreatedListener;
use App\Listeners\WebhookProjectCreateListener;
use App\Listeners\WebhookTaskCreateListener;
use App\Listeners\WebhookInvoiceCreateListener;
use App\Listeners\WebhookBudgetCreateListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        \Illuminate\Auth\Events\Registered::class => [
            \Illuminate\Auth\Listeners\SendEmailVerificationNotification::class,
        ],
        UserCreated::class => [
            SendUserCreatedEmail::class,
        ],
        WorkspaceInvited::class => [
            SendWorkspaceInvitationEmail::class,
            WebhookUserCreatedListener::class,
        ],
        ProjectMemberAssigned::class => [
            SendProjectAssignmentEmail::class,
        ],
        TaskAssigned::class => [
            SendTaskAssignmentEmail::class,
        ],
        BugAssigned::class => [
            SendBugAssignmentEmail::class,
        ],
        ExpenseCreated::class => [
            SendExpenseNotificationEmail::class,
        ],
        InvoiceCreated::class => [
            SendInvoiceNotificationEmail::class,
            SendNewInvoiceSlackNotification::class,
            SendNewInvoiceTelegramNotification::class,
            WebhookInvoiceCreateListener::class,
        ],
        ProjectCreated::class => [
            SendNewProjectSlackNotification::class,
            SendNewProjectTelegramNotification::class,
            WebhookProjectCreateListener::class,
        ],
        TaskCreated::class => [
            SendNewTaskSlackNotification::class,
            SendNewTaskTelegramNotification::class,
            WebhookTaskCreateListener::class,
        ],
        TaskStageUpdated::class => [
            SendTaskStageUpdateSlackNotification::class,
            SendTaskStageUpdateTelegramNotification::class,
        ],
        TaskCommentAdded::class => [
            SendTaskCommentSlackNotification::class,
            SendTaskCommentTelegramNotification::class,
        ],
        MilestoneCreated::class => [
            SendNewMilestoneSlackNotification::class,
            SendNewMilestoneTelegramNotification::class,
        ],
        MilestoneStatusUpdated::class => [
            SendMilestoneStatusUpdateSlackNotification::class,
            SendMilestoneStatusUpdateTelegramNotification::class,
        ],
        InvoiceStatusUpdated::class => [
            SendInvoiceStatusUpdateSlackNotification::class,
            SendInvoiceStatusUpdateTelegramNotification::class,
        ],
        ExpenseApprovalRequested::class => [
            SendExpenseApprovalSlackNotification::class,
            SendExpenseApprovalTelegramNotification::class,
        ],
        BudgetCreated::class => [
            SendNewBudgetSlackNotification::class,
            SendNewBudgetTelegramNotification::class,
            WebhookBudgetCreateListener::class,
        ],
        ContractCreated::class => [
            SendContractCreatedEmail::class,
        ],
        ZoomMeetingCreated::class => [
            SendZoomMeetingNotificationEmail::class,
        ],
        GoogleMeetingCreated::class => [
            SendGoogleMeetingNotificationEmail::class,
        ],
        TodoCreated::class => [
            SendTodoCreatedEmail::class,
        ],
        TodoStatusUpdated::class => [
            SendTodoStatusUpdatedEmail::class,
        ],
        TodoCommentAdded::class => [
            SendTodoCommentEmail::class,
        ],

    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Disable Laravel's event discovery.
        $this->disableEventDiscovery();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}