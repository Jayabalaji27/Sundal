<?php

namespace App\Http\Controllers;

use App\Models\NotificationTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = NotificationTemplate::with('notificationTemplateLangs');
        
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('type', 'like', '%' . $request->search . '%');
        }
        
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);
        
        $perPage = $request->get('per_page', 10);
        $templates = $query->paginate($perPage);
        
        return Inertia::render('notification-templates/index', [
            'templates' => $templates,
            'filters' => $request->only(['search', 'sort_field', 'sort_direction', 'per_page', 'type'])
        ]);
    }

    public function show(NotificationTemplate $notificationTemplate)
    {
        $template = $notificationTemplate->load('notificationTemplateLangs');
        $languages = json_decode(file_get_contents(resource_path('lang/language.json')), true);
        
        $variables = [];
        
        if ($template->name === 'New Project') {
            $variables = [
                '{project_name}' => 'Project Name',
                '{created_by}' => 'Project Creator Name',
                '{start_date}' => 'Project Start Date',
                '{end_date}' => 'Project End Date'
            ];
        } elseif ($template->name === 'New Task') {
            $variables = [
                '{task_title}' => 'Task Title',
                '{project_name}' => 'Project Name',
                '{due_date}' => 'Task Due Date'
            ];
        } elseif ($template->name === 'Task Stage Updated') {
            $variables = [
                '{task_title}' => 'Task Title',
                '{old_stage}' => 'Previous Stage',
                '{new_stage}' => 'New Stage',
                '{updated_by}' => 'Updated By User'
            ];
        } elseif ($template->name === 'New Milestone') {
            $variables = [
                '{milestone_title}' => 'Milestone Title',
                '{project_name}' => 'Project Name',
                '{due_date}' => 'Milestone Due Date'
            ];
        } elseif ($template->name === 'Milestone Status Updated') {
            $variables = [
                '{milestone_title}' => 'Milestone Title',
                '{status}' => 'New Status',
                '{updated_by}' => 'Updated By User'
            ];
        } elseif ($template->name === 'New Task Comment') {
            $variables = [
                '{task_title}' => 'Task Title',
                '{commenter_name}' => 'Comment Author',
                '{comment_text}' => 'Comment Content'
            ];
        } elseif ($template->name === 'New Invoice') {
            $variables = [
                '{invoice_number}' => 'Invoice Number',
                '{client_name}' => 'Client Name',
                '{amount}' => 'Invoice Amount',
                '{due_date}' => 'Invoice Due Date'
            ];
        } elseif ($template->name === 'Invoice Status Updated') {
            $variables = [
                '{invoice_number}' => 'Invoice Number',
                '{status}' => 'New Status',
                '{updated_by}' => 'Updated By User'
            ];
        } elseif ($template->name === 'Expense Approval') {
            $variables = [
                '{expense_title}' => 'Title of the expense',
                '{expense_amount}' => 'Amount of the expense',
                '{submitted_by}' => 'Name of the person who submitted the expense',
                '{project_name}' => 'Name of the associated project'
            ];
        } elseif ($template->name === 'New Budget') {
            $variables = [
                '{project_name}' => 'Name of the project',
                '{total_budget}' => 'Total budget amount',
                '{period_type}' => 'Budget period type (monthly, yearly, etc.)'
            ];
        }

        return Inertia::render('notification-templates/show', [
            'template' => $template,
            'languages' => $languages,
            'variables' => $variables
        ]);
    }

    public function updateSettings(NotificationTemplate $notificationTemplate, Request $request)
    {
        try {
            $request->validate([
                'type' => 'required|string|max:255'
            ]);

            $notificationTemplate->update([
                'type' => $request->type
            ]);
            
            return redirect()->back()->with('success', __('Template settings updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update template settings: :error', ['error' => $e->getMessage()]));
        }
    }

    public function updateContent(NotificationTemplate $notificationTemplate, Request $request)
    {
        try {
            $request->validate([
                'lang' => 'required|string|max:10',
                'title' => 'required|string|max:255',
                'content' => 'required|string'
            ]);

            $notificationTemplate->notificationTemplateLangs()
                ->where('lang', $request->lang)
                ->update([
                    'title' => $request->title,
                    'content' => $request->content
                ]);
            
            return redirect()->back()->with('success', __('Notification content updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update notification content: :error', ['error' => $e->getMessage()]));
        }
    }
}