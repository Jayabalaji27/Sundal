<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\BudgetCategory;
use App\Models\ExpenseApproval;
use App\Models\User;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    public function run()
    {
        $projects = Project::with(['workspace.owner', 'members.user', 'tasks'])->get();

        foreach ($projects as $project) {
            $budget = \App\Models\ProjectBudget::where('project_id', $project->id)->with('categories')->first();
            
            if (!$budget || $budget->categories->isEmpty()) {
                continue;
            }
            
            $owner = $project->workspace->owner;
            $categories = $budget->categories;
            $projectMembers = $project->members->pluck('user')->filter();
            $projectTasks = $project->tasks;
            
            // Ensure we have valid project members
            if ($projectMembers->isEmpty()) {
                $projectMembers = collect([$owner]);
            }
            
            // Create more expenses for completed projects
            $expenseCount = $project->status === 'completed' ? rand(15, 25) : rand(8, 15);
            
            for ($i = 0; $i < $expenseCount; $i++) {
                $expense = $this->getExpenseData($project->title, $i);
                $category = $this->getBestCategory($categories, $expense['category_type']);
                
                // Always assign a task ID (no null values)
                $taskId = $projectTasks->isNotEmpty() ? $projectTasks->random()->id : null;
                
                // Skip if no tasks available
                if (!$taskId) {
                    continue;
                }
                
                // Get a valid submitter
                $submitterId = $projectMembers->random()->id;
                
                // For completed projects, all expenses should be approved
                $expenseStatus = $project->status === 'completed' ? 'approved' : $expense['status'];
                
                $projectExpense = ProjectExpense::create([
                    'project_id' => $project->id,
                    'budget_category_id' => $category->id,
                    'task_id' => $taskId,
                    'submitted_by' => $submitterId,
                    'amount' => $expense['amount'],
                    'currency' => 'USD',
                    'expense_date' => now()->subDays(rand(1, 90)),
                    'title' => $expense['title'],
                    'description' => $expense['description'],
                    'vendor' => $expense['vendor'],
                    'status' => $expenseStatus,
                    'is_recurring' => $expense['is_recurring'],
                    'receipt_required' => $expense['receipt_required']
                ]);
                
                // Create approval record for processed expenses
                if (in_array($expenseStatus, ['approved', 'rejected'])) {
                    ExpenseApproval::create([
                        'project_expense_id' => $projectExpense->id,
                        'approver_id' => $owner->id,
                        'status' => $expenseStatus,
                        'notes' => $this->getApprovalComment($expenseStatus),
                        'approved_at' => $expenseStatus === 'approved' ? now()->subDays(rand(1, 7)) : null
                    ]);
                }
            }
        }
    }
    
    private function getExpenseData(string $projectTitle, int $index): array
    {
        $expenses = [
            // Development expenses
            [
                'title' => 'AWS Cloud Infrastructure',
                'amount' => rand(500, 1500),
                'description' => 'Monthly cloud hosting and infrastructure costs',
                'vendor' => 'Amazon Web Services',
                'category_type' => 'infrastructure',
                'is_recurring' => true,
                'receipt_required' => true
            ],
            [
                'title' => 'Development IDE Licenses',
                'amount' => rand(200, 800),
                'description' => 'Annual licenses for development tools and IDEs',
                'vendor' => 'JetBrains',
                'category_type' => 'development',
                'is_recurring' => false,
                'receipt_required' => true
            ],
            [
                'title' => 'Third-party API Subscription',
                'amount' => rand(100, 500),
                'description' => 'Monthly subscription for external API services',
                'vendor' => 'Various API Providers',
                'category_type' => 'development',
                'is_recurring' => true,
                'receipt_required' => true
            ],
            [
                'title' => 'SSL Certificates',
                'amount' => rand(50, 200),
                'description' => 'SSL certificates for secure connections',
                'vendor' => 'DigiCert',
                'category_type' => 'infrastructure',
                'is_recurring' => false,
                'receipt_required' => true
            ],
            [
                'title' => 'Database Hosting',
                'amount' => rand(300, 800),
                'description' => 'Managed database hosting services',
                'vendor' => 'MongoDB Atlas',
                'category_type' => 'infrastructure',
                'is_recurring' => true,
                'receipt_required' => true
            ],
            // Design expenses
            [
                'title' => 'Design Software License',
                'amount' => rand(600, 1200),
                'description' => 'Adobe Creative Suite annual subscription',
                'vendor' => 'Adobe Inc.',
                'category_type' => 'design',
                'is_recurring' => false,
                'receipt_required' => true
            ],
            [
                'title' => 'Stock Photos and Assets',
                'amount' => rand(100, 400),
                'description' => 'Premium stock photos and design assets',
                'vendor' => 'Shutterstock',
                'category_type' => 'design',
                'is_recurring' => false,
                'receipt_required' => true
            ],
            [
                'title' => 'UI/UX Design Tools',
                'amount' => rand(150, 300),
                'description' => 'Figma and design collaboration tools',
                'vendor' => 'Figma',
                'category_type' => 'design',
                'is_recurring' => true,
                'receipt_required' => true
            ],
            // Testing expenses
            [
                'title' => 'Testing Tools License',
                'amount' => rand(200, 600),
                'description' => 'Automated testing and QA tools',
                'vendor' => 'Selenium Grid',
                'category_type' => 'testing',
                'is_recurring' => false,
                'receipt_required' => true
            ],
            [
                'title' => 'Performance Testing Service',
                'amount' => rand(300, 800),
                'description' => 'Load testing and performance analysis',
                'vendor' => 'LoadRunner',
                'category_type' => 'testing',
                'is_recurring' => false,
                'receipt_required' => true
            ],
            // Project management expenses
            [
                'title' => 'Project Management Software',
                'amount' => rand(100, 300),
                'description' => 'Monthly subscription for project management tools',
                'vendor' => 'Atlassian',
                'category_type' => 'management',
                'is_recurring' => true,
                'receipt_required' => true
            ],
            [
                'title' => 'Team Communication Tools',
                'amount' => rand(50, 200),
                'description' => 'Slack premium subscription for team communication',
                'vendor' => 'Slack Technologies',
                'category_type' => 'management',
                'is_recurring' => true,
                'receipt_required' => true
            ],
            // Travel and meeting expenses
            [
                'title' => 'Client Meeting Travel',
                'amount' => rand(400, 1200),
                'description' => 'Travel expenses for client meetings and presentations',
                'vendor' => 'Various Travel Providers',
                'category_type' => 'management',
                'is_recurring' => false,
                'receipt_required' => true
            ],
            [
                'title' => 'Conference and Training',
                'amount' => rand(500, 1500),
                'description' => 'Professional development and conference attendance',
                'vendor' => 'Various Conference Organizers',
                'category_type' => 'management',
                'is_recurring' => false,
                'receipt_required' => true
            ],
            // Hardware expenses
            [
                'title' => 'Development Hardware',
                'amount' => rand(800, 2500),
                'description' => 'Laptops and development equipment',
                'vendor' => 'Apple/Dell',
                'category_type' => 'infrastructure',
                'is_recurring' => false,
                'receipt_required' => true
            ]
        ];
        
        $expense = $expenses[$index % count($expenses)];
        $expense['status'] = $this->getExpenseStatus();
        
        return $expense;
    }
    
    private function getBestCategory($categories, string $categoryType)
    {
        // Try to match category type to actual category name
        $categoryMap = [
            'development' => ['Development', 'Software'],
            'design' => ['Design', 'Design & UX'],
            'testing' => ['Testing', 'Testing & QA', 'Quality Assurance'],
            'infrastructure' => ['Infrastructure', 'Server', 'Hosting'],
            'management' => ['Project Management', 'Management', 'Administration']
        ];
        
        $preferredNames = $categoryMap[$categoryType] ?? [];
        
        foreach ($preferredNames as $name) {
            $category = $categories->first(function($cat) use ($name) {
                return stripos($cat->name, $name) !== false;
            });
            
            if ($category) {
                return $category;
            }
        }
        
        // Fallback to random category
        return $categories->random();
    }
    
    private function getExpenseStatus(): string
    {
        $statuses = ['approved', 'approved', 'approved', 'pending', 'rejected', 'requires_info'];
        return $statuses[array_rand($statuses)];
    }
    
    private function getApprovalComment(string $status): ?string
    {
        if ($status === 'rejected') {
            $comments = [
                'Expense amount exceeds budget allocation for this category.',
                'Receipt required for approval. Please resubmit with proper documentation.',
                'Vendor not on approved list. Please use pre-approved vendors.',
                'Expense not related to current project scope.'
            ];
            return $comments[array_rand($comments)];
        }
        
        if ($status === 'approved') {
            $comments = [
                'Expense approved. All documentation is in order.',
                'Approved as per project budget allocation.',
                'Legitimate business expense. Approved for payment.',
                null, null // Sometimes no comment
            ];
            return $comments[array_rand($comments)];
        }
        
        return null;
    }
}