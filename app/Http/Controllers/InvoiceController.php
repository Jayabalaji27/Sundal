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
use Illuminate\Validation\ValidationException;
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

        // Apply role-based filtering (managers: their projects incl. drafts; members:
        // their projects, no drafts; clients: sent invoices addressed to them)
        $query->visibleToRole($user, $userWorkspaceRole);

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
        // Overdue is its own bucket (computed from due_date + balance, never for
        // drafts), so sent/partial_paid exclude overdue rows and the buckets add up.
        $statusCounts = ['all' => (clone $query)->where('status', '!=', 'cancelled')->count()];
        foreach (['draft', 'sent', 'paid', 'partial_paid', 'overdue'] as $bucket) {
            $statusCounts[$bucket] = (clone $query)->inStatusBucket($bucket)->count();
        }

        $totalAmount = (clone $query)->sum('total_amount');
        $paidAmount = (clone $query)->sum('paid_amount');
        $outstandingQuery = (clone $query)->whereNotIn('status', ['paid', 'cancelled']);
        $outstandingAmount = $outstandingQuery->sum(\DB::raw('total_amount - paid_amount'));
        $outstandingCount = $outstandingQuery->count();
        $overdueCount = (clone $query)->overdue()->count();

        if ($request->status) {
            $query->inStatusBucket($request->status);
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

        // Get clients for filter dropdown (a client only ever sees their own invoices)
        $clients = $workspace->users()
            ->whereHas('roles', function ($q) {
                $q->where('name', 'client');
            })
            ->when($userWorkspaceRole === 'client', fn ($q) => $q->where('users.id', $user->id))
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
        // Only the user fields the invoice page shows - full user rows carry plan,
        // 2FA, referral and timer data.
        $invoice->load(['project', 'client:id,name,email,avatar', 'creator:id,name,email', 'items.task', 'items.expense', 'items.timesheetEntry']);
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

        // Payments waiting for the owner's approval (bank transfers and online
        // payments that couldn't be verified with the provider).
        $pendingPayments = $invoice->payments()
            ->where('status', \App\Models\Payment::STATUS_PENDING)
            ->latest()
            ->get()
            ->map(function ($payment) {
                $payment->receipt_url = $payment->receipt_path
                    ? (check_file($payment->receipt_path) ? get_file($payment->receipt_path) : $payment->receipt_path)
                    : null;
                return $payment;
            });

        return Inertia::render('invoices/Show', [
            'invoice' => $invoice,
            'userWorkspaceRole' => $userWorkspaceRole,
            'emailNotificationsEnabled' => $this->isEmailConfigured($workspace),
            'pendingPayments' => in_array($userWorkspaceRole, ['owner', 'manager']) || $workspace->isOwner($user) ? $pendingPayments : [],
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

    private const ITEM_AMOUNT_MESSAGES = [
        'items.*.amount.gt' => 'Each item amount must be greater than 0.',
        'items.*.amount.numeric' => 'Each item amount must be a number.',
        'items.*.amount.required' => 'Each item needs an amount.',
    ];

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
            'items.*.amount' => 'required|numeric|gt:0',
            'items.*.task_id' => 'required|exists:tasks,id',
        ], self::ITEM_AMOUNT_MESSAGES);

        $this->ensureTasksNotBilled($validated['items']);

        // Shared with the AI assistant.
        $invoice = app(\App\Actions\Invoices\CreateInvoice::class)->handle($user, $validated);

        return redirect()->route('invoices.show', $invoice)->with('success', __('Invoice created successfully!'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        // The edit form sends the edited value as `amount` (older payloads only
        // sent `rate`). Treat amount as the source of truth so an edit is never
        // silently dropped in favour of the stale rate.
        if (is_array($request->input('items'))) {
            $request->merge(['items' => collect($request->input('items'))
                ->map(fn ($item) => is_array($item)
                    ? array_merge($item, ['amount' => $item['amount'] ?? $item['rate'] ?? null])
                    : $item)
                ->all()]);
        }

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
            'items.*.description' => 'nullable|string',
            'items.*.amount' => 'required|numeric|gt:0',
            'items.*.task_id' => 'nullable|exists:tasks,id',
            'items.*.expense_id' => 'nullable|exists:project_expenses,id',
            'items.*.timesheet_entry_id' => 'nullable|exists:timesheet_entries,id',
        ], self::ITEM_AMOUNT_MESSAGES);

        $this->ensureTasksNotBilled($validated['items'], $invoice->id);

        // Shared with the AI assistant. Every field is sent, so every field changes.
        app(\App\Actions\Invoices\UpdateInvoice::class)->handle(auth()->user(), $invoice, [
            ...array_fill_keys(\App\Actions\Invoices\UpdateInvoice::FIELDS, null),
            'selected_taxes' => [],
            ...$validated,
        ]);

        return redirect()->route('invoices.show', $invoice)->with('success', __('Invoice updated successfully!'));
    }

    /** Stops a task from being billed twice (BUG-14). */
    private function ensureTasksNotBilled(array $items, ?int $exceptInvoiceId = null): void
    {
        $errors = \App\Actions\Invoices\CreateInvoice::billedErrors($items, $exceptInvoiceId);

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
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

    public function getProjectInvoiceData(Request $request, $projectId)
    {
        try {
            $project = Project::findOrFail($projectId);

            // Project tasks not yet billed elsewhere (the invoice being edited keeps its own)
            $tasks = $project->tasks()->get(['id', 'title']);
            $billed = InvoiceItem::billedTaskIds($tasks->pluck('id'), $request->integer('invoice') ?: null);
            $tasks = $tasks->whereNotIn('id', $billed)->values();

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
        app(\App\Actions\Invoices\DeleteInvoice::class)->handle(auth()->user(), $invoice);
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

        // Shared with the AI assistant.
        app(\App\Actions\Invoices\MarkInvoicePaid::class)->handle(
            auth()->user(),
            $invoice,
            isset($validated['paid_amount']) ? (float) $validated['paid_amount'] : null,
            $validated['payment_method'] ?? null,
            $validated['payment_reference'] ?? null,
            $validated['payment_details'] ?? null
        );

        return back()->with('success', __('Invoice marked as paid successfully!'));
    }

    public function send(Invoice $invoice)
    {
        // Shared with the AI assistant: marks it sent and fires the status event.
        app(\App\Actions\Invoices\SendInvoice::class)->handle(auth()->user(), $invoice);

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