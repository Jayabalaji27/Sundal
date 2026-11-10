<?php

namespace App\Services\Ai\Tools;

use App\Events\TaskCommentAdded;
use App\Models\TaskComment;
use App\Models\User;

/** Posts a comment on a task or bug as the user, as the comment box on the task does. */
class AddCommentTool extends AiTool
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'add_comment';
    }

    public function description(): string
    {
        return 'Post a comment on a task or bug, in the user\'s name. Pass the comment text the user wants posted. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['task_add_comments', 'bug_add_comments'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'type' => ['type' => 'enum', 'options' => ['task', 'bug'], 'description' => 'Defaults to task.'],
            'ref' => ['type' => 'string', 'required' => true, 'description' => 'Task or bug title or id.'],
            'project' => ['type' => 'string', 'description' => 'Project title or id, to narrow the search.'],
            'comment' => ['type' => 'string', 'required' => true, 'description' => 'The comment text, at most 5000 characters.'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $bug = ($args['type'] ?? 'task') === 'bug';
        if (!$user->hasWorkspacePermission($bug ? 'bug_add_comments' : 'task_add_comments')) {
            throw new ToolInputException(__('The user is not allowed to comment on :kind.', ['kind' => $bug ? __('bugs') : __('tasks')]));
        }
        $task = $bug
            ? $this->resolver->bug($user, (string) ($args['ref'] ?? ''), $args['project'] ?? null)
            : $this->resolver->task($user, (string) ($args['ref'] ?? ''), $args['project'] ?? null);

        $comment = trim((string) ($args['comment'] ?? ''));
        if ($comment === '') {
            throw new ToolInputException(__('What should the comment say? Ask the user.'));
        }
        if (mb_strlen($comment) > 5000) {
            throw new ToolInputException(__('The comment can be at most 5000 characters.'));
        }
        $task->loadMissing('project:id,title');

        return new PreparedAction(
            summary: __('Comment on ":title"', ['title' => $task->title]),
            details: [
                ($task->isBug() ? __('Bug') : __('Task')) => "{$task->title} (#{$task->id})",
                __('Project') => (string) $task->project?->title,
                __('Comment') => $comment,
            ],
            payload: ['task_id' => $task->id, 'comment' => $comment],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $task = $this->resolver->byId($this->resolver->tasks($user), (int) $payload['task_id'], __('task'));

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'comment' => $payload['comment'],
            'mentions' => [],
        ]);

        // Fire event for Slack notification
        if (!config('app.is_demo', true)) {
            event(new TaskCommentAdded($comment));
        }

        return new ToolOutcome(
            __('Posted your comment on ":title".', ['title' => $task->title]),
            $comment,
            route('tasks.show', $task->id, false),
            ['comment_id' => $comment->id],
        );
    }

    public function undo(array $undo, User $user): string
    {
        $comment = TaskComment::whereKey((int) $undo['comment_id'])
            ->where('user_id', $user->id)
            ->whereIn('task_id', $this->resolver->tasks($user)->select('id'))
            ->first() ?? throw new ToolInputException(__('The comment no longer exists.'));
        $comment->delete();

        return __('Comment removed.');
    }
}
