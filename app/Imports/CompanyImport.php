<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Plan;
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

class CompanyImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure
{
    use Importable, SkipsErrors, SkipsFailures;

    protected $importedCount = 0;
    protected $skippedCount = 0;
    protected $errors = [];

    public function model(array $row)
    {
        // Check for duplicate email
        if (User::where('email', $row['email'])->where('type', 'company')->exists()) {
            $this->skippedCount++;
            return null;
        }

        // Get default plan
        $defaultPlan = Plan::where('is_default', true)->first();
        
        $company = new User([
            'name' => $row['name'],
            'email' => $row['email'],
            'type' => 'company',
            'status' => $row['status'] ?? 'active',
            'is_enable_login' => ($row['status'] ?? 'active') === 'active' ? 1 : 0,
        ]);

        // Set creator language if available
        $creator = auth()->user();
        if ($creator && $creator->lang) {
            $company->lang = $creator->lang;
        }

        // Set default plan if available
        if ($defaultPlan) {
            $company->plan_id = $defaultPlan->id;
            
            if ($defaultPlan->duration === 'yearly') {
                $company->plan_expire_date = now()->addYear();
            } else {
                $company->plan_expire_date = now()->addMonth();
            }
            
            $company->plan_is_active = 1;
        }

        $this->importedCount++;

        // defaultRoleAndSetting() assigns the Spatie role and creates the
        // default workspace, both of which need a real primary key - unlike
        // CompanyController::store(), ToModel::model() is expected to return
        // an unsaved model (Laravel Excel saves it after this method
        // returns), so save it ourselves first or those inserts get a null
        // user_id/owner_id.
        $company->save();

        if (function_exists('defaultRoleAndSetting')) {
            defaultRoleAndSetting($company);
        }

        return $company;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where(function ($query) {
                    return $query->where('type', 'company');
                })
            ],
            'status' => 'nullable|in:active,inactive',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'name.required' => 'Company name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.unique' => 'A company with this email already exists.',
            'status.in' => 'Status must be either active or inactive.',
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
}