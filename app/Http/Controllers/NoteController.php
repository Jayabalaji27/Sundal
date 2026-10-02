<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class NoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $currentWorkspace = Auth::user()->currentWorkspace;
        
        if (!$currentWorkspace) {
            return redirect()->route('dashboard');
        }

        $perPage = in_array($request->get('per_page', 12), [12, 24, 48, 100]) ? $request->get('per_page', 12) : 12;

        // Build query for personal notes
        $personalQuery = Note::where('type', '=', 'personal')
            ->where('workspace', '=', $currentWorkspace->id)
            ->where('created_by', '=', Auth::user()->id)
            ->with('creator');

        // Build query for shared notes
        $sharedQuery = Note::where('type', '=', 'shared')
            ->where('workspace', '=', $currentWorkspace->id)
            ->whereRaw("find_in_set('" . Auth::user()->id . "',notes.assign_to)")
            ->with('creator');

        // Apply search filters
        if ($request->search) {
            $searchTerm = $request->search;
            $personalQuery->where(function($q) use ($searchTerm) {
                $q->where('title', 'like', '%' . $searchTerm . '%')
                  ->orWhere('text', 'like', '%' . $searchTerm . '%');
            });
            
            $sharedQuery->where(function($q) use ($searchTerm) {
                $q->where('title', 'like', '%' . $searchTerm . '%')
                  ->orWhere('text', 'like', '%' . $searchTerm . '%');
            });
        }

        // Apply type filter
        if ($request->type && $request->type !== 'all') {
            if ($request->type === 'personal') {
                $sharedQuery = $sharedQuery->whereRaw('1 = 0'); // No shared notes
            } elseif ($request->type === 'shared') {
                $personalQuery = $personalQuery->whereRaw('1 = 0'); // No personal notes
            }
        }

        // Handle sorting
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        // Validate sort fields
        $allowedSortFields = ['created_at', 'title', 'type', 'updated_at'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }
        
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }
        
        // Apply sorting to both queries
        $personalQuery->orderBy($sortField, $sortDirection);
        $sharedQuery->orderBy($sortField, $sortDirection);

        $transformNote = function ($note) {
            if ($note->creator) {
                $note->creator->avatar = check_file($note->creator->avatar)
                    ? get_file($note->creator->avatar)
                    : get_file('avatars/avatar.png');
            }
            if ($note->assign_to) {
                $assignedUserIds = explode(',', $note->assign_to);
                $note->assigned_users = User::whereIn('id', $assignedUserIds)->select('id', 'name', 'email')->get();
            }
            return $note;
        };

        // Get paginated results
        $personal_notes = $personalQuery->paginate($perPage, ['*'], 'personal_page')->through($transformNote);
        $shared_notes = $sharedQuery->paginate($perPage, ['*'], 'shared_page')->through($transformNote);

        // Build combined query for both list and grid views
        if (true) {
            $combinedQuery = Note::where('workspace', '=', $currentWorkspace->id)
                ->where(function($q) {
                    $q->where(function($personalQ) {
                        $personalQ->where('type', '=', 'personal')
                                 ->where('created_by', '=', Auth::user()->id);
                    })->orWhere(function($sharedQ) {
                        $sharedQ->where('type', '=', 'shared')
                               ->whereRaw("find_in_set('" . Auth::user()->id . "',notes.assign_to)");
                    });
                })
                ->with('creator');

            // Apply search to combined query
            if ($request->search) {
                $searchTerm = $request->search;
                $combinedQuery->where(function($q) use ($searchTerm) {
                    $q->where('title', 'like', '%' . $searchTerm . '%')
                      ->orWhere('text', 'like', '%' . $searchTerm . '%');
                });
            }

            // Apply type filter to combined query
            if ($request->type && $request->type !== 'all') {
                $combinedQuery->where('type', $request->type);
            }

            // Apply sorting to combined query
            $combinedQuery->orderBy($sortField, $sortDirection);

            $combined_notes = $combinedQuery->paginate($perPage, ['*'], 'notes_page')->through($transformNote);
        }

        $users = User::select('users.*', 'workspace_members.role')
            ->join('workspace_members', 'workspace_members.user_id', '=', 'users.id')
            ->where('workspace_members.workspace_id', '=', $currentWorkspace->id)
            ->where('users.id', '!=', Auth::user()->id)
            ->get();

        return Inertia::render('notes/Index', [
            'personal_notes' => $personal_notes,
            'shared_notes' => $shared_notes,
            'combined_notes' => isset($combined_notes) ? $combined_notes : null,
            'users' => $users,
            'currentWorkspace' => $currentWorkspace,
            'filters' => $request->only(['per_page', 'search', 'type', 'sort_field', 'sort_direction', 'view_mode']),
            'permissions' => [
                'view' => Auth::user()->can('note_view_any'),
                'create' => Auth::user()->can('note_create'),
                'update' => Auth::user()->can('note_update'),
                'delete' => Auth::user()->can('note_delete')
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'text' => 'required|string',
            'color' => 'required|string',
            'type' => 'required|in:personal,shared',
            'assign_to' => 'nullable|array'
        ]);

        $currentWorkspace = Auth::user()->currentWorkspace;
        
        if (!$currentWorkspace) {
            return response()->json(['error' => 'No workspace selected'], 400);
        }

        $data = $request->all();
        
        if ($data['type'] === 'shared' && !empty($data['assign_to'])) {
            $assign_to = $data['assign_to'];
            $assign_to[] = Auth::user()->id;
            $data['assign_to'] = implode(',', array_unique($assign_to));
        } else {
            $data['assign_to'] = null;
        }

        $data['workspace'] = $currentWorkspace->id;
        $data['created_by'] = Auth::user()->id;

        Note::create($data);

        return redirect()->route('notes.index')->with('success', 'Note created successfully!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Note $note)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'text' => 'required|string',
            'color' => 'required|string',
            'type' => 'required|in:personal,shared',
            'assign_to' => 'nullable|array'
        ]);

        $currentWorkspace = Auth::user()->currentWorkspace;
        
        // Check workspace access only
        if ($note->workspace !== $currentWorkspace->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = $request->all();
        
        if ($data['type'] === 'shared' && !empty($data['assign_to'])) {
            $assign_to = $data['assign_to'];
            $assign_to[] = Auth::user()->id;
            $data['assign_to'] = implode(',', array_unique($assign_to));
        } else {
            $data['assign_to'] = null;
        }

        $note->update($data);

        return redirect()->route('notes.index')->with('success', 'Note updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Note $note)
    {
        $currentWorkspace = Auth::user()->currentWorkspace;
        
        // Check workspace access only
        if ($note->workspace !== $currentWorkspace->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $note->delete();

        return redirect()->route('notes.index')->with('success', 'Note deleted successfully!');
    }
}