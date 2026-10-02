<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PlanRequest;
use App\Models\User;
use App\Models\Plan;
use App\Models\Workspace;

class PlanRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $workspaces = Workspace::with('activeMembers.user')->get();
        $plans = Plan::all();

        if ($workspaces->count() > 0 && $plans->count() > 0) {
            foreach ($workspaces as $workspace) {
                $workspaceUsers = $workspace->activeMembers->pluck('user')->filter();
                
                if ($workspaceUsers->isEmpty()) continue;
                
                // Select 50% of users to create plan requests (one request per selected user)
                $selectedUserCount = max(1, intval($workspaceUsers->count() * 0.5));
                $selectedUsers = $workspaceUsers->random($selectedUserCount);
                
                foreach ($selectedUsers as $user) {
                    $plan = $plans->random();
                    $status = $this->getRandomStatus();
                    
                    $planRequest = PlanRequest::create([
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'duration' => $this->getRandomDuration(),
                        'status' => $status,
                        'message' => $this->getRandomMessage($plan->name),
                    ]);
                    
                    // Set approval/rejection details for processed requests
                    if ($status === 'approved') {
                        $planRequest->update([
                            'approved_at' => now()->subDays(rand(1, 30)),
                            'approved_by' => $workspace->owner_id,
                        ]);
                    } elseif ($status === 'rejected') {
                        $planRequest->update([
                            'rejected_at' => now()->subDays(rand(1, 15)),
                            'rejected_by' => $workspace->owner_id,
                        ]);
                    }
                }
            }
        }
    }
    
    private function getRandomStatus(): string
    {
        $statuses = ['pending', 'approved', 'rejected'];
        $weights = [40, 35, 25]; // 40% pending, 35% approved, 25% rejected
        
        $rand = rand(1, 100);
        if ($rand <= 40) return 'pending';
        if ($rand <= 75) return 'approved';
        return 'rejected';
    }
    
    private function getRandomDuration(): string
    {
        return collect(['monthly', 'yearly'])->random();
    }
    
    private function getRandomMessage(string $planName): string
    {
        $messages = [
            "I would like to upgrade to the {$planName} plan to access more features for our growing team.",
            "Our current plan is limiting our productivity. Please approve the {$planName} upgrade.",
            "We need additional workspace capacity. The {$planName} plan would be perfect for our needs.",
            "Request for {$planName} plan upgrade to support our expanding project requirements.",
            "Our team has grown and we need the advanced features in the {$planName} plan.",
            "Please consider upgrading us to {$planName} for better collaboration tools.",
            "We require the enhanced security features available in the {$planName} plan.",
            "The {$planName} plan would help us manage our increased workload more effectively.",
            "Requesting {$planName} upgrade for additional storage and user limits.",
            "Our business needs have evolved and the {$planName} plan is now necessary."
        ];
        
        return $messages[array_rand($messages)];
    }
}
