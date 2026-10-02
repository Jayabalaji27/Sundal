<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = EmailTemplate::with('emailTemplateLangs');
        
        // Search functionality
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('from', 'like', '%' . $request->search . '%');
        }
        
        // Sorting
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);
        
        // Pagination
        $perPage = $request->get('per_page', 10);
        $templates = $query->paginate($perPage);
        
        return Inertia::render('email-templates/index', [
            'templates' => $templates,
            'filters' => $request->only(['search', 'sort_field', 'sort_direction', 'per_page'])
        ]);
    }

    public function show(EmailTemplate $emailTemplate)
    {
        $template = $emailTemplate->load('emailTemplateLangs');
        $languages = json_decode(file_get_contents(resource_path('lang/language.json')), true);
        
        // Template-specific variables
        $variables = [];
        
        if ($template->name === 'User Created') {
            $variables = [
                '{app_url}' => 'App URL',
                '{user_name}' => 'User Name',
                '{user_email}' => 'User Email',
                '{user_password}' => 'User Password',
                '{user_type}' => 'User Type'
            ];
        } elseif ($template->name === 'Workspace Invitation') {
            $variables = [
                '{workspace_name}' => 'Name of the workspace',
                '{invited_by_name}' => 'Name of the person who sent the invitation',
                '{invitee_email}' => 'Email of the invited user',
                '{role}' => 'Role assigned to the invitee',
                '{invitation_link}' => 'Link to accept the workspace invitation',
                '{company_name}' => 'Company name',
                '{app_name}' => 'Application name'
            ];
        } elseif ($template->name === 'Project Assignment') {
            $variables = [
                '{project_name}' => 'Name of the project',
                '{assigned_user_name}' => 'Name of the assigned user',
                '{assigned_by_name}' => 'Name of the person who assigned',
                '{role}' => 'Role in the project',
                '{project_description}' => 'Project description',
                '{company_name}' => 'Company name'
            ];
        } elseif ($template->name === 'Task Assignment') {
            $variables = [
                '{task_title}' => 'Title of the task',
                '{project_name}' => 'Name of the project',
                '{assigned_user_name}' => 'Name of the assigned user',
                '{assigned_by_name}' => 'Name of the person who assigned',
                '{task_description}' => 'Task description',
                '{task_priority}' => 'Task priority level',
                '{start_date}' => 'Task start date',
                '{end_date}' => 'Task end date',
                '{company_name}' => 'Company name'
            ];
        } elseif ($template->name === 'Bug Assignment') {
            $variables = [
                '{bug_title}' => 'Title of the bug',
                '{project_name}' => 'Name of the project',
                '{assigned_user_name}' => 'Name of the assigned user',
                '{assigned_by_name}' => 'Name of the person who assigned',
                '{bug_description}' => 'Bug description',
                '{bug_priority}' => 'Bug priority level',
                '{bug_severity}' => 'Bug severity level',
                '{start_date}' => 'Bug creation date',
                '{end_date}' => 'Bug due date',
                '{company_name}' => 'Company name'
            ];
        } elseif ($template->name === 'Expense Notification') {
            $variables = [
                '{expense_title}' => 'Title of the expense',
                '{project_name}' => 'Name of the project',
                '{expense_amount}' => 'Expense amount',
                '{expense_category}' => 'Expense category',
                '{expense_date}' => 'Expense date',
                '{created_by_name}' => 'Name of the person who created expense',
                '{expense_description}' => 'Expense description',
                '{company_name}' => 'Company name',
                '{app_name}' => 'Application name'
            ];
        } elseif ($template->name === 'Invoice Notification') {
            $variables = [
                '{client_name}' => 'Name of the client',
                '{invoice_number}' => 'Invoice number',
                '{invoice_title}' => 'Invoice title',
                '{project_name}' => 'Name of the project',
                '{total_amount}' => 'Total invoice amount',
                '{currency}' => 'Currency',
                '{due_date}' => 'Invoice due date',
                '{workspace_name}' => 'Name of the workspace',
                '{creator_name}' => 'Name of the invoice creator',
                '{company_name}' => 'Company name',
                '{app_name}' => 'Application name'
            ];
        } elseif ($template->name === 'New Contract') {
            $variables = [
                '{client_name}' => 'Name of the client',
                '{contract_id}' => 'Contract ID',
                '{contract_subject}' => 'Contract subject',
                '{contract_type}' => 'Contract type',
                '{contract_description}' => 'Contract description',
                '{contract_value}' => 'Contract value',
                '{currency}' => 'Currency',
                '{start_date}' => 'Contract start date',
                '{end_date}' => 'Contract end date',
                '{status}' => 'Contract status',
                '{creator_name}' => 'Name of the contract creator',
                '{app_name}' => 'Application name'
            ];
        } elseif ($template->name === 'Zoom Meeting Notification') {
            $variables = [
                '{member_name}' => 'Name of the meeting member',
                '{meeting_title}' => 'Title of the meeting',
                '{project_name}' => 'Name of the project',
                '{start_time}' => 'Meeting start time',
                '{duration}' => 'Meeting duration in minutes',
                '{organizer_name}' => 'Name of the meeting organizer',
                '{meeting_description}' => 'Meeting description',
                '{join_url}' => 'Zoom meeting join URL',
                '{app_name}' => 'Application name'
            ];
        } elseif ($template->name === 'Google Meeting Notification') {
            $variables = [
                '{member_name}' => 'Name of the meeting member',
                '{meeting_title}' => 'Title of the meeting',
                '{project_name}' => 'Name of the project',
                '{start_time}' => 'Meeting start time',
                '{duration}' => 'Meeting duration in minutes',
                '{organizer_name}' => 'Name of the meeting organizer',
                '{meeting_description}' => 'Meeting description',
                '{join_url}' => 'Google Meet join URL',
                '{app_name}' => 'Application name'
            ];
        } elseif ($template->name === 'Todo Created') {
            $variables = [
                '{todo_title}' => 'Title of the todo',
                '{todo_description}' => 'Todo description',
                '{todo_priority}' => 'Todo priority (Low/Medium/High)',
                '{todo_status}' => 'Todo status (Pending/In Progress/Completed)',
                '{due_date}' => 'Todo due date',
                '{workspace_name}' => 'Name of the workspace',
                '{created_by_name}' => 'Name of the person who created the todo',
                '{member_name}' => 'Name of the member',
                '{app_name}' => 'Application name',
                '{app_url}' => 'Application URL'
            ];
        } elseif ($template->name === 'Todo Status Updated') {
            $variables = [
                '{todo_title}' => 'Title of the todo',
                '{todo_description}' => 'Todo description',
                '{todo_priority}' => 'Todo priority (Low/Medium/High)',
                '{old_status}' => 'Previous status of the todo',
                '{new_status}' => 'New status of the todo',
                '{due_date}' => 'Todo due date',
                '{workspace_name}' => 'Name of the workspace',
                '{updated_by_name}' => 'Name of the person who updated the todo',
                '{member_name}' => 'Name of the member',
                '{app_name}' => 'Application name',
                '{app_url}' => 'Application URL'
            ];
        } elseif ($template->name === 'Todo Comments Added') {
            $variables = [
                '{todo_title}' => 'Title of the todo',
                '{todo_priority}' => 'Todo priority (Low/Medium/High)',
                '{todo_status}' => 'Todo status (Pending/In Progress/Completed)',
                '{due_date}' => 'Todo due date',
                '{activity_type}' => 'Activity type (Comment/Attachment)',
                '{workspace_name}' => 'Name of the workspace',
                '{created_by_name}' => 'Name of the person who added comment/attachment',
                '{member_name}' => 'Name of the member',
                '{app_name}' => 'Application name'
            ];
        }

        return Inertia::render('email-templates/show', [
            'template' => $template,
            'languages' => $languages,
            'variables' => $variables
        ]);
    }

    public function updateSettings(EmailTemplate $emailTemplate, Request $request)
    {
        try {
            $request->validate([
                'from' => 'required|string|max:255'
            ]);

            $emailTemplate->update([
                'from' => $request->from
            ]);
            
            return redirect()->back()->with('success', __('Template settings updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update template settings: :error', ['error' => $e->getMessage()]));
        }
    }

    public function updateContent(EmailTemplate $emailTemplate, Request $request)
    {
        try {
            $request->validate([
                'lang' => 'required|string|max:10',
                'subject' => 'required|string|max:255',
                'content' => 'required|string'
            ]);

            $emailTemplate->emailTemplateLangs()
                ->where('lang', $request->lang)
                ->update([
                    'subject' => $request->subject,
                    'content' => $request->content
                ]);
            
            return redirect()->back()->with('success', __('Email content updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update email content: :error', ['error' => $e->getMessage()]));
        }
    }
}
