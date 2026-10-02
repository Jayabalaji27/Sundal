<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bug;
use App\Models\BugComment;
use App\Models\BugStatus;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class BugSeeder extends Seeder
{
    public function run(): void
    {
        $projects = Project::with(['members.user', 'milestones', 'workspace.activeMembers.user'])->get();
        
        // If no projects exist, create some
        if ($projects->isEmpty()) {
            $this->createProjectsAndMilestones();
            $projects = Project::with(['members.user', 'milestones', 'workspace.activeMembers.user'])->get();
        }

        foreach ($projects as $project) {
            $projectMembers = $project->members->pluck('user')->filter();
            $milestones = $project->milestones;
            $workspace = $project->workspace;
            $statuses = BugStatus::forWorkspace($workspace->id)->ordered()->get();

            // Ensure milestones exist for this project
            if ($milestones->isEmpty()) {
                $this->createMilestonesForProject($project);
                $milestones = $project->milestones()->get();
            }

            if ($statuses->isEmpty() || $projectMembers->isEmpty()) continue;

            // Create 8 bugs per project
            for ($i = 1; $i <= 8; $i++) {
                $assignedMember = $projectMembers->random();
                $reportedBy = $projectMembers->random();
                $milestone = $milestones->random();
                $status = $statuses->random();

                $bug = Bug::create([
                    'project_id' => $project->id,
                    'bug_status_id' => $status->id,
                    'milestone_id' => $milestones->isNotEmpty() ? $milestone->id : null,
                    'title' => $this->getBugTitle($i),
                    'description' => $this->getBugDescription($i),
                    'priority' => $this->getRandomPriority(),
                    'severity' => $this->getRandomSeverity(),
                    'steps_to_reproduce' => $this->getStepsToReproduce($i),
                    'expected_behavior' => $this->getExpectedBehavior($i),
                    'actual_behavior' => $this->getActualBehavior($i),
                    'environment' => $this->getEnvironment(),
                    'assigned_to' => $projectMembers->isNotEmpty() ? $assignedMember->id : null,
                    'reported_by' => $projectMembers->isNotEmpty() ? $reportedBy->id : 1,
                    'start_date' => now()->subDays(rand(1, 30)),
                    'end_date' => now()->addDays(rand(5, 45)),
                ]);

                // Create 1-3 comments per bug
                for ($c = 1; $c <= rand(1, 3); $c++) {
                    BugComment::create([
                        'bug_id' => $bug->id,
                        'user_id' => $projectMembers->random()->id,
                        'comment' => $this->getBugComment($c),
                        'created_at' => now()->subDays(rand(1, 15)),
                    ]);
                }
            }
        }
    }

    private function getBugTitle(int $index): string
    {
        $titles = [
            'Login form validation not working',
            'Dashboard charts not loading',
            'File upload fails for large files',
            'Email notifications not sent',
            'Search functionality returns incorrect results',
            'Mobile responsive layout broken',
            'Database connection timeout',
            'Payment gateway integration error',
            'User profile image not displaying',
            'Export feature generates corrupted files'
        ];

        return $titles[($index - 1) % count($titles)];
    }

    private function getBugDescription(int $index): string
    {
        $descriptions = [
            'The login form accepts invalid email formats and does not show proper error messages.',
            'Dashboard charts fail to load data and show blank containers instead.',
            'File uploads over 10MB fail with a generic error message.',
            'Email notifications for password reset and account verification are not being sent.',
            'Search returns results that do not match the search criteria.',
            'Mobile layout breaks on screens smaller than 768px width.',
            'Database queries timeout after 30 seconds causing application errors.',
            'Payment processing fails at the final step with unclear error messages.',
            'User profile images show as broken links after upload.',
            'Exported CSV files are corrupted and cannot be opened in Excel.'
        ];

        return $descriptions[($index - 1) % count($descriptions)];
    }

    private function getStepsToReproduce(int $index): string
    {
        $steps = [
            "1. Navigate to login page\n2. Enter invalid email format\n3. Click submit button\n4. Observe error handling",
            "1. Login to dashboard\n2. Navigate to analytics section\n3. Wait for charts to load\n4. Notice blank containers",
            "1. Go to file upload section\n2. Select file larger than 10MB\n3. Click upload\n4. Wait for error message",
            "1. Request password reset\n2. Check email inbox\n3. Wait for notification\n4. Notice no email received",
            "1. Use search functionality\n2. Enter specific search term\n3. Review results\n4. Notice irrelevant results",
            "1. Open application on mobile device\n2. Navigate through pages\n3. Notice layout issues\n4. Test on different screen sizes",
            "1. Perform data-intensive operation\n2. Wait for response\n3. Notice timeout error\n4. Check server logs",
            "1. Add items to cart\n2. Proceed to checkout\n3. Enter payment details\n4. Submit payment and observe error"
        ];

        return $steps[($index - 1) % count($steps)];
    }

    private function getExpectedBehavior(int $index): string
    {
        $behaviors = [
            'Form should validate email format and show clear error messages for invalid inputs.',
            'Charts should load properly and display data visualization.',
            'Large files should upload successfully or show progress indicator.',
            'Email notifications should be sent immediately after request.',
            'Search should return relevant results matching the search criteria.',
            'Layout should be responsive and work on all screen sizes.',
            'Database operations should complete within reasonable time limits.',
            'Payment should process successfully and show confirmation.'
        ];

        return $behaviors[($index - 1) % count($behaviors)];
    }

    private function getActualBehavior(int $index): string
    {
        $behaviors = [
            'Form accepts invalid emails and shows generic error message.',
            'Charts show empty containers with no data or error message.',
            'Upload fails immediately with unhelpful error message.',
            'No email is sent and no error is logged.',
            'Search returns unrelated or no results.',
            'Layout breaks and becomes unusable on mobile devices.',
            'Operations timeout and cause application to become unresponsive.',
            'Payment fails at final step with cryptic error code.'
        ];

        return $behaviors[($index - 1) % count($behaviors)];
    }

    private function getEnvironment(): string
    {
        $environments = [
            'Chrome 120.0, Windows 11, 1920x1080',
            'Safari 17.0, macOS Sonoma, 1440x900',
            'Firefox 121.0, Ubuntu 22.04, 1366x768',
            'Edge 120.0, Windows 10, 2560x1440',
            'Mobile Safari, iOS 17, iPhone 14 Pro',
            'Chrome Mobile, Android 14, Samsung Galaxy S23'
        ];

        return collect($environments)->random();
    }

    private function getBugComment(int $index): string
    {
        $comments = [
            'I can reproduce this issue consistently. It seems to be related to the validation logic.',
            'This might be a server-side configuration issue. Let me check the logs.',
            'I\'ve seen similar issues before. Could be related to the recent deployment.',
            'This is affecting multiple users. We should prioritize this fix.',
            'I have a potential solution. Let me create a patch and test it.',
            'This issue is blocking other features. We need to resolve it quickly.',
            'I\'ve identified the root cause. It\'s in the authentication middleware.',
            'The fix is ready for testing. Please verify in the staging environment.'
        ];

        return $comments[($index - 1) % count($comments)];
    }

    private function getRandomPriority(): string
    {
        return collect(['low', 'medium', 'high', 'critical'])->random();
    }

    private function getRandomSeverity(): string
    {
        return collect(['minor', 'major', 'critical', 'blocker'])->random();
    }

    private function createProjectsAndMilestones(): void
    {
        $workspaces = Workspace::with('activeMembers.user')->get();
        
        if ($workspaces->isEmpty()) return;

        foreach ($workspaces->take(2) as $workspace) {
            $members = $workspace->activeMembers->pluck('user')->filter();
            if ($members->isEmpty()) continue;

            $project = Project::create([
                'workspace_id' => $workspace->id,
                'name' => 'Sample Project ' . $workspace->id,
                'description' => 'A sample project for bug testing',
                'status' => 'active',
                'start_date' => now()->subDays(30),
                'end_date' => now()->addDays(60),
            ]);

            // Add project members
            foreach ($members->take(3) as $user) {
                $project->members()->create([
                    'user_id' => $user->id,
                    'role' => 'member',
                ]);
            }

            $this->createMilestonesForProject($project);
        }
    }

    private function createMilestonesForProject(Project $project): void
    {
        $milestones = [
            ['name' => 'Planning Phase', 'days_offset' => -20],
            ['name' => 'Development Phase', 'days_offset' => 10],
            ['name' => 'Testing Phase', 'days_offset' => 40],
        ];

        foreach ($milestones as $milestone) {
            ProjectMilestone::create([
                'project_id' => $project->id,
                'title' => $milestone['name'],
                'description' => 'Sample milestone for ' . $milestone['name'],
                'start_date' => now()->addDays($milestone['days_offset']),
                'end_date' => now()->addDays($milestone['days_offset'] + 20),
                'status' => 'active',
            ]);
        }
    }
}