<?php

namespace App\Imports;

use App\Models\Project;
use App\Models\User;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Validators\Failure;
use Illuminate\Validation\Rule;

class ProjectImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure
{
    use Importable, SkipsErrors, SkipsFailures;

    protected $importedCount = 0;
    protected $skippedCount = 0;
    protected $errors = [];

    public function model(array $row)
    {
        $currentUser = auth()->user();
        $workspace = $currentUser->currentWorkspace;

        if (!$workspace) {
            $this->errors[] = 'No active workspace found';
            return null;
        }

        // Check for duplicate project title in the same workspace
        if (Project::where('title', $row['title'])
                  ->where('workspace_id', $workspace->id)
                  ->exists()) {
            $this->skippedCount++;
            return null;
        }

        $project = new Project([
            'workspace_id' => $workspace->id,
            'title' => $row['title'],
            'description' => $row['description'] ?? null,
            'status' => $this->validateStatus($row['status'] ?? 'planning'),
            'priority' => $this->validatePriority($row['priority'] ?? 'medium'),
            'start_date' => $this->parseDate($row['start_date'] ?? null),
            'deadline' => $this->parseDate($row['deadline'] ?? null),
            'estimated_hours' => is_numeric($row['estimated_hours'] ?? null) ? (int)$row['estimated_hours'] : null,
            'budget' => is_numeric($row['budget'] ?? null) ? (float)$row['budget'] : null,
            'progress' => is_numeric($row['progress'] ?? 0) ? max(0, min(100, (int)$row['progress'])) : 0,
            'is_public' => $this->parseBoolean($row['is_public'] ?? 'No'),
            'created_by' => $currentUser->id,
            'updated_by' => $currentUser->id,
        ]);

        $project->save();

        // Handle client assignments if provided
        if (!empty($row['client_emails'])) {
            $this->assignClients($project, $row['client_emails']);
        }

        $this->importedCount++;
        
        // Log activity
        $project->logActivity('project_imported', "Project '{$project->title}' imported from Excel");
        
        return $project;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:planning,active,on_hold,completed,cancelled',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'deadline' => 'nullable|date',
            'estimated_hours' => 'nullable|integer|min:0',
            'budget' => 'nullable|numeric|min:0',
            'progress' => 'nullable|integer|min:0|max:100',
            'is_public' => 'nullable|string',
            'client_emails' => 'nullable|string',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'title.required' => 'Project title is required.',
            'title.max' => 'Project title cannot exceed 255 characters.',
            'status.in' => 'Status must be one of: planning, active, on_hold, completed, cancelled.',
            'priority.in' => 'Priority must be one of: low, medium, high, urgent.',
            'start_date.date' => 'Start date must be a valid date.',
            'deadline.date' => 'Deadline must be a valid date.',
            'estimated_hours.integer' => 'Estimated hours must be a number.',
            'estimated_hours.min' => 'Estimated hours cannot be negative.',
            'budget.numeric' => 'Budget must be a number.',
            'budget.min' => 'Budget cannot be negative.',
            'progress.integer' => 'Progress must be a number.',
            'progress.min' => 'Progress cannot be less than 0.',
            'progress.max' => 'Progress cannot be more than 100.',
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function onError(\Throwable $error)
    {
        $this->errors[] = $error->getMessage();
    }

    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $this->errors[] = 'Row ' . $failure->row() . ': ' . implode(', ', $failure->errors());
        }
    }

    private function validateStatus($status)
    {
        $validStatuses = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];
        return in_array(strtolower($status), $validStatuses) ? strtolower($status) : 'planning';
    }

    private function validatePriority($priority)
    {
        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        return in_array(strtolower($priority), $validPriorities) ? strtolower($priority) : 'medium';
    }

    private function parseDate($date)
    {
        if (empty($date)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        
        $value = strtolower(trim($value));
        return in_array($value, ['yes', 'true', '1', 'on']);
    }

    private function assignClients($project, $clientEmails)
    {
        $emails = array_map('trim', explode(',', $clientEmails));
        $currentUser = auth()->user();
        $workspace = $currentUser->currentWorkspace;

        foreach ($emails as $email) {
            if (empty($email)) continue;

            $client = User::where('email', $email)
                         ->where('type', 'client')
                         ->first();

            if ($client) {
                // Check if client is already assigned to avoid duplicates
                if (!$project->clients()->where('user_id', $client->id)->exists()) {
                    $project->clients()->attach($client->id, [
                        'assigned_at' => now(),
                        'assigned_by' => $currentUser->id,
                    ]);
                }
            }
        }
    }
}