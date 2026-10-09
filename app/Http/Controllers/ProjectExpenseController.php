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

        // Members see only their own expenses, managers their assigned projects' (see the scope)
        $query = ProjectExpense::with(['project', 'budgetCategory', 'submitter', 'task'])
            ->visibleTo($user);

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
        // Creating happens in a dialog on the index page; ?create=1 opens it.
        return redirect()->route('expenses.index', ['create' => 1]);
    }

    public function show(ProjectExpense $expense)
    {
        $this->authorizePermission('expense_view');
        $this->authorizeVisible($expense);

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
        $this->authorizeVisible($expense);
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

        // Shared with the AI assistant.
        app(\App\Actions\Expenses\CreateExpense::class)->handle(auth()->user(), $validated);

        return redirect()->route('expenses.index')->with('success', __('Expense created successfully!'));
    }

    public function update(Request $request, ProjectExpense $expense)
    {
        $this->authorizePermission('expense_update');
        abort_unless($this->memberMayChange($expense), 403, __('You can only edit your own expenses that have not been approved yet.'));

        $validated = $request->validate([
            'budget_category_id' => 'nullable|exists:budget_categories,id',
            'task_id' => 'nullable|exists:tasks,id',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date|before_or_equal:today',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        // Shared with the AI assistant (a requires_info expense goes back to pending).
        app(\App\Actions\Expenses\UpdateExpense::class)->handle(auth()->user(), $expense, $validated);

        return redirect()->route('expenses.index')->with('success', __('Expense updated successfully!'));
    }

    public function destroy(ProjectExpense $expense)
    {
        $this->authorizePermission('expense_delete');
        abort_unless($this->memberMayChange($expense), 403, __('You can only delete your own expenses that have not been approved yet.'));

        app(\App\Actions\Expenses\DeleteExpense::class)->handle(auth()->user(), $expense);
        return back()->with('success', __('Expense deleted successfully!'));
    }

    public function duplicate(ProjectExpense $expense)
    {
        $this->authorizeVisible($expense);
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
        $user = auth()->user();
        abort_unless(Project::forWorkspace($user->current_workspace_id)->visibleTo($user)->whereKey($project->id)->exists(), 403);

        $tasks = $project->tasks()
            ->with(['taskStage:id,name,color'])
            ->select('id', 'title', 'task_stage_id')
            ->get();

        return response()->json($tasks);
    }

    /** Same rule as the expenses list, so a direct URL can't reach a record the list hides. */
    private function authorizeVisible(ProjectExpense $expense): void
    {
        abort_unless(ProjectExpense::visibleTo(auth()->user())->whereKey($expense->id)->exists(), 403);
    }

    /**
     * Members manage their own expenses only, and only until they're approved
     * (approved expenses count against the budget). Owners/managers aren't limited.
     */
    private function memberMayChange(ProjectExpense $expense): bool
    {
        return \App\Actions\Expenses\UpdateExpense::mayChange(auth()->user(), $expense);
    }
}
