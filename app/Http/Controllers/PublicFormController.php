<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicFormController extends Controller
{
    public function show(string $token)
    {
        $form = Form::where('token', $token)->with('fields')->firstOrFail();

        if (!$form->is_active) {
            return Inertia::render('forms/Closed');
        }

        return Inertia::render('forms/Public', ['form' => $form]);
    }

    public function submit(Request $request, string $token)
    {
        $form = Form::where('token', $token)->with('fields')->firstOrFail();

        if (!$form->is_active) {
            abort(403, 'This form is no longer accepting responses.');
        }

        if ($form->fields->isEmpty()) {
            abort(422, 'This form has no fields configured.');
        }

        // Build validation rules from fields, reporting errors by the field's label
        $rules = [];
        $attributes = [];
        foreach ($form->fields as $field) {
            $rule = $field->required ? 'required' : 'nullable';

            match ($field->type) {
                'email'    => $rule .= '|email|max:255',
                'number'   => $rule .= '|numeric',
                'checkbox' => $rule = $field->required ? 'accepted' : 'nullable|boolean',
                'select'   => $rule .= '|string|max:255',
                'date'     => $rule .= '|date',
                default    => $rule .= '|string|max:5000',
            };

            $rules['field_' . $field->id] = $rule;
            $attributes['field_' . $field->id] = $field->label;
        }

        $validated = $request->validate($rules, ['required' => __(':attribute is required.')], $attributes);

        // Store as {field_id => value} map
        $data = [];
        foreach ($form->fields as $field) {
            $key = 'field_' . $field->id;
            $data[$field->id] = $validated[$key] ?? null;
        }

        FormSubmission::create([
            'form_id'    => $form->id,
            'data'       => $data,
            'ip_address' => $request->ip(),
        ]);

        return Inertia::render('forms/ThankYou', ['form' => $form]);
    }
}
