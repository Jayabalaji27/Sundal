<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Notifications\TaskCommentNotification;
use App\Services\EmailTemplateService;
use App\Support\EmailTemplates\CommentMentionTemplate;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $this->ensureTaskVisible($task);

        $validated = $request->validate([
            'comment' => 'required|string',
            'mentions' => 'nullable|array',
        ]);

        $taskComment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => auth()->id(),
            'comment' => $validated['comment'],
            'mentions' => $this->allowedMentions($task, $validated['mentions'] ?? []),
        ]);

        $this->notify($taskComment, $taskComment->mentions, notifyFollowers: true);

        // Fire event for Slack notification
        if (!config('app.is_demo', true)) {
            event(new \App\Events\TaskCommentAdded($taskComment));
        }

        return back();
    }

    public function update(Request $request, TaskComment $taskComment)
    {
        $this->ensureTaskVisible($taskComment->task);

        // Check if user can update comment
        if (!$taskComment->canBeUpdatedBy(auth()->user())) {
            abort(403);
        }

        $validated = $request->validate([
            'comment' => 'required|string',
            'mentions' => 'nullable|array',
        ]);

        $before = $taskComment->mentions ?? [];
        $mentions = $this->allowedMentions($taskComment->task, $validated['mentions'] ?? []);
        $taskComment->update(['comment' => $validated['comment'], 'mentions' => $mentions]);

        // Only people added to the comment by this edit hear about it.
        $this->notify($taskComment, array_values(array_diff($mentions, $before)), notifyFollowers: false);

        return back();
    }

    public function destroy(TaskComment $taskComment)
    {
        $this->ensureTaskVisible($taskComment->task);

        // Check if user can delete comment
        if (!$taskComment->canBeDeletedBy(auth()->user())) {
            abort(403);
        }

        $taskComment->delete();

        return back();
    }

    /**
     * Same rule as the Tasks page: the task is in the user's current workspace,
     * in a project they can see. Anything else answers 404, so task ids of other
     * companies can't be probed.
     */
    private function ensureTaskVisible(Task $task): void
    {
        abort_unless(Task::visibleTo(auth()->user(), false)->whereKey($task->id)->exists(), 404);
    }

    /**
     * The mentioned ids that may see the task, in the order given, without the
     * author. Unknown ids, people of other workspaces, clients and members not
     * on a private project are dropped.
     */
    private function allowedMentions(Task $task, array $ids): array
    {
        $ids = collect($ids)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) auth()->id())->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $allowed = $this->usersWhoCanSee($task, $ids->all());

        return $ids->filter(fn ($id) => in_array($id, $allowed, true))->values()->all();
    }

    /**
     * Of the given user ids, those who can open the task: active, non-client
     * members of its workspace who are the owner, or (for a private project) its
     * creator or a member of it.
     */
    private function usersWhoCanSee(Task $task, array $ids): array
    {
        $project = $task->project;

        $roles = WorkspaceMember::where('workspace_id', $project->workspace_id)
            ->whereIn('user_id', $ids)
            ->where('status', 'active')
            ->where('role', '!=', 'client')
            ->pluck('role', 'user_id');

        if ($project->visibility === 'workspace') {
            return $roles->keys()->map(fn ($id) => (int) $id)->all();
        }

        $projectMembers = $project->members()->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        return $roles->filter(fn ($role, $id) => $role === 'owner'
            || (int) $id === (int) $project->created_by
            || in_array((int) $id, $projectMembers, true))
            ->keys()->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Bell notifications: "mention" for the given ids; with $notifyFollowers, a
     * "comment" for the task's assignee and creator who weren't mentioned.
     * The author is never notified.
     */
    private function notify(TaskComment $comment, array $mentionIds, bool $notifyFollowers): void
    {
        $task = $comment->task;
        $authorId = (int) $comment->user_id;

        $followers = $notifyFollowers
            ? $this->usersWhoCanSee($task, array_filter([$task->assigned_to, $task->created_by]))
            : [];
        $followers = array_values(array_diff($followers, $mentionIds, [$authorId]));

        $mentioned = User::whereIn('id', $mentionIds)->get();
        $mentioned->each(fn (User $user) => $user->notify(new TaskCommentNotification($comment, 'mention')));
        User::whereIn('id', $followers)->get()
            ->each(fn (User $user) => $user->notify(new TaskCommentNotification($comment, 'comment')));

        $this->emailMentioned($comment, $mentioned);
    }

    /**
     * "Comment Mention" email to each mentioned person, when the workspace owner
     * hasn't switched it off (it starts on). Sent after the response so posting
     * the comment never waits on, or fails because of, the mail server.
     */
    private function emailMentioned(TaskComment $comment, $mentioned): void
    {
        $task = $comment->task;
        $workspace = $task->project->workspace;

        if ($mentioned->isEmpty() || !$workspace
            || !EmailTemplate::isOnFor(CommentMentionTemplate::NAME, (int) $workspace->owner_id, (int) $workspace->id)) {
            return;
        }

        $sender = $comment->user?->name ?? '';
        $base = [
            '{sender_name}' => e($sender),
            '{task_title}' => e($task->title),
            '{project_name}' => e($task->project->title ?? '-'),
            '{comment}' => nl2br(e($comment->comment)),
            '{task_url}' => route('tasks.index', ['task_id' => $task->id]),
            '{company_name}' => e($workspace->name ?: config('app.name')),
        ];

        dispatch(function () use ($mentioned, $base, $workspace) {
            $mailer = app(EmailTemplateService::class);
            foreach ($mentioned as $user) {
                try {
                    $mailer->sendTemplateEmailWithLanguage(
                        templateName: CommentMentionTemplate::NAME,
                        variables: $base + ['{mentioned_user_name}' => e($user->name)],
                        toEmail: $user->email,
                        toName: $user->name,
                        language: $user->lang ?: 'en',
                        business: $workspace,
                    );
                } catch (\Throwable $e) {
                    // Logged by EmailTemplateService (IntegrationLog); the bell notice already went out.
                }
            }
        })->afterResponse();
    }
}
