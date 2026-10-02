<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ExportImportController extends Controller
{
    protected $exportClasses = [
        'companies' => \App\Exports\CompanyExport::class,
        'projects' => \App\Exports\ProjectExport::class,
        'invoices' => \App\Exports\InvoiceExport::class,
    ];

    protected $importClasses = [
        'companies' => \App\Imports\CompanyImport::class,
        'projects' => \App\Imports\ProjectImport::class,
    ];

    public function export(Request $request, $type = null)
    {
        // Detect type from URL path
        if (!$type) {
            $path = $request->path();
            if (str_contains($path, 'companies/export')) {
                $type = 'companies';
            } elseif (str_contains($path, 'projects/export')) {
                $type = 'projects';
            } elseif (str_contains($path, 'invoices/export')) {
                $type = 'invoices';
            } else {
                $type = $request->route()->parameter('type');
            }
        }
        
        $this->checkPermissions($type, 'view');
        
        if (!isset($this->exportClasses[$type])) {
            return response()->json(['error' => 'Export type not supported'], 400);
        }

        $filename = $type . '_export_' . date('Y-m-d_H-i-s') . '.xlsx';
        $exportClass = new $this->exportClasses[$type]($request);

        return Excel::download($exportClass, $filename);
    }

    public function import(Request $request, $type = null)
    {
        // Detect type from URL path
        if (!$type) {
            $path = $request->path();
            if (str_contains($path, 'companies/import')) {
                $type = 'companies';
            } elseif (str_contains($path, 'projects/import')) {
                $type = 'projects';
            } else {
                $type = $request->route()->parameter('type');
            }
        }
        
        $this->checkPermissions($type, 'create');
        
        $validator = Validator::make($request->all(), [
            'file' => 'nullable|mimes:xlsx,xls,csv|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!isset($this->importClasses[$type])) {
            return response()->json(['error' => 'Import type not supported'], 400);
        }

        // Handle import from session data (from previous file upload)
        $csvData = session('file_data');
        $headers = session('file_header');
        $mappingData = $request->input('data');

        if (!$csvData || !$headers || !$mappingData) {
            return response()->json(['error' => 'No import data found. Please upload a file first.'], 400);
        }

        try {
            $imported = 0;
            $errors = [];
            $table = session('import_table', $type);

            $seen = [];
            $skipped = 0;
            foreach ($csvData as $rowIndex => $row) {
                try {
                    $mappedData = [];
                    foreach ($mappingData as $field => $columnIndex) {
                        $mappedData[$field] = $row[$columnIndex] ?? null;
                    }

                    $uniqueKey = $this->getUniqueKeyForTable($table, $mappedData);
                    
                    if (isset($seen[$uniqueKey])) {
                        $skipped++;
                        continue;
                    }
                    $seen[$uniqueKey] = true;
                    
                    if ($this->checkDuplicateRecord($table, $mappedData)) {
                        $skipped++;
                        continue;
                    }

                    $this->createRecord($table, $mappedData);
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
                }
            }

            session()->forget(['file_data', 'file_header', 'import_table']);

            if (count($errors) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Import completed with errors',
                    'errors' => $errors
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => "{$imported} {$type} imported successfully" . ($skipped > 0 ? ", {$skipped} duplicates skipped" : ""),
                'data' => [
                    'imported_count' => $imported,
                    'skipped_count' => $skipped
                ]
            ], 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = 'Row ' . $failure->row() . ': ' . implode(', ', $failure->errors());
            }
            return response()->json(['error' => 'Validation failed: ' . implode('; ', $errors)], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    public function getTemplate($type = null)
    {
        // Detect type from URL path
        if (!$type) {
            $path = request()->path();
            if (str_contains($path, 'companies/template')) {
                $type = 'companies';
            } elseif (str_contains($path, 'projects/template')) {
                $type = 'projects';
            } else {
                $type = request()->route()->parameter('type');
            }
        }
      
        try {
            $this->checkPermissions($type, 'view');

            $tableFields = $this->getTableWiseFields($type);
            if (!$tableFields['status']) {
                return response()->json(['error' => 'Template type not supported'], 400);
            }

            $fields = $tableFields['fields'];
            $filename = 'sample_' . $type . '.csv';

            return response()->streamDownload(function () use ($fields) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $fields);
                fclose($file);
            }, $filename, [
                'Content-Type' => 'text/csv',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Template download failed: ' . $e->getMessage()], 500);
        }
    }

    private function checkPermissions($type, $action)
    {

        $permissionMap = [
            'companies' => [
                'view' => 'company_view_any',
                'create' => 'company_create'
            ],
            'projects' => [
                'view' => 'project_view_any',
                'create' => 'project_create'
            ],
            'invoices' => [
                'view' => 'invoice_view_any',
                'create' => 'invoice_create'
            ]
        ];

        if (isset($permissionMap[$type][$action])) {
            $permission = $permissionMap[$type][$action];
    
            if (!auth()->user() || !auth()->user()->can($permission)) {
                abort(403, 'User does not have the right permissions.');
            }
        }
        
    }

    public function fileImport(Request $request)
    {
        $error = '';
        $html = '';
        $fields = [];

        if ($request->file && $request->file->getClientOriginalName() != '') {
            $file_array = explode(".", $request->file->getClientOriginalName());
            $extension = strtolower(end($file_array));

            if (in_array($extension, ['csv', 'xlsx', 'xls'])) {
                $tableFields = $this->getTableWiseFields($request->table);
                if ($tableFields['error'] != '') {
                    $error = $tableFields['error'];
                } else {
                    $fields = $tableFields['fields'];
                }

                try {
                    // Handle Excel files using Laravel Excel
                    $data = Excel::toArray([], $request->file)[0];
                    $file_header = array_shift($data);

                    $temp_data = [];
                    foreach (array_slice($data, 0, 5) as $row) {
                        $html .= '<tr>';
                        for ($count = 0; $count < count($row); $count++) {
                            $html .= '<td>' . ($row[$count] ?? '') . '</td>';
                        }
                        $html .= '</tr>';
                    }
                    
                    foreach ($data as $row) {
                        $temp_data[] = $row;
                    }

                    session(['file_data' => $temp_data]);
                    session(['file_header' => $file_header]);
                    session(['import_table' => $request->table]);
                } catch (\Exception $e) {
                    $error = 'Error processing file: ' . $e->getMessage();
                }
            } else {
                $error = 'Only <b>.csv, .xlsx, .xls</b> files allowed';
            }
        } else {
            $error = 'Please Select File';
        }

        return response()->json([
            'error' => $error,
            'output' => $html,
            'fields' => $fields,
        ]);
    }

    public function fileImportModal(Request $request)
    {
        $fields = [];
        $tableFields = $this->getTableWiseFields($request->table);
        if ($tableFields['error'] != '') {
            $error = $tableFields['error'];
        } else {
            $fields = json_encode($tableFields['fields']);
        }

        return response()->json([
            'fields' => $fields,
            'headers' => session('file_header', []),
            'data' => session('file_data', [])
        ]);
    }

    public function importData(Request $request)
    {
        $data = $request->input('data');
        $csvData = session('file_data');
        $headers = session('file_header');

        if (!$csvData || !$headers) {
            return response()->json(['html' => false, 'response' => 'No file data found'], 400);
        }

        try {
            $imported = 0;
            $errors = [];
            $table = session('import_table', 'companies');

            $seen = [];
            $skipped = 0;
            foreach ($csvData as $rowIndex => $row) {
                try {
                    $mappedData = [];
                    foreach ($data as $field => $columnIndex) {
                        $mappedData[$field] = $row[$columnIndex] ?? null;
                    }

                    $uniqueKey = $this->getUniqueKeyForTable($table, $mappedData);
                    
                    if (isset($seen[$uniqueKey])) {
                        $skipped++;
                        continue;
                    }
                    $seen[$uniqueKey] = true;
                    
                    if ($this->checkDuplicateRecord($table, $mappedData)) {
                        $skipped++;
                        continue;
                    }

                    $this->createRecord($table, $mappedData);
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
                }
            }

            session()->forget(['file_data', 'file_header', 'import_table']);

            if (count($errors) > 0) {
                return response()->json([
                    'html' => true,
                    'response' => implode('<br>', $errors)
                ]);
            }

            return response()->json([
                'html' => false,
                'response' => $imported . ' records imported successfully' . ($skipped > 0 ? ', ' . $skipped . ' duplicates skipped' : '')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'html' => true,
                'response' => 'Import failed: ' . $e->getMessage()
            ]);
        }
    }

    private function getTableWiseFields($table)
    {
        $error = '';
        switch ($table) {
            case 'companies':
                $tableFields = ['name', 'email', 'password', 'status', 'plan', 'created_at', 'updated_at'];
                break;
            case 'projects':
                $tableFields = ['title', 'description', 'status', 'priority', 'start_date', 'deadline', 'estimated_hours', 'is_public', 'created_at', 'updated_at'];
                break;
            default:
                $error = 'Something went wrong!';
                $tableFields = [];
                break;
        }

        return [
            'fields' => $tableFields,
            'error' => $error,
            'status' => empty($error)
        ];
    }

    private function getUniqueKeyForTable($table, $data)
    {
        switch ($table) {
            case 'companies':
                return strtolower(trim($data['email'] ?? ''));
            case 'projects':
                $currentUser = auth()->user();
                $workspace = $currentUser->currentWorkspace;
                return strtolower(trim($data['title'] ?? '')) . '_' . ($workspace ? $workspace->id : 'no_workspace');
            default:
                return '';
        }
    }

    private function checkDuplicateRecord($type, $data)
    {
        switch ($type) {
            case 'companies':
                if (empty($data['email'])) return false;
                return \App\Models\User::where('email', $data['email'])
                    ->where('type', 'company')
                    ->exists();
            case 'projects':
                if (empty($data['title'])) return false;
                $currentUser = auth()->user();
                $workspace = $currentUser->currentWorkspace;
                if (!$workspace) return false;
                return \App\Models\Project::where('title', $data['title'])
                    ->where('workspace_id', $workspace->id)
                    ->exists();
            default:
                return false;
        }
    }

    private function createRecord($type, $data)
    {
        switch ($type) {
            case 'companies':
                $company = new \App\Models\User();
                $company->name = $data['name'] ?? '';
                $company->email = $data['email'] ?? '';
                $company->type = 'company';
                $company->status = $data['status'] ?? 'active';
                $company->is_enable_login = ($data['status'] ?? 'active') === 'active' ? 1 : 0;
                
                // Handle password - hash if provided, otherwise set default
                if (!empty($data['password'])) {
                    $company->password = \Hash::make($data['password']);
                } else {
                    $company->password = \Hash::make('1234'); // Default password
                }
                
                // Set creator language if available
                $creator = auth()->user();
                if ($creator && $creator->lang) {
                    $company->lang = $creator->lang;
                }
                
                // Handle plan - find by name or use default
                if (!empty($data['plan'])) {
                    $plan = \App\Models\Plan::where('name', $data['plan'])->first();
                    if (!$plan) {
                        $plan = \App\Models\Plan::first(); // Use first plan if not found
                    }
                } else {
                    $plan = \App\Models\Plan::where('is_default', true)->first() ?? \App\Models\Plan::first();
                }
                
                if ($plan) {
                    $company->plan_id = $plan->id;
                    
                    // Auto-calculate plan expiry date based on plan duration
                    if ($plan->duration === 'yearly') {
                        $company->plan_expire_date = now()->addYear();
                    } else {
                        $company->plan_expire_date = now()->addMonth();
                    }
                    
                    $company->plan_is_active = 1;
                }
                
                // Handle timestamps if provided
                if (!empty($data['created_at'])) {
                    $company->created_at = \Carbon\Carbon::parse($data['created_at']);
                }
                if (!empty($data['updated_at'])) {
                    $company->updated_at = \Carbon\Carbon::parse($data['updated_at']);
                }
                
                $company->save();
                
                // Set up default roles and settings
                if (function_exists('defaultRoleAndSetting')) {
                    defaultRoleAndSetting($company);
                }
                break;
            case 'projects':
                $currentUser = auth()->user();
                $workspace = $currentUser->currentWorkspace;
                
                if (!$workspace) {
                    throw new \Exception('No active workspace found');
                }
                
                $project = new \App\Models\Project();
                $project->workspace_id = $workspace->id;
                $project->title = $data['title'] ?? '';
                $project->description = $data['description'] ?? null;
                $project->status = $this->validateProjectStatus($data['status'] ?? 'planning');
                $project->priority = $this->validateProjectPriority($data['priority'] ?? 'medium');
                $project->start_date = $this->parseProjectDate($data['start_date'] ?? null);
                $project->deadline = $this->parseProjectDate($data['deadline'] ?? null);
                $project->estimated_hours = is_numeric($data['estimated_hours'] ?? null) ? (int)$data['estimated_hours'] : null;
                $project->is_public = $this->parseProjectBoolean($data['is_public'] ?? 'No');
                $project->created_by = $currentUser->id;
                $project->updated_by = $currentUser->id;
                
                // Handle timestamps if provided
                if (!empty($data['created_at'])) {
                    $project->created_at = \Carbon\Carbon::parse($data['created_at']);
                }
                if (!empty($data['updated_at'])) {
                    $project->updated_at = \Carbon\Carbon::parse($data['updated_at']);
                }
                
                $project->save();
                
                // Log activity
                $project->logActivity('project_imported', "Project '{$project->title}' imported from Excel");
                break;
            default:
                throw new \Exception('Unsupported table type');
        }
    }

    private function validateProjectStatus($status)
    {
        $validStatuses = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];
        return in_array(strtolower($status), $validStatuses) ? strtolower($status) : 'planning';
    }

    private function validateProjectPriority($priority)
    {
        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        return in_array(strtolower($priority), $validPriorities) ? strtolower($priority) : 'medium';
    }

    private function parseProjectDate($date)
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

    private function parseProjectBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        
        $value = strtolower(trim($value));
        return in_array($value, ['yes', 'true', '1', 'on']);
    }

    private function assignProjectClients($project, $clientEmails)
    {
        $emails = array_map('trim', explode(',', $clientEmails));
        $currentUser = auth()->user();

        foreach ($emails as $email) {
            if (empty($email)) continue;

            $client = \App\Models\User::where('email', $email)
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