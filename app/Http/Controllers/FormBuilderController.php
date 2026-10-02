<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FormBuilderController extends Controller
{
    // ── Admin: list all forms ──────────────────────────────────────────────
    public function index()
    {
        $user = auth()->user();
        $forms = Form::where('workspace_id', $user->current_workspace_id)
            ->withCount(['fields', 'submissions'])
            ->with('creator:id,name')
            ->latest()
            ->get();

        return Inertia::render('forms/Index', ['forms' => $forms]);
    }

    // ── Admin: create form ─────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $user = auth()->user();
        $form = Form::create([
            'workspace_id' => $user->current_workspace_id,
            'created_by'   => $user->id,
            'title'        => $request->title,
            'description'  => $request->description,
            'is_active'    => true,
        ]);

        return redirect()->route('forms.builder', $form->id)
            ->with('success', 'Form created. Add your fields below.');
    }

    // ── Admin: builder page (fields editor) ───────────────────────────────
    public function builder(Form $form)
    {
        $this->authorizeForm($form);
        $form->load('fields');
        return Inertia::render('forms/Builder', ['form' => $form]);
    }

    // ── Admin: update form meta ────────────────────────────────────────────
    public function update(Request $request, Form $form)
    {
        $this->authorizeForm($form);
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
        ]);
        $form->update($request->only('title', 'description', 'is_active'));
        return back()->with('success', 'Form updated.');
    }

    // ── Admin: save fields (replaces all) ─────────────────────────────────
    public function saveFields(Request $request, Form $form)
    {
        $this->authorizeForm($form);
        $request->validate([
            'fields'                => 'required|array',
            'fields.*.type'         => 'required|in:text,email,number,textarea,checkbox,select,date',
            'fields.*.label'        => 'required|string|max:255',
            'fields.*.placeholder'  => 'nullable|string|max:255',
            'fields.*.required'     => 'boolean',
            'fields.*.options'      => 'nullable|array',
            'fields.*.options.*'    => 'string|max:255',
        ]);

        // Replace all fields
        $form->fields()->delete();
        foreach ($request->fields as $i => $f) {
            FormField::create([
                'form_id'     => $form->id,
                'type'        => $f['type'],
                'label'       => $f['label'],
                'placeholder' => $f['placeholder'] ?? null,
                'required'    => $f['required'] ?? false,
                'options'     => $f['options'] ?? null,
                'sort_order'  => $i,
            ]);
        }

        return back()->with('success', 'Fields saved.');
    }

    // ── Admin: delete form ─────────────────────────────────────────────────
    public function destroy(Form $form)
    {
        $this->authorizeForm($form);
        $form->delete();
        return back()->with('success', 'Form deleted.');
    }

    // ── Admin: view submissions ────────────────────────────────────────────
    public function submissions(Form $form)
    {
        $this->authorizeForm($form);
        $form->load('fields');
        $submissions = FormSubmission::where('form_id', $form->id)
            ->latest()
            ->get();

        return Inertia::render('forms/Submissions', [
            'form'        => $form,
            'submissions' => $submissions,
        ]);
    }

    // ── Admin: delete single submission ───────────────────────────────────
    public function deleteSubmission(Form $form, FormSubmission $submission)
    {
        $this->authorizeForm($form);
        abort_if($submission->form_id !== $form->id, 404);
        $submission->delete();
        return back()->with('success', 'Submission deleted.');
    }

    private function authorizeForm(Form $form): void
    {
        $user = auth()->user();
        if ($form->workspace_id !== $user->current_workspace_id) {
            abort(404);
        }
    }
}
