<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $query = Contact::query();
            
        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('subject', 'like', "%{$request->search}%");
            });
        }
        
        // Apply status filter
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        // Apply date filters
        if ($request->has('start_date') && !empty($request->start_date)) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        
        if ($request->has('end_date') && !empty($request->end_date)) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        
        // Apply sorting
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);
        
        // Get paginated results
        $perPage = $request->input('per_page', 10);
        $contacts = $query->paginate($perPage)->withQueryString();
        
        return Inertia::render('contacts/index', [
            'contacts' => $contacts,
            'filters' => $request->only(['search', 'status', 'start_date', 'end_date', 'sort_field', 'sort_direction', 'per_page'])
        ]);
    }
    
    public function show(Contact $contact)
    {
        // Mark as read if it's new
        if ($contact->status === 'new') {
            $contact->markAsRead();
        }
        
        return Inertia::render('contacts/show', [
            'contact' => $contact
        ]);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'status' => 'required|in:new,read,replied,closed',
        ]);
        
        $contact = Contact::create($validated);
        
        return redirect()->back()->with('success', __('Contact created successfully'));
    }
    
    public function update(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'status' => 'required|in:new,read,replied,closed',
            'admin_notes' => 'nullable|string',
        ]);
        
        // Update timestamps based on status change
        $updateData = $validated;
        if ($contact->status !== $validated['status']) {
            if ($validated['status'] === 'read' && $contact->status === 'new') {
                $updateData['read_at'] = now();
            } elseif ($validated['status'] === 'replied') {
                $updateData['replied_at'] = now();
            }
        }
        
        $contact->update($updateData);
        
        return redirect()->back()->with('success', __('Contact updated successfully'));
    }
    
    public function destroy(Contact $contact)
    {
        $contact->delete();
        
        return redirect()->back()->with('success', __('Contact deleted successfully'));
    }
    
    public function updateStatus(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'status' => 'required|in:new,read,replied,closed',
            'admin_notes' => 'nullable|string',
        ]);
        
        $updateData = ['status' => $validated['status']];
        
        if (isset($validated['admin_notes'])) {
            $updateData['admin_notes'] = $validated['admin_notes'];
        }
        
        // Update timestamps based on status
        if ($validated['status'] === 'read' && $contact->status === 'new') {
            $updateData['read_at'] = now();
        } elseif ($validated['status'] === 'replied') {
            $updateData['replied_at'] = now();
        }
        
        $contact->update($updateData);
        
        return redirect()->back()->with('success', __('Contact status updated successfully'));
    }
    
    public function bulkUpdateStatus(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:contacts,id',
            'status' => 'required|in:new,read,replied,closed'
        ]);
        
        $updateData = ['status' => $validated['status']];
        
        // Update timestamps based on status
        if ($validated['status'] === 'read') {
            $updateData['read_at'] = now();
        } elseif ($validated['status'] === 'replied') {
            $updateData['replied_at'] = now();
        }
        
        Contact::whereIn('id', $validated['ids'])->update($updateData);
        
        return redirect()->back()->with('success', __('Selected contacts status updated successfully'));
    }
    
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:contacts,id'
        ]);
        
        Contact::whereIn('id', $validated['ids'])->delete();
        
        return redirect()->back()->with('success', __('Selected contacts deleted successfully'));
    }
    
    public function export(Request $request)
    {
        $query = Contact::query();
        
        // Apply same filters as index
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('subject', 'like', "%{$request->search}%");
            });
        }
        
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        if ($request->has('start_date') && !empty($request->start_date)) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        
        if ($request->has('end_date') && !empty($request->end_date)) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        
        $contacts = $query->orderBy('created_at', 'desc')->get();
        
        $filename = 'contacts_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];
        
        $callback = function() use ($contacts) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Name', 'Email', 'Subject', 'Message', 'Status', 'Admin Notes', 'Created At', 'Read At', 'Replied At']);
            
            foreach ($contacts as $contact) {
                fputcsv($file, [
                    $contact->name,
                    $contact->email,
                    $contact->subject,
                    $contact->message,
                    $contact->status,
                    $contact->admin_notes,
                    $contact->created_at->format('Y-m-d H:i:s'),
                    $contact->read_at?->format('Y-m-d H:i:s'),
                    $contact->replied_at?->format('Y-m-d H:i:s'),
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
}