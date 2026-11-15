<?php

namespace App\Services\History;

use App\Models\Epic;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskStage;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * A task's history for the task window's History tab, read from the log that
 * HistoryRecorder writes: changes to the task (with old and new values), its
 * comments, and sprint moves. Ids are turned into names here so the page only
 * has to lay the entries out.
 */
class TaskTimeline
{
    public const LIMIT = 100;

    /** Columns that change as a side effect and mean nothing to a reader. */
    private const SKIP = ['google_calendar_event_id', 'is_googlecalendar_sync', 'project_id', 'created_by', 'order', 'position', 'sort_order'];

    /** Free text: the entry says it changed rather than printing both versions. */
    private const LONG = ['description', 'steps_to_reproduce', 'expected_behavior', 'actual_behavior', 'resolution_notes'];

    /** @var array<string, array<int, mixed>> */
    private array $names = [];

    /** @return array{entries: array<int, array>, truncated: bool} */
    public function for(Task $task): array
    {
        $commentIds = TaskComment::where('task_id', $task->id)->pluck('id');
        $taskClass = $task->getMorphClass();
        $commentClass = (new TaskComment)->getMorphClass();

        $activities = Activity::query()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('subject_type', $taskClass)->where('subject_id', $task->id))
                ->orWhere(fn ($q) => $q->where('subject_type', $commentClass)->where(fn ($q) => $q
                    ->whereIn('subject_id', $commentIds)
                    ->orWhere('properties->old->task_id', $task->id))))
            ->with('causer')
            ->latest('id')
            ->limit(self::LIMIT + 1)
            ->get();

        $truncated = $activities->count() > self::LIMIT;
        $activities = $activities->take(self::LIMIT);
        $this->preload($activities);

        // A move-back reason is its own comment; it is shown on the stage change instead.
        $reasonComments = $activities->pluck('properties.reason_comment_id')->filter()->map(fn ($id) => (int) $id)->all();
        [$legacyHide, $legacyNotes] = $this->pairLegacyReasons($activities, $taskClass, $commentClass);
        $reasonComments = array_merge($reasonComments, $legacyHide);

        $entries = $activities
            ->reject(fn (Activity $a) => $a->subject_type === $commentClass && in_array((int) $a->subject_id, $reasonComments, true))
            ->map(function (Activity $a) use ($commentClass, $legacyNotes) {
                if ($a->subject_type === $commentClass) {
                    return $this->comment($a);
                }
                $entry = $this->taskEntry($a);
                if ($entry && !$entry['note'] && isset($legacyNotes[$a->id])) {
                    $entry['note'] = $legacyNotes[$a->id];
                    $entry['note_type'] = 'reason';
                }
                return $entry;
            })
            ->filter()
            ->values();

        // Seeded or imported tasks have no "created" entry: start the story from the task itself.
        $hasCreated = $activities->contains(fn (Activity $a) => $a->subject_type === $taskClass && $a->event === 'created');
        if (!$hasCreated && !$truncated) {
            $creator = $task->created_by ? User::find($task->created_by) : null;
            $entries->push([
                'id' => 'created-' . $task->id,
                'kind' => 'created',
                'summary' => __('created this task'),
                'at' => $task->created_at?->toIso8601String(),
                'actor' => $this->actor($creator),
                'source' => null,
                'changes' => [],
                'note' => null,
                'note_type' => null,
            ]);
        }

        return ['entries' => $entries->all(), 'truncated' => $truncated];
    }

    /**
     * Move-backs saved before the reason was written onto the history entry: the
     * reason comment ("… Reason: …") came from the same person within seconds of
     * the stage change. Returns [comment ids to hide, [stage activity id => reason]].
     */
    private function pairLegacyReasons(Collection $activities, string $taskClass, string $commentClass): array
    {
        $hide = [];
        $notes = [];
        $stageMoves = $activities->filter(fn (Activity $a) => $a->subject_type === $taskClass
            && isset($a->properties['attributes']['task_stage_id']) && empty($a->properties['reason']));

        foreach ($activities as $c) {
            $text = (string) ($c->properties['attributes']['comment'] ?? '');
            if ($c->subject_type !== $commentClass || $c->event !== 'created' || !preg_match('/\bReason:\s*(.+)$/su', $text, $m)) {
                continue;
            }
            $move = $stageMoves->first(fn (Activity $a) => !isset($notes[$a->id])
                && $a->causer_id === $c->causer_id
                && $a->id < $c->id
                && abs($a->created_at->diffInSeconds($c->created_at)) <= 10);
            if ($move) {
                $hide[] = (int) $c->subject_id;
                $notes[$move->id] = trim($m[1]);
            }
        }

        return [$hide, $notes];
    }

    private function taskEntry(Activity $a): ?array
    {
        $new = collect($a->properties['attributes'] ?? []);
        $old = collect($a->properties['old'] ?? []);

        if (in_array($a->event, ['added_to_sprint', 'removed_from_sprint'], true)) {
            $sprint = $new['sprint'] ?? ('#' . ($new['sprint_id'] ?? '?'));
            return $this->entry($a, 'sprint', $a->event === 'added_to_sprint'
                ? __('added this task to sprint :sprint', ['sprint' => $sprint])
                : __('removed this task from sprint :sprint', ['sprint' => $sprint]));
        }

        if ($a->event === 'created') {
            $details = collect(['task_stage_id', 'assigned_to', 'priority', 'end_date'])
                ->filter(fn ($f) => filled($new[$f] ?? null))
                ->map(fn ($f) => $this->change($f, null, $new[$f]))
                ->values()->all();

            return $this->entry($a, 'created', __('created this task'), $details);
        }

        if ($a->event === 'deleted') {
            return $this->entry($a, 'deleted', __('deleted this task'));
        }

        $fields = $new->keys()->reject(fn ($f) => in_array($f, self::SKIP, true))->values();
        if ($fields->isEmpty()) {
            return null;
        }

        $changes = $fields->map(fn ($f) => $this->change($f, $old[$f] ?? null, $new[$f]))->all();

        $only = $fields->count() === 1 ? $fields->first() : null;
        [$kind, $summary] = match (true) {
            $fields->contains('task_stage_id') => ['stage', __('moved this task')],
            $only === 'assigned_to' && blank($new['assigned_to']) => ['assignment', __('unassigned this task')],
            $only === 'assigned_to' => ['assignment', __('changed the assignee')],
            $only !== null => ['update', __('changed the :field', ['field' => mb_strtolower($this->label($only))])],
            default => ['update', __('updated :count fields', ['count' => $fields->count()])],
        };

        $entry = $this->entry($a, $kind, $summary, $changes);
        if ($reason = $a->properties['reason'] ?? null) {
            $entry['note'] = $reason;
            $entry['note_type'] = 'reason';
        }

        return $entry;
    }

    private function comment(Activity $a): ?array
    {
        $text = $a->event === 'deleted'
            ? ($a->properties['old']['comment'] ?? null)
            : ($a->properties['attributes']['comment'] ?? null);

        [$kind, $summary] = match ($a->event) {
            'created' => ['comment', __('commented')],
            'updated' => ['comment', __('edited a comment')],
            'deleted' => ['comment', __('deleted a comment')],
            default => [null, null],
        };
        if (!$kind) {
            return null;
        }

        $entry = $this->entry($a, $kind, $summary);
        if ($text && $a->event !== 'deleted') {
            $entry['note'] = mb_strimwidth(strip_tags((string) $text), 0, 280, '…');
            $entry['note_type'] = 'comment';
        }

        return $entry;
    }

    private function entry(Activity $a, string $kind, string $summary, array $changes = []): array
    {
        return [
            'id' => $a->id,
            'kind' => $kind,
            'summary' => $summary,
            'at' => $a->created_at?->toIso8601String(),
            'actor' => $this->actor($a->causer),
            'source' => $a->properties['source'] ?? null,
            'changes' => $changes,
            'note' => null,
            'note_type' => null,
        ];
    }

    private function actor($user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'name' => $user->name,
            'avatar' => $user->avatar ? (function_exists('check_file') && check_file($user->avatar) ? get_file($user->avatar) : null) : null,
        ];
    }

    /** One field's change, with ids turned into names. */
    private function change(string $field, $from, $to): array
    {
        if (in_array($field, self::LONG, true)) {
            return ['field' => $field, 'label' => $this->label($field), 'type' => 'long', 'from' => null, 'to' => null];
        }

        $type = match ($field) {
            'task_stage_id' => 'stage',
            'assigned_to' => 'user',
            'start_date', 'end_date' => 'date',
            default => 'text',
        };

        return [
            'field' => $field,
            'label' => $this->label($field),
            'type' => $type,
            'from' => $this->value($field, $from),
            'to' => $this->value($field, $to),
        ];
    }

    private function value(string $field, $value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'task_stage_id' => $this->names['stage'][(int) $value] ?? ['name' => '#' . $value, 'color' => null],
            'assigned_to' => $this->names['user'][(int) $value] ?? '#' . $value,
            'milestone_id' => $this->names['milestone'][(int) $value] ?? '#' . $value,
            'epic_id' => $this->names['epic'][(int) $value] ?? '#' . $value,
            'start_date', 'end_date' => substr((string) $value, 0, 10),
            'priority', 'severity', 'type' => __(ucfirst((string) $value)),
            'progress' => $value . '%',
            default => is_scalar($value) ? (string) $value : json_encode($value),
        };
    }

    private function label(string $field): string
    {
        return match ($field) {
            'task_stage_id' => __('Stage'),
            'assigned_to' => __('Assignee'),
            'end_date' => __('Due date'),
            'start_date' => __('Start date'),
            'milestone_id' => __('Milestone'),
            'epic_id' => __('Epic'),
            'story_points' => __('Story points'),
            'steps_to_reproduce' => __('Steps to reproduce'),
            'expected_behavior' => __('Expected behaviour'),
            'actual_behavior' => __('Actual behaviour'),
            'resolution_notes' => __('Resolution notes'),
            default => __(ucfirst(str_replace('_', ' ', $field))),
        };
    }

    /** Looks every referenced stage, person, milestone and epic up once. */
    private function preload(Collection $activities): void
    {
        $ids = ['stage' => [], 'user' => [], 'milestone' => [], 'epic' => []];
        $map = ['task_stage_id' => 'stage', 'assigned_to' => 'user', 'milestone_id' => 'milestone', 'epic_id' => 'epic'];

        foreach ($activities as $a) {
            foreach (['attributes', 'old'] as $side) {
                foreach ($map as $field => $kind) {
                    if (!empty($a->properties[$side][$field])) {
                        $ids[$kind][] = (int) $a->properties[$side][$field];
                    }
                }
            }
        }

        $this->names['stage'] = TaskStage::whereIn('id', array_unique($ids['stage']))->get(['id', 'name', 'color'])
            ->mapWithKeys(fn ($s) => [$s->id => ['name' => $s->name, 'color' => $s->color]])->all();
        $this->names['user'] = User::whereIn('id', array_unique($ids['user']))->pluck('name', 'id')->all();
        $this->names['milestone'] = ProjectMilestone::whereIn('id', array_unique($ids['milestone']))->pluck('title', 'id')->all();
        $this->names['epic'] = class_exists(Epic::class) ? Epic::whereIn('id', array_unique($ids['epic']))->pluck('name', 'id')->all() : [];
    }
}
