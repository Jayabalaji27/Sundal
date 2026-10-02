<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Traits\HasPermissionChecks;

class TodoController extends Controller
{
    use HasPermissionChecks;

    public function index(Request $request)
    {
        $this->authorizePermission('todo_view_any');
        $currentWorkspace = Auth::user()->currentWorkspace;
        
        if (!$currentWorkspace) {
            return redirect()->route('dashboard');
        }

        $perPage = $request->get('per_page', 12);
        $search = $request->get('search');
        $status = $request->get('status');
        $priority = $request->get('priority');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query = Todo::where('workspace_id', $currentWorkspace->id)
            ->where(function($query) {
                $query->where('created_by', Auth::user()->id)
                    ->orWhereHas('members', function($q) {
                        $q->where('user_id', Auth::user()->id);
                    });
            })
            ->with(['creator', 'members', 'comments.user', 'attachments.uploadedBy']);

        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%')
                  ->orWhereHas('creator', function($creatorQuery) use ($search) {
                      $creatorQuery->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        // Apply status filter
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        // Apply priority filter
        if ($priority && $priority !== 'all') {
            $query->where('priority', $priority);
        }

        // Apply sorting
        $allowedSortFields = ['title', 'priority', 'status', 'due_date', 'created_at'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $todos = $query->paginate($perPage)->withQueryString();

        // Transform member avatars
        $todos->getCollection()->transform(function ($todo) {
            $todo->members->transform(function ($member) {
                $member->avatar = check_file($member->avatar)
                    ? get_file($member->avatar)
                    : get_file('avatars/avatar.png');
                return $member;
            });
            return $todo;
        });

        $workspaceMembers = User::select('users.*')
            ->join('workspace_members', 'workspace_members.user_id', '=', 'users.id')
            ->where('workspace_members.workspace_id', $currentWorkspace->id)
            ->where('users.id', '!=', Auth::user()->id)
            ->get();

        return Inertia::render('todos/Index', [
            'todos' => $todos,
            'workspaceMembers' => $workspaceMembers,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'priority' => $priority,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'per_page' => $perPage,
                'view' => $request->get('view', 'grid'),
            ],
        ]);
    }

    public function show(Todo $todo)
    {
        $this->authorizePermission('todo_view');
        $currentWorkspace = Auth::user()->currentWorkspace;
        
        if ($todo->workspace_id !== $currentWorkspace->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $todo->load(['creator', 'members', 'comments.user', 'attachments.uploadedBy']);
        
        $todo->comments->each(function ($comment) {
            $comment->can_update = false;
            $comment->can_delete = false;
            if ($comment->user) {
                $comment->user->avatar = check_file($comment->user->avatar)
                    ? get_file($comment->user->avatar)
                    : get_file('avatars/avatar.png');
            }
        });
        
        $todo->can_create_comment = false;

        foreach ($todo->attachments as $attachment) {
            $attachment->file_url = asset('storage/media/' . $attachment->file);
        }

        return response()->json(['todo' => $todo]);
    }

    public function edit(Todo $todo)
    {
        $this->authorizePermission('todo_update');
        $currentWorkspace = Auth::user()->currentWorkspace;
        
        if ($todo->workspace_id !== $currentWorkspace->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $todo->load(['creator', 'members', 'comments.user', 'attachments.uploadedBy']);
        
        $todo->comments->each(function ($comment) {
            $comment->can_update = $comment->user_id === Auth::id();
            $comment->can_delete = $comment->user_id === Auth::id();
            if ($comment->user) {
                $comment->user->avatar = check_file($comment->user->avatar)
                    ? get_file($comment->user->avatar)
                    : get_file('avatars/avatar.png');
            }
        });

        foreach ($todo->attachments as $attachment) {
            $attachment->file_url = asset('storage/media/' . $attachment->file);
        }

        return response()->json(['todo' => $todo]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('todo_create');
        
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high',
            'status' => 'required|in:pending,in_progress,completed,overdue',
            'due_date' => 'required|date',
            'members' => 'nullable|array',
        ];
        
        // Conditional validation based on status
        if ($request->status === 'overdue') {
            $rules['due_date'] .= '|before:today';
        } elseif (!in_array($request->status, ['completed'])) {
            $rules['due_date'] .= '|after_or_equal:today';
        }
        
        $request->validate($rules);

        $currentWorkspace = Auth::user()->currentWorkspace;
        
        if (!$currentWorkspace) {
            return response()->json(['error' => 'No workspace selected'], 400);
        }

        $todo = Todo::create([
            'workspace_id' => $currentWorkspace->id,
            'created_by' => Auth::user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'priority' => $request->priority,
            'status' => $request->status,
            'due_date' => $request->due_date,
            'completed_at' => $request->status === 'completed' ? now() : null,
        ]);

        if ($request->members) {
            $todo->members()->sync($request->members);
        }

        // Fire event for email notification
        if (!config('app.is_demo', true)) {
            event(new \App\Events\TodoCreated($todo));
        }

        // Check if there was an email warning
        if (session()->has('email_warning')) {
            return redirect()->route('todos.index')->with('warning', session()->get('email_warning'));
        }

        return redirect()->route('todos.index')->with('success', 'ToDo created successfully!');
    }

    public function update(Request $request, Todo $todo)
    {
        $this->authorizePermission('todo_update');
        
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high',
            'status' => 'required|in:pending,in_progress,completed,overdue',
            'due_date' => 'required|date',
            'members' => 'nullable|array',
        ];
        
        // Conditional validation based on status
        if ($request->status === 'overdue') {
            $rules['due_date'] .= '|before:today';
        } elseif (!in_array($request->status, ['completed'])) {
            $rules['due_date'] .= '|after_or_equal:today';
        }
        
        $request->validate($rules);

        $currentWorkspace = Auth::user()->currentWorkspace;
        
        if ($todo->workspace_id !== $currentWorkspace->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $oldStatus = $todo->status;

        $todo->update([
            'title' => $request->title,
            'description' => $request->description,
            'priority' => $request->priority,
            'status' => $request->status,
            'due_date' => $request->due_date,
            'completed_at' => $request->status === 'completed' ? now() : null,
        ]);

        if ($request->has('members')) {
            $todo->members()->sync($request->members);
        }

        // Fire event only when status changes
        if ($oldStatus !== $request->status && !config('app.is_demo', true)) {
            event(new \App\Events\TodoStatusUpdated($todo, $oldStatus));
        }

        return redirect()->route('todos.index')->with('success', 'ToDo updated successfully!');
    }

    public function destroy(Todo $todo)
    {
        $this->authorizePermission('todo_delete');
        $currentWorkspace = Auth::user()->currentWorkspace;
        
        if ($todo->workspace_id !== $currentWorkspace->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $todo->delete();

        return redirect()->route('todos.index')->with('success', 'ToDo deleted successfully!');
    }

    public function updateStatus(Request $request, Todo $todo)
    {
        $this->authorizePermission('todo_status_update');
        $request->validate([
            'status' => 'required|in:pending,in_progress,completed',
        ]);

        $todo->update([
            'status' => $request->status,
            'completed_at' => $request->status === 'completed' ? now() : null,
        ]);

        return redirect()->route('todos.index')->with('success', 'Status updated successfully!');
    }

    public function updateMembers(Request $request, Todo $todo)
    {
        $this->authorizePermission('todo_manage_members');
        $currentWorkspace = Auth::user()->currentWorkspace;
        
        if ($todo->workspace_id !== $currentWorkspace->id || $todo->created_by !== Auth::user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'members' => 'nullable|array',
        ]);

        $todo->members()->sync($request->members ?? []);

        return redirect()->route('todos.index')->with('success', 'Members updated successfully!');
    }
}
