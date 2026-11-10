<?php

namespace App\Services\Ai\Tools;

use App\Models\Epic;
use App\Models\User;

/** Changes an epic's name, description, owner, dates or status (open / done), as the Epics tab does. */
class UpdateEpicTool extends AiTool
{
    use RevertsChanges;

    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'update_epic';
    }

    public function description(): string
    {
        return 'Change an epic: rename it, change its description, owner, start or target date, or mark it done or open again. Only the values given change. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['sprint_update'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'epic' => ['type' => 'string', 'required' => true, 'description' => 'Epic name or id.'],
            'project' => ['type' => 'string', 'description' => 'Project title or id, to narrow the search.'],
            'name' => ['type' => 'string', 'description' => 'New name.'],
            'description' => ['type' => 'string', 'description' => 'New description.'],
            'owner' => ['type' => 'string', 'description' => '"me" or the name or email of someone on the project.'],
            'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD.'],
            'target_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, not before the start date.'],
            'status' => ['type' => 'enum', 'options' => Epic::STATUSES, 'description' => 'open or done.'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $epic = $this->resolver->epic($user, (string) ($args['epic'] ?? ''), $args['project'] ?? null);
        // The whole project (the lookup loads only its title): its team and creator decide who may own the epic.
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $epic->project_id, __('project'));

        $owner = null;
        if (!empty($args['owner'])) {
            $owner = $this->resolver->member($user, (string) $args['owner']);
            $team = $project->members()->pluck('user_id')->push($project->created_by)->all();
            if (!in_array($owner->id, $team, true)) {
                throw new ToolInputException(__(':name is not on project :project, so cannot own its epic.', ['name' => $owner->name, 'project' => $project->title]));
            }
        }

        $changes = [
            'name' => isset($args['name']) && trim((string) $args['name']) !== '' ? trim((string) $args['name']) : null,
            'description' => isset($args['description']) && trim((string) $args['description']) !== '' ? mb_substr(trim((string) $args['description']), 0, 5000) : null,
            'owner_id' => $owner?->id,
            'start_date' => $this->resolver->date($args['start_date'] ?? null, __('Start date')),
            'end_date' => $this->resolver->date($args['target_date'] ?? null, __('Target date')),
            'status' => in_array($args['status'] ?? null, Epic::STATUSES, true) ? $args['status'] : null,
        ];
        if (isset($changes['name'])) {
            if (mb_strlen($changes['name']) > 255) {
                throw new ToolInputException(__('The epic needs a name of at most 255 characters.'));
            }
            if ($project->epics()->whereKeyNot($epic->id)->whereRaw('LOWER(name) = ?', [mb_strtolower($changes['name'])])->exists()) {
                throw new ToolInputException(__('Project :project already has an epic called ":name".', ['project' => $project->title, 'name' => $changes['name']]));
            }
        }

        $old = $this->snapshot($epic, array_keys($changes));
        $changes = array_filter($changes, fn ($value, $field) => $value !== null && (string) $value !== (string) ($field === 'owner_id' ? (int) ($old[$field] ?? 0) : ($old[$field] ?? '')), ARRAY_FILTER_USE_BOTH);
        if ($changes === []) {
            throw new ToolInputException(__('What should change on epic ":name"? Ask the user.', ['name' => $epic->name]));
        }
        $current = $this->snapshot($epic, ['start_date', 'end_date']);
        $start = $changes['start_date'] ?? $current['start_date'];
        $end = $changes['end_date'] ?? $current['end_date'];
        if ($start && $end && $end < $start) {
            throw new ToolInputException(__('The target date cannot be before the start date.'));
        }

        $labels = ['owner_id' => __('Owner'), 'end_date' => __('Target')];

        return new PreparedAction(
            summary: __('Change epic ":name"', ['name' => $epic->name]),
            details: [
                __('Epic') => $epic->name,
                __('Project') => $project->title,
                ...collect($changes)->mapWithKeys(fn ($value, $field) => [
                    $labels[$field] ?? __(ucfirst(str_replace('_', ' ', $field))) => match ($field) {
                        'owner_id' => ($epic->owner?->name ?? '—') . ' → ' . $owner->name,
                        'description' => __('new text: :text', ['text' => mb_strimwidth((string) $value, 0, 80, '…')]),
                        default => ($old[$field] ?? '—') . ' → ' . $value,
                    },
                ])->all(),
            ],
            payload: ['epic_id' => $epic->id, 'changes' => $changes],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        /** @var Epic $epic */
        $epic = $this->resolver->byId($this->resolver->epics($user), (int) $payload['epic_id'], __('epic'));
        $before = $this->snapshot($epic, array_keys($payload['changes']));

        $epic->update($payload['changes']);

        return new ToolOutcome(
            __('Updated epic ":name".', ['name' => $epic->name]),
            $epic,
            route('epics.index', $epic->project_id, false),
            $this->changeUndo($epic, $before),
        );
    }

    public function undo(array $undo, User $user): string
    {
        /** @var Epic $epic */
        $epic = $this->resolver->byId($this->resolver->epics($user), (int) $undo['id'], __('epic'));
        $this->ensureUnchanged($epic, $undo, __('Epic ":name"', ['name' => $epic->name]));

        $epic->update($undo['before']);

        return __('Epic ":name" put back.', ['name' => $epic->name]);
    }
}
