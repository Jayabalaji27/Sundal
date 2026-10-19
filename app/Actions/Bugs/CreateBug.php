<?php

namespace App\Actions\Bugs;

use App\Actions\Tasks\CreateTask;
use App\Models\Task;
use App\Models\User;

/**
 * Reports a bug: a task of type "bug" in the workspace's first stage, so it
 * shows on the same Board, Backlog and Sprints as the rest of the work.
 * Shared by the AI assistant and the sheet import. Expects validated data with
 * a project in the actor's workspace; the actor is stored as the creator.
 */
class CreateBug
{
    public function __construct(private readonly CreateTask $createTask) {}

    public function handle(User $actor, array $data): Task
    {
        return $this->createTask->handle($actor, [
            ...$data,
            'type' => 'bug',
            'severity' => $data['severity'] ?? 'major',
        ]);
    }
}
