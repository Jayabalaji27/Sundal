<?php

namespace Database\Seeders;

use App\Models\Todo;
use App\Models\TodoComment;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class ToDoSeeder extends Seeder
{
    public function run(): void
    {
        $workspaces = Workspace::all();

        if ($workspaces->isEmpty()) {
            $this->command->info('No workspaces found. Please run WorkspaceSeeder first.');
            return;
        }

        $todoTitles = [
            ['title' => 'Review project requirements document',       'priority' => 'high'],
            ['title' => 'Set up development environment',             'priority' => 'high'],
            ['title' => 'Write unit tests for authentication module', 'priority' => 'medium'],
            ['title' => 'Update API documentation',                   'priority' => 'medium'],
            ['title' => 'Fix UI bugs reported in last sprint',        'priority' => 'high'],
            ['title' => 'Prepare weekly status report',               'priority' => 'low'],
            ['title' => 'Code review for pull request #42',           'priority' => 'medium'],
            ['title' => 'Optimize database queries',                  'priority' => 'medium'],
            ['title' => 'Schedule client demo meeting',               'priority' => 'low'],
            ['title' => 'Deploy staging environment updates',         'priority' => 'high'],
            ['title' => 'Research new payment gateway integration',   'priority' => 'medium'],
            ['title' => 'Update project timeline in Gantt chart',     'priority' => 'low'],
            ['title' => 'Conduct security audit on login flow',       'priority' => 'high'],
            ['title' => 'Refactor legacy codebase modules',           'priority' => 'medium'],
            ['title' => 'Create onboarding guide for new members',    'priority' => 'low'],
        ];

        $descriptions = [
            'This task needs to be completed before the next sprint starts.',
            'Coordinate with the team before proceeding.',
            'Refer to the project wiki for detailed instructions.',
            'Ensure all edge cases are handled properly.',
            'Document the changes after completion.',
            null,
        ];

        $comments = [
            'Started working on this, will update soon.',
            'Blocked by dependency on another task.',
            'Completed the first phase, reviewing now.',
            'Need clarification from the client before proceeding.',
            'Almost done, just final testing remaining.',
            'Assigned to the right person, tracking progress.',
        ];

        $statuses = ['pending', 'pending', 'in_progress', 'in_progress', 'completed', 'overdue'];

        foreach ($workspaces as $workspace) {
            $users = $workspace->users()->select('users.*')->get();

            if ($users->isEmpty()) continue;

            $creator = $users->first();

            foreach ($todoTitles as $todoData) {
                $status  = $statuses[array_rand($statuses)];
                $dueDate = match($status) {
                    'overdue'     => now()->subDays(random_int(1, 15)),
                    'completed'   => now()->subDays(random_int(1, 10)),
                    'in_progress' => now()->addDays(random_int(1, 14)),
                    default       => now()->addDays(random_int(3, 30)),
                };

                $todo = Todo::create([
                    'workspace_id' => $workspace->id,
                    'created_by'   => $creator->id,
                    'title'        => $todoData['title'],
                    'description'  => $descriptions[array_rand($descriptions)],
                    'priority'     => $todoData['priority'],
                    'status'       => $status,
                    'due_date'     => $dueDate,
                    'completed_at' => $status === 'completed' ? now()->subDays(random_int(1, 5)) : null,
                ]);

                // Assign 1-3 members
                $members = $users->random(min(random_int(1, 3), $users->count()));
                $todo->members()->attach($members->pluck('id')->toArray());

                // Add 1-3 comments
                $commentCount = random_int(1, 3);
                for ($i = 0; $i < $commentCount; $i++) {
                    TodoComment::create([
                        'todo_id' => $todo->id,
                        'user_id' => $users->random()->id,
                        'comment' => $comments[array_rand($comments)],
                    ]);
                }
            }
        }

        $this->command->info('ToDo seeder completed successfully.');
    }
}
