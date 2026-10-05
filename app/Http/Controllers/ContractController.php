<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractType;
use App\Models\ContractNote;
use App\Models\ContractComment;
use App\Models\ContractAttachment;
use App\Models\User;
use App\Models\MediaItem;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Events\ContractCreated;
use Illuminate\Support\Facades\Process;

class ContractController extends Controller
{
    use HasPermissionChecks;

    // Only the user fields the contract pages render - full user rows carry plan,
    // 2FA, referral and timer data.
    private const CLIENT_COLUMNS = 'id,name,email,avatar';
    private const CREATOR_COLUMNS = 'id,name,avatar';

    private function isClient(): bool
    {
        $user = auth()->user();

        return $user->currentWorkspace?->getMemberRole($user) === 'client';
    }

    /**
     * Clients only see contracts assigned to them. Anything else - including
     * unassigned contracts - is treated as not found, like other records they
     * can't see.
     */
    private function abortUnlessVisible(Contract $contract): void
    {
        abort_if($this->isClient() && (int) $contract->client_id !== (int) auth()->id(), 404);
    }

    public function index(Request $request)
    {
        $this->authorizePermission('contract_view_any');
        $workspaceId = auth()->user()->current_workspace_id;
        
        // Start with base query without forWorkspace scope to avoid ambiguity
        $query = Contract::where('contracts.workspace_id', $workspaceId)
            ->with(['contractType', 'client:' . self::CLIENT_COLUMNS, 'creator:' . self::CREATOR_COLUMNS])
            ->withCount($this->isClient() ? ['comments', 'attachments'] : ['notes', 'comments', 'attachments'])
            ->when($this->isClient(), fn ($q) => $q->where('contracts.client_id', auth()->id()));

        // Apply filters
        if ($request->filled('status')) {
            $query->where('contracts.status', $request->status);
        }
        if ($request->filled('contract_type_id')) {
            $query->where('contracts.contract_type_id', $request->contract_type_id);
        }
        if ($request->filled('client_id')) {
            $query->where('contracts.client_id', $request->client_id);
        }
        if ($request->filled('project_id')) {
            $query->where('contracts.project_id', $request->project_id);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('contracts.subject', 'like', '%' . $request->search . '%')
                    ->orWhere('contracts.contract_id', 'like', '%' . $request->search . '%')
                    ->orWhereHas('client', function ($clientQuery) use ($request) {
                        $clientQuery->where('name', 'like', '%' . $request->search . '%');
                    });
            });
        }

        // Handle sorting
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        // Validate sort fields
        $allowedSortFields = ['created_at', 'subject', 'contract_value', 'start_date', 'end_date', 'status', 'contract_type.name'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }
        
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }
        
        // Handle nested relationship sorting
        switch ($sortField) {
            case 'contract_type.name':
                $query->leftJoin('contracts_types', function($join) {
                    $join->on('contracts.contract_type_id', '=', 'contracts_types.id');
                })
                ->orderBy('contracts_types.name', $sortDirection)
                ->select('contracts.*');
                break;
            default:
                $query->orderBy('contracts.' . $sortField, $sortDirection);
                break;
        }

        $perPage = in_array($request->get('per_page', 12), [12, 24, 48, 100]) ? $request->get('per_page', 12) : 12;
        $contracts = $query->paginate($perPage);

        $contracts->getCollection()->transform(function ($contract) {
            if ($contract->client) {
                $contract->client->avatar = check_file($contract->client->avatar)
                    ? get_file($contract->client->avatar)
                    : get_file('avatars/avatar.png');
            }
            return $contract;
        });
        
        $contractTypes = ContractType::forWorkspace($workspaceId)->active()->ordered()->get();
        // The client filter and project lists feed the create/edit dialog and the
        // client filter; a client only needs themselves and their own projects.
        $clients = User::whereHas('workspaces', function ($q) use ($workspaceId) {
            $q->where('workspace_id', $workspaceId)
              ->where('role', 'client');
        })
            ->when($this->isClient(), fn ($q) => $q->whereKey(auth()->id()))
            ->get(['id', 'name', 'email']);
        $projects = \App\Models\Project::forWorkspace($workspaceId)
            ->when($this->isClient(), fn ($q) => $q->whereHas('clients', fn ($c) => $c->where('user_id', auth()->id())))
            ->with(['clients:users.id'])
            ->get(['id', 'title'])
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'clients' => $project->clients->map(fn($c) => ['id' => $c->id])
                ];
            });

        return Inertia::render('contracts/Index', [
            'contracts' => $contracts,
            'contractTypes' => $contractTypes,
            'clients' => $clients,
            'projects' => $projects,
            'filters' => $request->only(['status', 'contract_type_id', 'client_id', 'project_id', 'search', 'per_page', 'sort_field', 'sort_direction', 'view_mode']),
            'permissions' => [
                'create' => $this->checkPermission('contract_create'),
                'update' => $this->checkPermission('contract_update'),
                'delete' => $this->checkPermission('contract_delete'),
                'view' => $this->checkPermission('contract_view'),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('contract_create');
        $request->validate([
            'subject' => 'required|string|max:255',
            'contract_type_id' => 'required|exists:contracts_types,id',
            'contract_value' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'client_id' => 'nullable|exists:users,id',
            'file' => 'required|file|max:10240',
        ]);

        $contract = Contract::create([
            'subject' => $request->subject,
            'contract_type_id' => $request->contract_type_id,
            'contract_value' => $request->contract_value ?? 0,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'client_id' => $request->client_id,
            'project_id' => $request->project_id,
            'currency' => $request->currency ?? 'USD',
            'workspace_id' => auth()->user()->current_workspace_id,
            'created_by' => auth()->id(),
        ]);

        $error = $this->storeAttachment($contract, $request->file('file'));
        if ($error) {
            $contract->delete();
            return back()->withErrors(['file' => $error]);
        }

       if (!config('app.is_demo', true)) {
            event(new ContractCreated($contract));
        }
        return redirect()->route('contracts.index')->with('success', 'Contract created successfully.');
    }

    public function show(Contract $contract)
    {
        $this->authorizePermission('contract_view');
        $this->abortUnlessVisible($contract);
        // Notes and internal comments are for the team only - never sent to a client.
        $isClient = $this->isClient();
        $contract->load([
            'contractType',
            'client:' . self::CLIENT_COLUMNS,
            'creator:' . self::CREATOR_COLUMNS,
            'notes' => fn($q) => $q->with('creator:' . self::CREATOR_COLUMNS)->when($isClient, fn ($n) => $n->whereRaw('1 = 0'))->orderBy('is_pinned', 'desc')->orderBy('created_at', 'desc'),
            'comments' => fn($q) => $q->with('creator:' . self::CREATOR_COLUMNS)->when($isClient, fn ($c) => $c->where('is_internal', false))->orderBy('created_at', 'desc'),
            'attachments'
        ]);

        foreach ($contract->attachments as $attachment) {
            $attachment->url = get_file($attachment->files);
        }

        if ($contract->client) {
            $contract->client->avatar = check_file($contract->client->avatar)
                ? get_file($contract->client->avatar)
                : get_file('avatars/avatar.png');
        }

        if ($contract->creator) {
            $contract->creator->avatar = check_file($contract->creator->avatar)
                ? get_file($contract->creator->avatar)
                : get_file('avatars/avatar.png');
        }

        if ($contract->comments) {
            $contract->comments->map(function ($comment) {
                $comment->creator->avatar = check_file($comment->creator->avatar)
                    ? get_file($comment->creator->avatar)
                    : get_file('avatars/avatar.png');
                return $comment;
            });
        }

        if ($contract->getRelation('notes')) {
            $contract->getRelation('notes')->each(function ($note) {
                if ($note->creator) {
                    $note->creator->avatar = check_file($note->creator->avatar)
                        ? get_file($note->creator->avatar)
                        : get_file('avatars/avatar.png');
                }
            });
        }

        $emailEnabled = isEmailTemplateEnabled('New Contract', auth()->user()->id);
        
        return Inertia::render('contracts/Show', [
            'contract' => $contract,
            'assignedUsers' => $contract->assignedUsers()->map(function ($user) {
                $user->avatar = check_file($user->avatar) ? get_file($user->avatar) : get_file('avatars/avatar.png');
                return $user;
            }),
            'emailTemplateEnabled' => $emailEnabled,
            'permissions' => [
                'update' => $this->checkPermission('contract_update'),
                'delete' => $this->checkPermission('contract_delete'),
                'viewNotes' => !$isClient,
            ]
        ]);
    }

    public function update(Request $request, Contract $contract)
    {
        $this->authorizePermission('contract_update');
        $request->validate([
            'subject' => 'required|string|max:255',
            'contract_type_id' => 'required|exists:contracts_types,id',
            'contract_value' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'client_id' => 'nullable|exists:users,id',
        ]);

        $contract->update($request->only([
            'subject',
            'contract_type_id',
            'start_date',
            'end_date',
            'client_id',
            'project_id',
            'currency'
        ]) + ['contract_value' => $request->contract_value ?? 0]);

        return redirect()->route('contracts.index')->with('success', 'Contract updated successfully.');
    }

    public function destroy(Contract $contract)
    {
        $this->authorizePermission('contract_delete');
        foreach ($contract->attachments as $attachment) {
            delete_file($attachment->files);
        }
        
        $contract->notes()->delete();
        $contract->comments()->delete();
        $contract->attachments()->delete();
        $contract->delete();
        return redirect()->route('contracts.index')->with('success', 'Contract deleted successfully.');
    }

    public function changeStatus(Request $request, Contract $contract)
    {
        $this->authorizePermission('contract_change_status');
        $request->validate(['status' => 'required|in:pending,sent,accept,decline,expired']);

        $user = auth()->user();
        $statusLabels = ['pending' => 'pending', 'sent' => 'sent', 'accept' => 'accepted', 'decline' => 'declined', 'expired' => 'expired'];

        if ($contract->status === $request->status) {
            return redirect()->back()->with('warning', __('Contract is already :status.', ['status' => $statusLabels[$contract->status] ?? $contract->status]));
        }

        // A client may only respond to their own contract (accept or decline), and
        // only while it's still awaiting a response - an accepted or declined
        // contract is final from the client's side.
        if ($user->currentWorkspace?->getMemberRole($user) === 'client') {
            abort_if((int) $contract->client_id !== (int) $user->id, 403);

            if (!in_array($request->status, ['accept', 'decline'], true)) {
                return redirect()->back()->with('error', __('You can only accept or decline a contract.'));
            }
            if (in_array($contract->status, ['accept', 'decline', 'expired'], true)) {
                return redirect()->back()->with('error', __('This contract is already :status and can no longer be changed.', ['status' => $statusLabels[$contract->status]]));
            }
        }

        $updates = ['status' => $request->status];
        if ($request->status === 'sent' && !$contract->sent_at)
            $updates['sent_at'] = now();
        if ($request->status === 'accept' && !$contract->accepted_at)
            $updates['accepted_at'] = now();
        if ($request->status === 'decline' && !$contract->declined_at)
            $updates['declined_at'] = now();
        $contract->update($updates);
        return redirect()->back()->with('success', 'Contract status updated successfully.');
    }

    public function noteStore(Request $request, Contract $contract)
    {
        $request->validate(['note' => 'required|string']);
        ContractNote::create([
            'contract_id' => $contract->id,
            'note' => $request->note,
            'is_pinned' => false,
            'created_by' => auth()->id(),
        ]);
        return back()->with('success', 'Note added successfully.');
    }

    public function noteUpdate(Request $request, Contract $contract, ContractNote $note)
    {
        if ($request->has('is_pinned')) {
            $note->update(['is_pinned' => $request->boolean('is_pinned')]);
        } else {
            $request->validate(['note' => 'required|string']);
            $note->update(['note' => $request->note]);
        }
        return back()->with('success', 'Note updated successfully.');
    }

    public function noteDestroy(Contract $contract, ContractNote $note)
    {
        $note->delete();
        return redirect()->back()->with('success', 'Note deleted successfully.');
    }

    public function commentStore(Request $request, Contract $contract)
    {
        $this->abortUnlessVisible($contract);
        $request->validate(['comment' => 'required|string']);
        ContractComment::create([
            'contract_id' => $contract->id,
            'comment' => $request->comment,
            'parent_id' => $request->parent_id,
            'is_internal' => $request->boolean('is_internal'),
            'created_by' => auth()->id(),
        ]);
        return redirect()->back()->with('success', 'Comment added successfully.');
    }

    public function commentUpdate(Request $request, ContractComment $comment)
    {
        $request->validate(['comment' => 'required|string']);
        $comment->update(['comment' => $request->comment]);
        return back()->with('success', 'Comment updated successfully.');
    }

    public function commentDestroy(ContractComment $comment)
    {
        $comment->delete();
        return redirect()->back()->with('success', 'Comment deleted successfully.');
    }

    public function fileUpload(Request $request, Contract $contract)
    {
        if ($contract->workspace_id !== auth()->user()->current_workspace_id) {
            abort(403, __('Contract not found in current workspace'));
        }

        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|max:10240'
        ]);

        foreach ($request->file('files') as $file) {
            $error = $this->storeAttachment($contract, $file);
            if ($error) {
                return back()->withErrors(['files' => $error]);
            }
        }

        return back()->with('success', __('Attachment(s) uploaded successfully'));
    }

    /**
     * Upload a single file and attach it to the contract. Returns an error
     * message on failure, or null on success.
     */
    private function storeAttachment(Contract $contract, $file): ?string
    {
        $filenameWithExt = $file->getClientOriginalName();
        $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $fileNameToStore = $filename . '_' . time() . '_' . uniqid() . '.' . $extension;

        $singleFileRequest = new Request();
        $singleFileRequest->files->set('file', $file);
        $singleFileRequest->merge(['file' => $file]);

        $upload = upload_file($singleFileRequest, 'file', $fileNameToStore, 'contracts/attachments');

        if ($upload['status'] != true) {
            return $upload['msg'];
        }

        ContractAttachment::create([
            'contract_id' => $contract->id,
            'files' => $upload['url'],
            'workspace_id' => $contract->workspace_id
        ]);

        return null;
    }

    public function fileDelete(ContractAttachment $attachment)
    {
        delete_file($attachment->files);
        $attachment->delete();
        return redirect()->back()->with('success', 'Attachment removed successfully.');
    }

    public function fileDownload(ContractAttachment $attachment)
    {
        abort_unless($attachment->contract, 404);
        $this->abortUnlessVisible($attachment->contract);

        return download_file($attachment->files, basename($attachment->files));
    }
}