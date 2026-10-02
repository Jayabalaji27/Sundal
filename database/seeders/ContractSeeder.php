<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\ContractComment;
use App\Models\ContractNote;
use App\Models\ContractType;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
{
    public function run(): void
    {
        $workspaces = Workspace::all();

        if ($workspaces->isEmpty()) {
            $this->command->info('No workspaces found. Please run WorkspaceSeeder first.');
            return;
        }

        $contractTypes = [
            ['name' => 'Service Agreement',    'color' => '#007bff', 'sort_order' => 1],
            ['name' => 'NDA',                  'color' => '#28a745', 'sort_order' => 2],
            ['name' => 'Employment Contract',  'color' => '#ffc107', 'sort_order' => 3],
            ['name' => 'Freelance Contract',   'color' => '#17a2b8', 'sort_order' => 4],
            ['name' => 'Maintenance Contract', 'color' => '#6f42c1', 'sort_order' => 5],
        ];

        foreach ($workspaces as $workspace) {
            $creator = $workspace->users()->first();
            if (!$creator) continue;

            // Create contract types per workspace, unless the workspace already has some
            // (e.g. WorkspaceObserver already seeded defaults via ContractTypeSeeder) -
            // otherwise re-running this seeder duplicates "Service Agreement"/"NDA" etc.
            $types = collect();
            if ($workspace->contractTypes()->count() === 0) {
                foreach ($contractTypes as $typeData) {
                    $types->push(ContractType::create(array_merge($typeData, [
                        'is_active'    => true,
                        'workspace_id' => $workspace->id,
                        'created_by'   => $creator->id,
                    ])));
                }
            } else {
                $types = $workspace->contractTypes;
            }

            $projects = $workspace->projects()->take(3)->get();
            $clients  = $workspace->users()
                ->select('users.*')
                ->whereHas('roles', fn($q) => $q->where('name', 'client'))
                ->get();

            if ($clients->isEmpty()) {
                $clients = $workspace->users()->select('users.*')->get();
            }

            if ($clients->isEmpty() || $projects->isEmpty()) continue;

            $statuses = ['pending', 'sent', 'accept', 'accept', 'decline', 'expired'];

            foreach ($projects as $project) {
                $contractCount = random_int(3, 6);

                for ($i = 0; $i < $contractCount; $i++) {
                    $status    = $statuses[array_rand($statuses)];
                    $startDate = now()->subDays(random_int(10, 180));
                    $endDate   = $startDate->copy()->addDays(random_int(30, 365));
                    $client    = $clients->random();
                    $type      = $types->random();

                    $assignedUsers = $workspace->users()
                        ->select('users.id')
                        ->inRandomOrder()
                        ->take(random_int(1, 3))
                        ->pluck('users.id')
                        ->toArray();

                    $contract = Contract::create([
                        'subject'          => $this->generateSubject($project->title, $i + 1),
                        'description'      => $this->generateDescription(),
                        'contract_type_id' => $type->id,
                        'contract_value'   => random_int(500, 50000),
                        'start_date'       => $startDate,
                        'end_date'         => $endDate,
                        'status'           => $status,
                        'client_id'        => $client->id,
                        'project_id'       => $project->id,
                        'assigned_users'   => $assignedUsers,
                        'terms_conditions' => $this->generateTerms(),
                        'notes'            => $this->generateNotes(),
                        'currency'         => 'USD',
                        'workspace_id'     => $workspace->id,
                        'created_by'       => $creator->id,
                        'sent_at'          => in_array($status, ['sent', 'accept', 'decline', 'expired']) ? $startDate->copy()->subDays(random_int(1, 5)) : null,
                        'accepted_at'      => $status === 'accept' ? $startDate->copy()->addDays(random_int(1, 7)) : null,
                        'declined_at'      => $status === 'decline' ? $startDate->copy()->addDays(random_int(1, 7)) : null,
                    ]);

                    $this->createNotes($contract, $creator->id);
                    $this->createComments($contract, $creator->id, $client->id);
                }
            }
        }

        $this->command->info('Contract seeder completed successfully.');
    }

    private function generateSubject(string $projectTitle, int $index): string
    {
        $subjects = [
            "Service Agreement - {$projectTitle}",
            "NDA for {$projectTitle}",
            "Development Contract #{$index} - {$projectTitle}",
            "Consulting Agreement - {$projectTitle}",
            "Maintenance Contract - {$projectTitle}",
        ];

        return $subjects[array_rand($subjects)];
    }

    private function generateDescription(): string
    {
        $descriptions = [
            'This contract outlines the scope of work and deliverables for the project.',
            'Agreement for professional services including development and consulting.',
            'Non-disclosure and service agreement for the duration of the project.',
            'Contract covering all phases of software development and support.',
        ];

        return $descriptions[array_rand($descriptions)];
    }

    private function generateTerms(): string
    {
        return 'Payment is due within 30 days of invoice. Either party may terminate this agreement with 30 days written notice. All work remains property of the client upon full payment.';
    }

    private function generateNotes(): string
    {
        $notes = [
            'Client requested expedited delivery.',
            'Reviewed and approved by legal team.',
            'Renewal discussion scheduled for next quarter.',
            'All milestones agreed upon in kickoff meeting.',
        ];

        return $notes[array_rand($notes)];
    }

    private function createNotes(Contract $contract, int $createdBy): void
    {
        $noteTexts = [
            'Initial contract draft reviewed.',
            'Client requested minor revisions to payment terms.',
            'All parties have reviewed the contract.',
            'Follow-up scheduled for signature.',
        ];

        $count = random_int(1, 3);
        for ($i = 0; $i < $count; $i++) {
            ContractNote::create([
                'contract_id' => $contract->id,
                'note'        => $noteTexts[array_rand($noteTexts)],
                'is_pinned'   => $i === 0,
                'created_by'  => $createdBy,
            ]);
        }
    }

    private function createComments(Contract $contract, int $createdBy, int $clientId): void
    {
        $commentTexts = [
            'Please review the updated terms in section 3.',
            'We have approved the contract as discussed.',
            'Can we adjust the payment schedule?',
            'Contract looks good, proceeding with signature.',
        ];

        $count = random_int(1, 3);
        for ($i = 0; $i < $count; $i++) {
            $parent = ContractComment::create([
                'contract_id' => $contract->id,
                'comment'     => $commentTexts[array_rand($commentTexts)],
                'parent_id'   => null,
                'is_internal' => (bool) random_int(0, 1),
                'created_by'  => $i % 2 === 0 ? $createdBy : $clientId,
            ]);

            // Add one reply occasionally
            if (random_int(0, 1)) {
                ContractComment::create([
                    'contract_id' => $contract->id,
                    'comment'     => 'Acknowledged, thank you for the update.',
                    'parent_id'   => $parent->id,
                    'is_internal' => false,
                    'created_by'  => $i % 2 === 0 ? $clientId : $createdBy,
                ]);
            }
        }
    }
}
