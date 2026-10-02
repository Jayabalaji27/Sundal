<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\BudgetCategory;
use App\Models\BudgetRevision;
use App\Models\User;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    public function run()
    {
        $projects = Project::with('workspace')->take(20)->get();
        $projectsWithoutBudget = rand(5, 7); // Skip budget for 5-7 projects
        $skippedProjects = $projects->random($projectsWithoutBudget)->pluck('id')->toArray();

        foreach ($projects as $project) {
            // Skip budget creation for selected projects
            if (in_array($project->id, $skippedProjects)) {
                continue;
            }
            $owner = $project->workspace->owner;
            
            // Create project budget
            $totalBudget = $this->getBudgetAmount($project->title);
            
            $budget = ProjectBudget::create([
                'project_id' => $project->id,
                'workspace_id' => $project->workspace_id,
                'total_budget' => $totalBudget,
                'period_type' => 'project',
                'start_date' => $project->start_date,
                'end_date' => $project->deadline,
                'description' => "Budget allocation for {$project->title}",
                'status' => $project->status === 'completed' ? 'completed' : 'active',
                'created_by' => $owner->id
            ]);

            // Create budget categories based on project type
            $categories = $this->getBudgetCategories($project->title, $totalBudget);

            foreach ($categories as $index => $category) {
                BudgetCategory::create([
                    'project_budget_id' => $budget->id,
                    'name' => $category['name'],
                    'allocated_amount' => $category['allocated_amount'],
                    'color' => $category['color'],
                    'description' => $category['description'],
                    'sort_order' => $index + 1
                ]);
            }
            
            // Create budget revision for some projects
            if (rand(0, 2) === 0) { // 33% chance
                BudgetRevision::create([
                    'project_budget_id' => $budget->id,
                    'revised_by' => $owner->id,
                    'previous_amount' => $totalBudget,
                    'new_amount' => $totalBudget * (rand(110, 130) / 100), // 10-30% increase
                    'reason' => $this->getRevisionReason(),
                    'status' => 'approved',
                    'approved_by' => $owner->id,
                    'approved_at' => now()->subDays(rand(1, 15))
                ]);
            }
        }
    }
    
    private function getBudgetAmount(string $projectTitle): float
    {
        // Different project types have different budget ranges
        if (stripos($projectTitle, 'E-Commerce') !== false || stripos($projectTitle, 'Platform') !== false) {
            return rand(80000, 150000);
        }
        
        if (stripos($projectTitle, 'Mobile') !== false || stripos($projectTitle, 'App') !== false) {
            return rand(60000, 120000);
        }
        
        if (stripos($projectTitle, 'Migration') !== false || stripos($projectTitle, 'Infrastructure') !== false) {
            return rand(40000, 80000);
        }
        
        if (stripos($projectTitle, 'Integration') !== false || stripos($projectTitle, 'API') !== false) {
            return rand(30000, 60000);
        }
        
        return rand(25000, 75000); // Default range
    }
    
    private function getBudgetCategories(string $projectTitle, float $totalBudget): array
    {
        $baseCategories = [
            'Development' => ['percentage' => 0.45, 'color' => '#3B82F6', 'description' => 'Software development and coding costs'],
            'Design & UX' => ['percentage' => 0.15, 'color' => '#8B5CF6', 'description' => 'User interface and experience design'],
            'Testing & QA' => ['percentage' => 0.12, 'color' => '#10B981', 'description' => 'Quality assurance and testing activities'],
            'Infrastructure' => ['percentage' => 0.10, 'color' => '#F59E0B', 'description' => 'Server, hosting, and infrastructure costs'],
            'Project Management' => ['percentage' => 0.08, 'color' => '#EF4444', 'description' => 'Project management and coordination'],
            'Documentation' => ['percentage' => 0.05, 'color' => '#6B7280', 'description' => 'Technical and user documentation'],
            'Contingency' => ['percentage' => 0.05, 'color' => '#84CC16', 'description' => 'Buffer for unexpected costs']
        ];
        
        // Adjust categories based on project type
        if (stripos($projectTitle, 'E-Commerce') !== false) {
            $baseCategories['Payment Integration'] = ['percentage' => 0.08, 'color' => '#F97316', 'description' => 'Payment gateway and security integration'];
            $baseCategories['Development']['percentage'] = 0.37;
        }
        
        if (stripos($projectTitle, 'Mobile') !== false) {
            $baseCategories['App Store'] = ['percentage' => 0.03, 'color' => '#EC4899', 'description' => 'App store fees and submission costs'];
            $baseCategories['Development']['percentage'] = 0.42;
        }
        
        if (stripos($projectTitle, 'Migration') !== false) {
            $baseCategories['Data Migration'] = ['percentage' => 0.15, 'color' => '#06B6D4', 'description' => 'Data migration and validation costs'];
            $baseCategories['Development']['percentage'] = 0.30;
        }
        
        $categories = [];
        foreach ($baseCategories as $name => $config) {
            $categories[] = [
                'name' => $name,
                'allocated_amount' => round($totalBudget * $config['percentage'], 2),
                'color' => $config['color'],
                'description' => $config['description']
            ];
        }
        
        return $categories;
    }
    
    private function getRevisionReason(): string
    {
        $reasons = [
            'Additional features requested by client',
            'Scope expansion due to new requirements',
            'Technical complexity higher than estimated',
            'Third-party integration costs increased',
            'Extended timeline requiring additional resources',
            'Security requirements necessitate additional work',
            'Performance optimization requirements added'
        ];
        
        return $reasons[array_rand($reasons)];
    }
}