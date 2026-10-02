<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tax;
use App\Models\ProjectExpense;
use App\Models\TimesheetEntry;
use App\Exports\InvoiceExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class InvoiceController extends Controller
{
    public function export(Request $request)
    {
        return Excel::download(new InvoiceExport($request), 'invoices_' . date('Y-m-d_His') . '.xlsx');
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $userWorkspaceRole = $workspace->getMemberRole($user);

        $query = Invoice::with(['project:id,title', 'client:id,name,avatar', 'creator:id,name', 'payments'])
            ->where('workspace_id', $workspace->id);

        // Apply role-based filtering
        if (in_array($userWorkspaceRole, ['manager', 'member'])) {
            $query->where(function ($q) use ($user, $userWorkspaceRole) {
                // Show sent invoices to all members
                $q->where('status', '!=', 'draft')
                    ->whereHas('project', function ($projQ) use ($user) {
                        $projQ->where(function ($projectQuery) use ($user) {
                            $projectQuery->whereHas('members', function ($memberQuery) use ($user) {
                                $memberQuery->where('user_id', $user->id);
                            })->orWhere('created_by', $user->id);
                        });
                    });

                // Show draft invoices only to managers
                if ($userWorkspaceRole === 'manager') {
                    $q->orWhere('status', 'draft')
                        ->whereHas('project', function ($projQ) use ($user) {
                            $projQ->where(function ($projectQuery) use ($user) {
                                $projectQuery->whereHas('members', function ($memberQuery) use ($user) {
                                    $memberQuery->where('user_id', $user->id);
                                })->orWhere('created_by', $user->id);
                            });
                        });
                }
            });
        } elseif ($userWorkspaceRole === 'client') {
            // Clients only see sent invoices assigned to them
            $query->where('client_id', $user->id)
                ->where('status', '!=', 'draft');
        }
        // Owners see all invoices (no additional filtering needed)

        // Apply filters
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('invoice_number', 'like', '%' . $request->search . '%')
                    ->orWhere('title', 'like', '%' . $request->search . '%')
                    ->orWhereHas('project', function ($projQ) use ($request) {
                        $projQ->where('title', 'like', '%' . $request->search . '%');
                    });
            });
        }

        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->client_id) {
            $query->where('client_id', $request->client_id);
        }

        // Snapshot the role/search/project/client-scoped query (before the status
        // filter) so status tab counts reflect the same rows the user could see
        // under any tab, not just the currently selected one.
        $statusCounts = [
            'all' => (clone $query)->count(),
            'draft' => (clone $query)->where('status', 'draft')->count(),
            'sent' => (clone $query)->where('status', 'sent')->count(),
            'paid' => (clone $query)->where('status', 'paid')->count(),
            'partial_paid' => (clone $query)->where('status', 'partial_paid')->count(),
            // Overdue is computed from due_date, not a literal status value - nothing
            // proactively flips an invoice's status to 'overdue' outside of a payment
            // attempt, so a status match here would almost always read 0.
            'overdue' => (clone $query)->overdue()->count(),
        ];

        $totalAmount = (clone $query)->sum('total_amount');
        $paidAmount = (clone $query)->sum('paid_amount');
        $outstandingQuery = (clone $query)->whereNotIn('status', ['paid', 'cancelled']);
        $outstandingAmount = $outstandingQuery->sum(\DB::raw('total_amount - paid_amount'));
        $outstandingCount = $outstandingQuery->count();
        $overdueCount = (clone $query)->overdue()->count();

        if ($request->status === 'overdue') {
            $query->overdue();
        } elseif ($request->status) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 12);
        $invoices = $query->latest()->paginate($perPage)->withQueryString();

        // Debug: Log invoices without projects
        $invoicesWithoutProject = $invoices->getCollection()->filter(function ($invoice) {
            return is_null($invoice->project);
        });

        if ($invoicesWithoutProject->count() > 0) {
            \Log::warning('Found invoices without projects:', [
                'count' => $invoicesWithoutProject->count(),
                'invoice_ids' => $invoicesWithoutProject->pluck('id')->toArray()
            ]);
        }

        // Get projects for filter dropdown (Project::scopeVisibleTo - same rule
        // used on the Projects and Budgets pages, so a project doesn't show up
        // here but not there).
        $projectsQuery = Project::forWorkspace($workspace->id);
        if (in_array($userWorkspaceRole, ['manager', 'member', 'client'])) {
            $projectsQuery->visibleTo($user);
        }
        $projects = $projectsQuery->get(['id', 'title']);

        // Get clients for filter dropdown
        $clients = $workspace->users()
            ->whereHas('roles', function ($q) {
                $q->where('name', 'client');
            })
            ->get(['users.id', 'users.name']);

        return Inertia::render('invoices/Index', [
            'invoices' => $invoices,
            'projects' => $projects,
            'clients' => $clients,
            'filters' => $request->only(['search', 'status', 'project_id', 'client_id', 'per_page']),
            'userWorkspaceRole' => $userWorkspaceRole,
            'statusCounts' => $statusCounts,
            'totalAmount' => $totalAmount,
            'paidAmount' => $paidAmount,
            'outstandingAmount' => $outstandingAmount,
            'outstandingCount' => $outstandingCount,
            'overdueCount' => $overdueCount,
            'emailNotificationsEnabled' => $this->isEmailConfigured($workspace),
        ]);
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['project', 'client', 'creator', 'items.task', 'items.expense', 'items.timesheetEntry']);
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $userWorkspaceRole = $workspace->getMemberRole($user);

        // Check access permissions for draft invoices
        if ($invoice->status === 'draft') {
            // Only managers and owners can view draft invoices
            if (!in_array($userWorkspaceRole, ['owner', 'manager'])) {
                abort(403, 'Access denied. Draft invoices are only visible to managers and owners.');
            }
        } elseif ($userWorkspaceRole === 'client') {
            // Clients can only view invoices assigned to them
            if ($invoice->client_id !== $user->id) {
                abort(403, 'Access denied.');
            }
        }

        return Inertia::render('invoices/Show', [
            'invoice' => $invoice,
            'userWorkspaceRole' => $userWorkspaceRole,
            'emailNotificationsEnabled' => $this->isEmailConfigured($workspace),
        ]);
    }

    /**
     * Whether the workspace owner has configured real SMTP settings (not the
     * unedited "smtp.example.com" placeholder) — see EmailSettingController.
     */
    private function isEmailConfigured($workspace): bool
    {
        $host = getSetting('email_host', 'smtp.example.com', $workspace->owner_id, $workspace->id);

        return !empty($host) && $host !== 'smtp.example.com';
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'client_id' => 'nullable|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'selected_taxes' => 'nullable|array',
            'selected_taxes.*' => 'exists:taxes,id',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|in:task',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.task_id' => 'required|exists:tasks,id',
        ]);

        $project = Project::findOrFail($validated['project_id']);

        // Calculate totals
        $subtotal = collect($validated['items'])->sum('amount');
        $appliedTaxes = Tax::whereIn('id', $validated['selected_taxes'] ?? [])->get(['id', 'name', 'rate']);
        $taxAmount = $appliedTaxes->sum(fn (Tax $tax) => ($subtotal * $tax->rate) / 100);
        $totalAmount = $subtotal + $taxAmount;

        $invoice = Invoice::create([
            'project_id' => $validated['project_id'],
            'workspace_id' => $project->workspace_id,
            'client_id' => $validated['client_id'] ?? null,
            'created_by' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'],
            'tax_rate' => $appliedTaxes->map(fn (Tax $tax) => ['id' => $tax->id, 'name' => $tax->name, 'rate' => $tax->rate])->all(),
            'notes' => $validated['notes'] ?? null,
            'terms' => $validated['terms'] ?? null,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ]);

        // Fire event for email notification
        if (!config('app.is_demo', true)) {
            event(new \App\Events\InvoiceCreated($invoice));
        }


        // Create invoice items
        foreach ($validated['items'] as $index => $item) {
            $task = Task::find($item['task_id']);
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'type' => 'task',
                'description' => $task ? $task->title : 'Task',
                'rate' => $item['amount'],
                'amount' => $item['amount'],
                'task_id' => $item['task_id'],
                'sort_order' => $index + 1,
            ]);
        }

        return redirect()->route('invoices.show', $invoice)->with('success', __('Invoice created successfully!'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'client_id' => 'nullable|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'selected_taxes' => 'nullable|array',
            'selected_taxes.*' => 'exists:taxes,id',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|in:custom,task,expense,time',
            'items.*.description' => 'required|string',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.task_id' => 'nullable|exists:tasks,id',
            'items.*.expense_id' => 'nullable|exists:project_expenses,id',
            'items.*.timesheet_entry_id' => 'nullable|exists:timesheet_entries,id',
        ]);

        $appliedTaxes = Tax::whereIn('id', $validated['selected_taxes'] ?? [])->get(['id', 'name', 'rate']);

        $invoice->update([
            'client_id' => $validated['client_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'],
            'tax_rate' => $appliedTaxes->map(fn (Tax $tax) => ['id' => $tax->id, 'name' => $tax->name, 'rate' => $tax->rate])->all(),
            'notes' => $validated['notes'] ?? null,
            'terms' => $validated['terms'] ?? null,
        ]);

        // Update items
        $invoice->items()->delete();
        foreach ($validated['items'] as $index => $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'type' => $item['type'],
                'description' => $item['description'],
                'rate' => $item['rate'],
                'amount' => $item['rate'],
                'task_id' => $item['task_id'] ?? null,
                'expense_id' => $item['expense_id'] ?? null,
                'timesheet_entry_id' => $item['timesheet_entry_id'] ?? null,
                'sort_order' => $index + 1,
            ]);
        }

        // Recalculate totals after updating items
        $invoice->calculateTotals();

        // Fire event for email notification
        if (!config('app.is_demo', true)) {
            event(new \App\Events\InvoiceCreated($invoice));
        }

        return redirect()->route('invoices.show', $invoice)->with('success', __('Invoice updated successfully!'));
    }

    public function create()
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $userWorkspaceRole = $workspace->getMemberRole($user);

        $projectsQuery = Project::forWorkspace($workspace->id);
        if (in_array($userWorkspaceRole, ['manager', 'member', 'client'])) {
            $projectsQuery->visibleTo($user);
        }
        $projects = $projectsQuery->get(['id', 'title']);
        $clients = $workspace->users()->whereHas('roles', function ($q) {
            $q->where('name', 'client');
        })->get(['users.id', 'users.name']);
        $taxes = Tax::orderBy('name')->get(['id', 'name', 'rate']);

        return Inertia::render('invoices/Form', [
            'projects' => $projects,
            'clients' => $clients,
            'taxes' => $taxes,
        ]);
    }

    public function getProjectInvoiceData($projectId)
    {
        try {
            $project = Project::findOrFail($projectId);

            // Get project tasks
            $tasks = $project->tasks()->get(['id', 'title']);

            // Get project clients using the clients relationship
            $clients = $project->clients()->get(['users.id', 'users.name']);

            return response()->json([
                'tasks' => $tasks,
                'clients' => $clients
            ]);
        } catch (\Exception $e) {
            \Log::error('Error loading project invoice data: ' . $e->getMessage());
            return response()->json([
                'tasks' => [],
                'clients' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function edit(Invoice $invoice)
    {
        $invoice->load(['items']);

        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $userWorkspaceRole = $workspace->getMemberRole($user);

        // Only managers and owners can edit invoices
        if (!in_array($userWorkspaceRole, ['owner', 'manager'])) {
            abort(403, 'Access denied. Only managers and owners can edit invoices.');
        }

        $projectsQuery = Project::forWorkspace($workspace->id);
        if ($userWorkspaceRole === 'manager') {
            $projectsQuery->visibleTo($user);
        }
        $projects = $projectsQuery->get(['id', 'title']);
        $clients = $workspace->users()->whereHas('roles', function ($q) {
            $q->where('name', 'client');
        })->get(['users.id', 'users.name']);
        $taxes = Tax::orderBy('name')->get(['id', 'name', 'rate']);

        return Inertia::render('invoices/Form', [
            'invoice' => $invoice,
            'projects' => $projects,
            'clients' => $clients,
            'taxes' => $taxes,
        ]);
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();
        return back()->with('success', __('Invoice deleted successfully!'));
    }



    public function markAsPaid(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'paid_amount' => 'nullable|numeric|min:0|max:' . $invoice->total_amount,
            'payment_method' => 'nullable|string',
            'payment_reference' => 'nullable|string',
            'payment_details' => 'nullable|array',
        ]);

        $oldStatus = $invoice->status;
        $invoice->markAsPaid(
            $validated['paid_amount'] ?? null,
            $validated['payment_method'] ?? null,
            $validated['payment_reference'] ?? null,
            $validated['payment_details'] ?? null
        );

        // Fire event for Slack notification
        if (!config('app.is_demo', true)) {
            event(new \App\Events\InvoiceStatusUpdated($invoice, $oldStatus, 'paid'));
        }

        return back()->with('success', __('Invoice marked as paid successfully!'));
    }

    public function send(Invoice $invoice)
    {
        $oldStatus = $invoice->status;
        $invoice->update([
            'status' => 'sent',
            'sent_at' => now()
        ]);

        // Fire event for Slack notification
        if (!config('app.is_demo', true)) {
            event(new \App\Events\InvoiceStatusUpdated($invoice, $oldStatus, 'sent'));
        }

        return back()->with('success', __('Invoice sent successfully!'));
    }

    public function getProjectData(Project $project)
    {
        $tasks = $project->tasks()
            ->with('taskStage')
            ->where('status', 'completed')
            ->get(['id', 'title', 'task_stage_id']);

        $expenses = $project->expenses()
            ->with('budgetCategory')
            ->where('status', 'approved')
            ->get(['id', 'title', 'amount', 'currency', 'budget_category_id']);

        $timesheetEntries = TimesheetEntry::whereHas('timesheet', function ($q) use ($project) {
            $q->where('project_id', $project->id);
        })
            ->with(['task', 'user'])
            ->get(['id', 'task_id', 'user_id', 'hours', 'description']);

        return response()->json([
            'tasks' => $tasks,
            'expenses' => $expenses,
            'timesheet_entries' => $timesheetEntries
        ]);
    }

    public function preview(Invoice $invoice)
    {
        $invoice->load(['project', 'client', 'creator', 'items.task', 'items.expense', 'items.timesheetEntry', 'payments']);
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $userWorkspaceRole = $workspace->getMemberRole($user);
        if ($userWorkspaceRole === 'client' && $invoice->client_id !== $user->id) {
            abort(403, 'Access denied.');
        }
        $invoiceData = $invoice->toArray();
        $taxRate = $invoice->tax_rate;
        if (is_string($taxRate)) {
            $taxRate = json_decode($taxRate, true) ?: [];
        }
        $invoiceData['tax_rate'] = $taxRate;
        $invoiceSettings = \App\Models\Setting::where('user_id', $invoice->created_by)
            ->whereIn('key', ['invoice_template', 'invoice_qr_display', 'invoice_color', 'invoice_footer_title', 'invoice_footer_notes', 'invoice_logo'])
            ->pluck('value', 'key')
            ->toArray();
        return \Inertia\Inertia::render('invoices/Preview', [
            'invoice' => $invoiceData,
            'invoiceSettings' => $invoiceSettings,
        ]);
    }

    // The "Download PDF" button used to open invoices/Preview and auto-run a
    // client-side html2canvas capture that silently failed (errors were swallowed
    // and the tab closed itself regardless of success) - generate the PDF
    // server-side instead, same pattern as ProjectReportController, and return it
    // as a real attachment download.
    public function downloadPdf(Invoice $invoice)
    {
        $invoice->load(['project', 'client', 'items']);
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $userWorkspaceRole = $workspace->getMemberRole($user);
        if ($userWorkspaceRole === 'client' && $invoice->client_id !== $user->id) {
            abort(403, 'Access denied.');
        }

        $currencyCode = settings()['defaultCurrency'] ?? 'USD';
        $currencySymbol = \App\Models\Currency::where('code', $currencyCode)->value('symbol') ?? '$';

        $html = view('pdf.invoice', [
            'invoice' => $invoice,
            'currencySymbol' => $currencySymbol,
        ])->render();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        return $pdf->download('Invoice-' . $invoice->invoice_number . '.pdf');
    }

    public function approvePayment(\Illuminate\Http\Request $request, Invoice $invoice, \App\Models\Payment $payment)
    {
        if ($payment->invoice_id !== $invoice->id || $payment->status !== 'pending') {
            abort(422, 'Invalid payment.');
        }
        $payment->update(['status' => 'completed']);
        $invoice->updatePaymentStatus();
        return back()->with('success', __('Payment approved successfully!'));
    }

    public function rejectPayment(\Illuminate\Http\Request $request, Invoice $invoice, \App\Models\Payment $payment)
    {
        if ($payment->invoice_id !== $invoice->id || $payment->status !== 'pending') {
            abort(422, 'Invalid payment.');
        }
        $payment->delete();
        return back()->with('success', __('Payment rejected and removed.'));
    }
}