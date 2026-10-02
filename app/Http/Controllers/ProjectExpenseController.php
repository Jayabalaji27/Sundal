<?php

namespace App\Http\Controllers;

use App\Models\ProjectExpense;
use App\Models\Project;
use App\Models\BudgetCategory;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectExpenseController extends Controller
{
    use HasPermissionChecks;
    public function index(Request $request)
    {
        $this->authorizePermission('expense_view_any');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        $userWorkspaceRole = $workspace->getMemberRole($user);

        $query = ProjectExpense::with(['project', 'budgetCategory', 'submitter', 'task'])
            ->whereHas('project', function ($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            });

        // Members can only see their own expenses
        if ($userWorkspaceRole === 'member') {
            $query->where('submitted_by', $user->id);
        } elseif ($userWorkspaceRole === 'manager') {
            // Managers see expenses from their assigned projects
            $query->whereHas('project', function ($q) use ($user) {
                $q->where(function ($projectQuery) use ($user) {
                    $projectQuery->whereHas('members', function ($memberQuery) use ($user) {
                        $memberQuery->where('user_id', $user->id);
                    })
                        ->orWhere('created_by', $user->id);
                });
            });
        }

        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->category_id) {
            $query->where('budget_category_id', $request->category_id);
        }

        if ($request->status) {
            $query->where('project_expenses.status', $request->status);
        }

        if ($request->submitted_by) {
            $query->where('submitted_by', $request->submitted_by);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
                    ->orWhere('vendor', 'like', '%' . $request->search . '%')
                    ->orWhereHas('submitter', function ($subQ) use ($request) {
                        $subQ->where('name', 'like', '%' . $request->search . '%');
                    })
                    ->orWhereHas('project', function ($projQ) use ($request) {
                        $projQ->where('title', 'like', '%' . $request->search . '%');
                    });
            });
        }

        // Add sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        // Validate sort fields
        $allowedSortFields = ['created_at', 'amount', 'expense_date', 'title', 'status', 'project.title', 'submitter.name'];
        if ($sortBy === 'project.title') {
            $query->join('projects', 'project_expenses.project_id', '=', 'projects.id')
                  ->orderBy('projects.title', $sortOrder)
                  ->select('project_expenses.*');
        } elseif ($sortBy === 'submitter.name') {
            $query->join('users', 'project_expenses.submitted_by', '=', 'users.id')
                  ->orderBy('users.name', $sortOrder)
                  ->select('project_expenses.*');
        } elseif (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->latest();
        }

        $perPage = $request->get('per_page', 12);
        $expenses = $query->paginate($perPage);

        // expense.submitter.avatar
        $expenses->each(function ($expense) {
            $expense->submitter->avatar = check_file($expense->submitter->avatar) ? get_file($expense->submitter->avatar) : get_file('avatars/avatar.png');
        });

        $userWorkspaceRole = $workspace->getMemberRole($user);

        // Apply access control to projects dropdown
        $projectsQuery = Project::with(['budget.categories'])->forWorkspace($workspace->id);

        if (in_array($userWorkspaceRole, ['member', 'manager'])) {
            // Members and managers only see projects they're assigned to
            $projectsQuery->where(function ($q) use ($user) {
                $q->whereHas('members', function ($memberQuery) use ($user) {
                    $memberQuery->where('user_id', $user->id);
                })
                    ->orWhere('created_by', $user->id);
            });
        } elseif ($userWorkspaceRole === 'client') {
            // Clients see projects they're assigned to as clients
            $projectsQuery->where(function ($q) use ($user) {
                $q->whereHas('clients', function ($clientQuery) use ($user) {
                    $clientQuery->where('user_id', $user->id);
                })
                    ->orWhere('created_by', $user->id);
            });
        }

        $projects = $projectsQuery->get();
        $categories = BudgetCategory::whereHas('projectBudget', function ($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id);
        })->get(['id', 'name', 'color']);

        $members = $workspace->users()->get(['users.id', 'users.name', 'users.avatar']);

        return Inertia::render('expenses/Index', [
            'expenses' => $expenses,
            'projects' => $projects,
            'categories' => $categories,
            'members' => $members,
            'filters' => $request->only(['project_id', 'category_id', 'status', 'submitted_by', 'search', 'per_page', 'sort_by', 'sort_order', 'view']),
            'project_name' => $request->project_name,
            'userWorkspaceRole' => $userWorkspaceRole,
            'permissions' => [
                'create' => $this->checkPermission('expense_create'),
                'update' => $this->checkPermission('expense_update'),
                'delete' => $this->checkPermission('expense_delete'),
                'view' => $this->checkPermission('expense_view'),
            ]
        ]);
    }

    public function create()
    {
        return redirect()->route('expenses.index');
    }

    public function show(ProjectExpense $expense)
    {
        $this->authorizePermission('expense_view');

        $expense->load([
            'project',
            'budgetCategory',
            'submitter',
            'task',
            'approvals.approver'
        ]);

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        $projectsQuery = Project::with(['budget.categories'])->forWorkspace($workspace->id);
        $userWorkspaceRole = $workspace->getMemberRole($user);

        if (in_array($userWorkspaceRole, ['member', 'manager'])) {
            $projectsQuery->where(function ($q) use ($user) {
                $q->whereHas('members', function ($memberQuery) use ($user) {
                    $memberQuery->where('user_id', $user->id);
                })->orWhere('created_by', $user->id);
            });
        }

        $projects = $projectsQuery->get();

        return Inertia::render('expenses/Show', [
            'expense' => $expense,
            'projects' => $projects,
            'permissions' => [
                'update' => $this->checkPermission('expense_update'),
                'delete' => $this->checkPermission('expense_delete'),
            ]
        ]);
    }

    public function edit(ProjectExpense $expense)
    {
        return redirect()->route('expenses.index');
    }

    public function store(Request $request)
    {
        $this->authorizePermission('expense_create');

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'budget_category_id' => 'nullable|exists:budget_categories,id',
            'task_id' => 'nullable|exists:tasks,id',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date|before_or_equal:today',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $expense = ProjectExpense::create([
            ...$validated,
            'submitted_by' => auth()->id(),
            'status' => 'pending',
            'currency' => 'USD'
        ]);

        // Load relationships for email
        $expense->load(['project.clients', 'budgetCategory', 'submitter']);

        // Trigger expense notification event
        if (!config('app.is_demo', true)) {
            event(new \App\Events\ExpenseCreated($expense));
        }

        return redirect()->route('expenses.index')->with('success', __('Expense created successfully!'));
    }

    public function update(Request $request, ProjectExpense $expense)
    {
        $this->authorizePermission('expense_update');

        $validated = $request->validate([
            'budget_category_id' => 'nullable|exists:budget_categories,id',
            'task_id' => 'nullable|exists:tasks,id',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date|before_or_equal:today',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        // If expense was requires_info, change back to pending when updated
        if ($expense->status === 'requires_info') {
            $validated['status'] = 'pending';
        }

        $expense->update($validated);

        // Load relationships for email
        $expense->load(['project.clients', 'budgetCategory', 'submitter']);

        // Trigger expense notification event
        if (!config('app.is_demo', true)) {
            event(new \App\Events\ExpenseCreated($expense));
        }

        return redirect()->route('expenses.index')->with('success', __('Expense updated successfully!'));
    }

    public function destroy(ProjectExpense $expense)
    {
        $this->authorizePermission('expense_delete');

        $expense->delete();
        return back()->with('success', __('Expense deleted successfully!'));
    }

    public function duplicate(ProjectExpense $expense)
    {
        $newExpense = $expense->replicate();
        $newExpense->title = $expense->title . ' (Copy)';
        $newExpense->expense_date = now()->toDateString();
        $newExpense->status = 'pending';
        $newExpense->submitted_by = auth()->id();
        $newExpense->save();

        return redirect()->route('expenses.index')->with('success', __('Expense duplicated successfully!'));
    }

    public function getProjectTasks(Project $project)
    {
        $tasks = $project->tasks()
            ->with(['taskStage:id,name,color'])
            ->select('id', 'title', 'task_stage_id')
            ->get();

        return response()->json($tasks);
    }
}