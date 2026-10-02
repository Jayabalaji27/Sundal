<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        // Get existing workspaces, projects, and users
        $workspaces = Workspace::all();
        
        if ($workspaces->isEmpty()) {
            $this->command->info('No workspaces found. Please run WorkspaceSeeder first.');
            return;
        }

        foreach ($workspaces as $workspace) {
            $projects = $workspace->projects()->take(2)->get();
            
            if ($projects->isEmpty()) {
                continue;
            }

            // Get project clients (users assigned to projects as clients)
            $projectClients = collect();
            foreach ($projects as $project) {
                $clients = $project->clients()->get();
                if ($clients->isNotEmpty()) {
                    $projectClients = $projectClients->merge($clients);
                }
            }
            
            // If no project clients, get workspace clients
            if ($projectClients->isEmpty()) {
                $projectClients = $workspace->users()
                    ->whereHas('roles', function($q) {
                        $q->where('name', 'client');
                    })
                    ->get();
            }
            
            // If still no clients, get any workspace users
            if ($projectClients->isEmpty()) {
                $projectClients = $workspace->users()->get();
            }

            if ($projectClients->isEmpty()) {
                continue; // Skip if no users at all
            }

            foreach ($projects as $project) {
                // Get clients specifically for this project
                $availableClients = $project->clients()->get();
                if ($availableClients->isEmpty()) {
                    $availableClients = $projectClients->take(2);
                }
                
                // Create 20-25 invoices with mixed statuses
                $invoiceCount = random_int(20, 25);
                
                for ($i = 0; $i < $invoiceCount; $i++) {
                    $client = $availableClients->random();
                    $invoiceDate = now()->addDays(random_int(-60, 0));
                    $dueDate = $invoiceDate->copy()->addDays(random_int(15, 45));
                    
                    // Mix of statuses - more realistic distribution
                    $statusOptions = ['draft', 'sent', 'viewed', 'paid', 'paid', 'paid']; // More paid invoices
                    $status = $statusOptions[array_rand($statusOptions)];
                        
                        $invoice = Invoice::create([
                        'project_id' => $project->id,
                        'workspace_id' => $workspace->id,
                        'client_id' => $client->id,
                        'created_by' => $project->created_by,
                        'title' => $this->generateInvoiceTitle($project->title, $i + 1),
                        'description' => $this->generateInvoiceDescription(),
                        'invoice_date' => $invoiceDate,
                        'due_date' => $dueDate,
                        'tax_rate' => random_int(0, 1) ? random_int(5, 15) : 0,
                        'discount_amount' => random_int(0, 1) ? random_int(50, 200) : 0,
                        'total_amount' => 0, // Temporary, will be calculated later
                        'status' => $status,
                        'paid_amount' => $status === 'paid' ? 0 : 0, // Will be set after total calculation
                        'paid_at' => $status === 'paid' ? $invoiceDate->copy()->addDays(random_int(1, 30)) : null,
                        'payment_method' => $status === 'paid' ? collect(['stripe', 'paypal', 'bank', 'cash'])->random() : null,
                        'payment_reference' => $status === 'paid' ? $this->generatePaymentReference('stripe') : null,
                        'notes' => $this->generateInvoiceNotes(),
                        'terms' => $this->generateInvoiceTerms(),
                        'client_details' => [
                            'name' => $client->name,
                            'email' => $client->email,
                            'address' => $this->generateClientAddress(),
                        ],
                    ]);

                    // Create invoice items first
                    $this->createInvoiceItems($invoice, $project);
                    
                    // Calculate totals
                    $invoice->calculateTotals();
                    
                    // Set paid amount for paid invoices
                    if ($status === 'paid') {
                        $invoice->update([
                            'paid_amount' => $invoice->total_amount
                        ]);
                    }
                }
            }
        }

        $this->command->info('Invoice seeder completed successfully.');
    }



    private function generateInvoiceTitle($projectTitle, $invoiceNumber)
    {
        $titles = [
            "Invoice #{$invoiceNumber} - {$projectTitle}",
            "Monthly Services - {$projectTitle}",
            "Development Work - {$projectTitle} Phase {$invoiceNumber}",
            "Consulting Services - {$projectTitle}",
            "Project Milestone {$invoiceNumber} - {$projectTitle}",
        ];
        
        return $titles[array_rand($titles)];
    }

    private function generateInvoiceDescription()
    {
        $descriptions = [
            'Professional services rendered for project development and implementation.',
            'Consulting and development work completed during the billing period.',
            'Software development services including design, coding, and testing.',
            'Project management and technical consulting services.',
            'Custom software development and system integration services.',
            null, // Some invoices may not have descriptions
        ];
        
        return $descriptions[array_rand($descriptions)];
    }

    private function generateInvoiceNotes()
    {
        $notes = [
            'Thank you for your business. Payment is due within 30 days.',
            'Please remit payment by the due date to avoid late fees.',
            'All work has been completed according to project specifications.',
            'Contact us if you have any questions about this invoice.',
            null, // Some invoices may not have notes
        ];
        
        return collect($notes)->random();
    }

    private function generateInvoiceTerms()
    {
        return "Payment is due within 30 days of invoice date. Late payments may incur a 1.5% monthly service charge. All work is guaranteed for 90 days from completion date.";
    }

    private function generateClientAddress()
    {
        $addresses = [
            "123 Business St\nSuite 100\nNew York, NY 10001",
            "456 Corporate Ave\nFloor 5\nLos Angeles, CA 90210",
            "789 Enterprise Blvd\nBuilding A\nChicago, IL 60601",
            "321 Commerce Dr\nUnit 200\nMiami, FL 33101",
        ];
        
        return collect($addresses)->random();
    }

    private function generatePaymentReference($method)
    {
        switch ($method) {
            case 'stripe':
                return 'pi_' . Str::random(24);
            case 'paypal':
                return 'PAYID-' . Str::upper(Str::random(16));
            case 'bank':
                return 'TXN' . random_int(100000, 999999);
            default:
                return 'REF' . random_int(100000, 999999);
        }
    }

    private function generatePaymentDetails($method)
    {
        switch ($method) {
            case 'stripe':
                return [
                    'payment_intent_id' => 'pi_' . Str::random(24),
                    'card_last4' => random_int(1000, 9999),
                    'card_brand' => collect(['visa', 'mastercard', 'amex'])->random(),
                ];
            case 'paypal':
                return [
                    'order_id' => random_int(10000000, 99999999),
                    'payment_id' => 'PAYID-' . Str::upper(Str::random(16)),
                    'payer_email' => 'payer@example.com',
                ];
            case 'bank':
                return [
                    'transfer_reference' => 'TXN' . random_int(100000, 999999),
                    'bank_name' => collect(['Chase Bank', 'Bank of America', 'Wells Fargo', 'Citibank'])->random(),
                    'status' => 'verified',
                ];
            default:
                return [];
        }
    }

    private function createInvoiceItems($invoice, $project)
    {
        $tasks = $project->tasks()->get();
        $itemCount = min(random_int(2, 5), max(1, $tasks->count()));
        
        // Create items based on actual tasks first
        $taskItems = $tasks->take($itemCount);
        
        foreach ($taskItems as $index => $task) {
            $rate = random_int(50, 500);
            
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'task_id' => $task->id,
                'type' => 'task',
                'description' => $task->title,
                'rate' => $rate,
                'amount' => $rate,
                'sort_order' => $index + 1,
            ]);
        }
        
        // Add custom items if we need more
        $remainingItems = $itemCount - $taskItems->count();
        for ($i = 0; $i < $remainingItems; $i++) {
            $rate = random_int(50, 500);
            $description = $this->generateItemDescription('custom');
            
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'type' => 'custom',
                'description' => $description,
                'rate' => $rate,
                'amount' => $rate,
                'sort_order' => $taskItems->count() + $i + 1,
            ]);
        }
    }

    private function generateItemDescription($type)
    {
        $descriptions = [
            'Software Development Services',
            'Project Management',
            'Technical Consulting',
            'System Integration',
            'Quality Assurance Testing',
            'Documentation and Training',
            'Database Design and Implementation',
            'API Development',
            'User Interface Design',
            'Performance Optimization',
        ];
        
        return collect($descriptions)->random();
    }
}