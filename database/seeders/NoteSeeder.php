<?php

namespace Database\Seeders;

use App\Models\Note;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class NoteSeeder extends Seeder
{
    public function run(): void
    {
        $workspaces = Workspace::all();

        if ($workspaces->isEmpty()) {
            $this->command->info('No workspaces found. Please run WorkspaceSeeder first.');
            return;
        }

        $colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9'];

        $personalNotes = [
            [
                'title' => 'Meeting Notes - Q3 Planning',
                'text'  => 'Discussed Q3 goals: increase user retention by 20%, launch mobile app by August, onboard 3 new enterprise clients. Follow up with marketing team on campaign strategy.',
            ],
            [
                'title' => 'Ideas for Dashboard Redesign',
                'text'  => 'Consider adding a quick-action toolbar, collapsible sidebar, dark mode toggle, and customizable widget layout. Reference Notion and Linear for inspiration.',
            ],
            [
                'title' => 'Client Call Summary',
                'text'  => 'Client requested additional reporting features. Priority: export to PDF, custom date ranges, and team performance charts. Deadline: end of next sprint.',
            ],
            [
                'title' => 'Personal Goals This Month',
                'text'  => 'Complete Laravel certification, review 5 pull requests per week, document all new API endpoints, and attend 2 tech meetups.',
            ],
            [
                'title' => 'Bug Fixes Backlog',
                'text'  => 'Login redirect issue on mobile, timezone mismatch in reports, file upload size limit not enforced, notification bell not clearing on read.',
            ],
            [
                'title' => 'Research: Third-party Integrations',
                'text'  => 'Evaluate Zapier, Make (Integromat), and native webhooks for workflow automation. Check pricing tiers and API rate limits before deciding.',
            ],
            [
                'title' => 'Sprint Retrospective Notes',
                'text'  => 'What went well: team communication improved, CI/CD pipeline stable. Improvements needed: better estimation, reduce context switching, clearer acceptance criteria.',
            ],
        ];

        $sharedNotes = [
            [
                'title' => 'Team Coding Standards',
                'text'  => 'Use PSR-12 for PHP, ESLint with Airbnb config for JS. All functions must have docblocks. PRs require at least 2 approvals. Branch naming: feature/, bugfix/, hotfix/.',
            ],
            [
                'title' => 'Project Deployment Checklist',
                'text'  => "Before deploying: run all tests, update .env on server, run migrations, clear cache, notify team in Slack #deployments channel, monitor error logs for 30 minutes post-deploy.",
            ],
            [
                'title' => 'Onboarding Guide for New Members',
                'text'  => 'Setup: clone repo, copy .env.example, run composer install & npm install, run migrations with seeders. Read the wiki for architecture overview. First task: fix a good-first-issue.',
            ],
            [
                'title' => 'Weekly Team Standup Template',
                'text'  => "Format:\n- What did I do yesterday?\n- What will I do today?\n- Any blockers?\n\nKeep it under 15 minutes. Update your tasks in the project board before standup.",
            ],
            [
                'title' => 'API Rate Limits & Keys',
                'text'  => 'Stripe: 100 req/sec (test), 100 req/sec (live). Google API: 10,000 req/day. Zoom: 100 req/sec. Store all keys in .env, never commit to repo.',
            ],
            [
                'title' => 'Design System Colors',
                'text'  => 'Primary: #4F46E5, Secondary: #7C3AED, Success: #10B981, Warning: #F59E0B, Danger: #EF4444, Info: #3B82F6. Always use Tailwind utility classes, avoid inline styles.',
            ],
        ];

        foreach ($workspaces as $workspace) {
            $users = $workspace->users()->select('users.*')->get();

            if ($users->isEmpty()) continue;

            // Personal notes — one per user (first 3 users)
            foreach ($users->take(3) as $user) {
                foreach (array_slice($personalNotes, 0, random_int(3, 5)) as $noteData) {
                    Note::create([
                        'title'      => $noteData['title'],
                        'text'       => $noteData['text'],
                        'color'      => $colors[array_rand($colors)],
                        'type'       => 'personal',
                        'assign_to'  => null,
                        'workspace'  => $workspace->id,
                        'created_by' => $user->id,
                    ]);
                }
            }

            // Shared notes — created by first user, assigned to multiple users
            $creator = $users->first();
            foreach ($sharedNotes as $noteData) {
                $assignedIds = $users->random(min(random_int(2, 4), $users->count()))
                    ->pluck('id')
                    ->implode(',');

                Note::create([
                    'title'      => $noteData['title'],
                    'text'       => $noteData['text'],
                    'color'      => $colors[array_rand($colors)],
                    'type'       => 'shared',
                    'assign_to'  => $assignedIds,
                    'workspace'  => $workspace->id,
                    'created_by' => $creator->id,
                ]);
            }
        }

        $this->command->info('Note seeder completed successfully.');
    }
}
