<?php

namespace App\Http\Controllers;

use App\Models\TimesheetApproval;
use App\Models\Timesheet;
use App\Services\ApprovalQueue;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TimesheetApprovalController extends Controller
{
    public function __construct(private ApprovalQueue $queue)
    {
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        // Check if user is owner or manager
        $isOwner = $workspace->isOwner($user);
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        // Only owners and managers can access timesheet approvals
        if (!$isOwner && $userWorkspaceRole !== 'manager') {
            abort(403, 'Access denied. Only owners and managers can access timesheet approvals.');
        }
        
        // The inbox opens on what's waiting; "history" shows decided timesheets.
        $validStatuses = ['pending', 'history', 'approved', 'rejected', 'all'];
        $statusFilter = $request->status && in_array($request->status, $validStatuses) ? $request->status : 'pending';

        // Same queue the dashboard counts: whole workspace for the owner, managed projects for a manager.
        $query = $this->queue->timesheets($user)->with(['timesheet.user', 'timesheet.entries', 'approver']);

        if ($statusFilter === 'history') {
            $query->whereIn('timesheet_approvals.status', ['approved', 'rejected']);
        } elseif ($statusFilter !== 'all') {
            $query->where('timesheet_approvals.status', $statusFilter);
        }

        if ($request->search) {
            $searchTerm = trim($request->search);
            if (!empty($searchTerm)) {
                $query->whereHas('timesheet.user', function($q) use ($searchTerm) {
                    $q->where('name', 'like', '%' . $searchTerm . '%');
                });
            }
        }

        if ($request->from_date) {
            $fromDate = $request->validate(['from_date' => 'date'])['from_date'];
            $query->whereHas('timesheet', function($q) use ($fromDate) {
                $q->where('start_date', '>=', $fromDate);
            });
        }

        if ($request->to_date) {
            $toDate = $request->validate(['to_date' => 'date'])['to_date'];
            $query->whereHas('timesheet', function($q) use ($toDate) {
                $q->where('end_date', '<=', $toDate);
            });
        }

        // Handle sorting
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        // Validate sort fields
        $allowedSortFields = ['created_at', 'status', 'approved_at'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }
        
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }
        
        // Apply sorting - handle nested fields
        if ($sortField === 'user_name') {
            $query->join('timesheets', 'timesheet_approvals.timesheet_id', '=', 'timesheets.id')
                  ->join('users', 'timesheets.user_id', '=', 'users.id')
                  ->orderBy('users.name', $sortDirection)
                  ->select('timesheet_approvals.*');
        } elseif ($sortField === 'start_date') {
            $query->join('timesheets as ts', 'timesheet_approvals.timesheet_id', '=', 'ts.id')
                  ->orderBy('ts.start_date', $sortDirection)
                  ->select('timesheet_approvals.*');
        } elseif ($sortField === 'total_hours') {
            $query->join('timesheets as ts2', 'timesheet_approvals.timesheet_id', '=', 'ts2.id')
                  ->orderBy('ts2.total_hours', $sortDirection)
                  ->select('timesheet_approvals.*');
        } elseif ($sortField === 'billable_hours') {
            $query->join('timesheets as ts3', 'timesheet_approvals.timesheet_id', '=', 'ts3.id')
                  ->orderBy('ts3.billable_hours', $sortDirection)
                  ->select('timesheet_approvals.*');
        } else {
            $query->orderBy($sortField, $sortDirection);
        }

        $perPage = in_array($request->per_page, [12, 24, 36, 48]) ? $request->per_page : 12;
        $approvals = $query->paginate($perPage);
        $approvals->getCollection()->transform(function ($approval) {
            if ($approval->timesheet?->user) {
                $approval->timesheet->user->avatar = check_file($approval->timesheet->user->avatar)
                    ? get_file($approval->timesheet->user->avatar)
                    : get_file('avatars/avatar.png');
            }
            return $approval;
        });

        return Inertia::render('timesheets/Approvals', [
            'approvals' => $approvals,
            'filters' => ['status' => $statusFilter] + $request->only(['search', 'per_page', 'from_date', 'to_date', 'sort_field', 'sort_direction']),
            'userWorkspaceRole' => $isOwner ? 'owner' : $userWorkspaceRole,
            'approvalCounts' => $this->queue->counts($user),
        ]);
    }

    public function show(TimesheetApproval $approval)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $isOwner = $workspace->isOwner($user);
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        if (!$isOwner && $userWorkspaceRole !== 'manager') {
            abort(403, 'Access denied.');
        }
        abort_unless($this->queue->inTimesheetQueue($user, $approval), 403);
        
        $approval->load(['timesheet.user', 'timesheet.entries.project', 'timesheet.entries.task', 'approver']);
        
        return Inertia::render('timesheets/ApprovalDetails', [
            'approval' => $approval,
            'userWorkspaceRole' => $isOwner ? 'owner' : $userWorkspaceRole
        ]);
    }

    public function approve(Request $request, TimesheetApproval $approval)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        // Check if user is owner or manager
        $isOwner = $workspace->isOwner($user);
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        // Only owners and managers can approve timesheets
        if (!$isOwner && $userWorkspaceRole !== 'manager') {
            abort(403, 'Access denied. Only owners and managers can approve timesheets.');
        }
        
        // Check if approval is still pending
        if ($approval->status !== 'pending') {
            return back()->with('error', __('This approval has already been processed.'));
        }

        if ($this->isOwnTimesheet($approval, $user, $isOwner)) {
            return back()->with('error', __('You cannot approve or reject your own timesheet. The workspace owner reviews it.'));
        }
        abort_unless($this->queue->inTimesheetQueue($user, $approval), 403);
        
        $validated = $request->validate([
            'comments' => 'nullable|string'
        ]);

        try {
            // Shared with the AI assistant: records the decision and settles the timesheet.
            app(\App\Actions\Timesheets\DecideTimesheetApproval::class)->handle($user, $approval, 'approved', $validated['comments'] ?? null);

            return back()->with('success', __('Timesheet approved successfully!'));
        } catch (\Exception $e) {
            return back()->with('error', __('Failed to approve timesheet.'));
        }
    }

    public function reject(Request $request, TimesheetApproval $approval)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        // Check if user is owner or manager
        $isOwner = $workspace->isOwner($user);
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        // Only owners and managers can reject timesheets
        if (!$isOwner && $userWorkspaceRole !== 'manager') {
            abort(403, 'Access denied. Only owners and managers can reject timesheets.');
        }
        
        // Check if approval is still pending
        if ($approval->status !== 'pending') {
            return back()->with('error', __('This approval has already been processed.'));
        }

        if ($this->isOwnTimesheet($approval, $user, $isOwner)) {
            return back()->with('error', __('You cannot approve or reject your own timesheet. The workspace owner reviews it.'));
        }
        abort_unless($this->queue->inTimesheetQueue($user, $approval), 403);
        
        $validated = $request->validate([
            'comments' => 'required|string'
        ]);

        try {
            app(\App\Actions\Timesheets\DecideTimesheetApproval::class)->handle($user, $approval, 'rejected', $validated['comments']);

            return back()->with('success', __('Timesheet rejected successfully!'));
        } catch (\Exception $e) {
            return back()->with('error', __('Failed to reject timesheet.'));
        }
    }

    public function bulkApprove(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        // Check if user is owner or manager
        $isOwner = $workspace->isOwner($user);
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        // Only owners and managers can approve timesheets
        if (!$isOwner && $userWorkspaceRole !== 'manager') {
            abort(403, 'Access denied. Only owners and managers can approve timesheets.');
        }
        
        $validated = $request->validate([
            'approval_ids' => 'required|array',
            'approval_ids.*' => 'exists:timesheet_approvals,id',
            'comments' => 'nullable|string'
        ]);

        $approvals = $this->queue->timesheets($user)->with('timesheet')->whereIn('timesheet_approvals.id', $validated['approval_ids'])
            ->where('timesheet_approvals.status', 'pending')
            ->get()
            ->reject(fn ($approval) => $this->isOwnTimesheet($approval, $user, $isOwner));

        if ($approvals->isEmpty()) {
            return back()->with('error', __('No pending approvals found.'));
        }

        try {
            $decide = app(\App\Actions\Timesheets\DecideTimesheetApproval::class);
            foreach ($approvals as $approval) {
                $decide->handle($user, $approval, 'approved', $validated['comments'] ?? null);
            }

            return back()->with('success', __('Timesheets approved successfully!'));
        } catch (\Exception $e) {
            return back()->with('error', __('Failed to approve timesheets.'));
        }
    }

    public function bulkReject(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        // Check if user is owner or manager
        $isOwner = $workspace->isOwner($user);
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        // Only owners and managers can reject timesheets
        if (!$isOwner && $userWorkspaceRole !== 'manager') {
            abort(403, 'Access denied. Only owners and managers can reject timesheets.');
        }
        
        $validated = $request->validate([
            'approval_ids' => 'required|array',
            'approval_ids.*' => 'exists:timesheet_approvals,id',
            'comments' => 'required|string'
        ]);

        $approvals = $this->queue->timesheets($user)->with('timesheet')->whereIn('timesheet_approvals.id', $validated['approval_ids'])
            ->where('timesheet_approvals.status', 'pending')
            ->get()
            ->reject(fn ($approval) => $this->isOwnTimesheet($approval, $user, $isOwner));

        if ($approvals->isEmpty()) {
            return back()->with('error', __('No pending approvals found.'));
        }

        try {
            $decide = app(\App\Actions\Timesheets\DecideTimesheetApproval::class);
            foreach ($approvals as $approval) {
                $decide->handle($user, $approval, 'rejected', $validated['comments']);
            }

            return back()->with('success', __('Timesheets rejected successfully!'));
        } catch (\Exception $e) {
            return back()->with('error', __('Failed to reject timesheets.'));
        }
    }

    /**
     * Nobody reviews their own time, except the workspace owner: there is no one
     * above them to send it to.
     */
    private function isOwnTimesheet(TimesheetApproval $approval, $user, bool $isOwner): bool
    {
        return !$isOwner && (int) $approval->timesheet?->user_id === (int) $user->id;
    }
}