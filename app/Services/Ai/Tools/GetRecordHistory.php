<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * "Who created this story?", "Who changed this bug?" Reads the creator column
 * and the history log (App\Services\History\HistoryRecorder).
 */
class GetRecordHistory extends AiTool
{
    private const TYPES = ['task', 'bug', 'project', 'invoice'];

    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'get_record_history';
    }

    public function description(): string
    {
        return 'Who created a task, story, bug, project or invoice, and every later change: who, when, what changed (old and new values), and whether it came from a normal screen or the AI assistant.';
    }

    public function permissions(): array
    {
        return ['task_view_any', 'bug_view_any', 'project_view_any', 'invoice_view_any'];
    }

    public function parameters(): array
    {
        return [
            'type' => ['type' => 'enum', 'options' => self::TYPES, 'required' => true, 'description' => 'Kind of record. Stories are tasks.'],
            'record' => ['type' => 'string', 'required' => true, 'description' => 'Title, invoice number or id.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $type = $args['type'] ?? '';
        $ref = (string) ($args['record'] ?? '');

        $permission = ['task' => 'task_view_any', 'bug' => 'bug_view_any', 'project' => 'project_view_any', 'invoice' => 'invoice_view_any'][$type] ?? null;
        if (!$permission || !$user->hasWorkspacePermission($permission)) {
            throw new ToolInputException(__('This user cannot view the history of that kind of record.'));
        }

        [$record, $creatorColumn] = match ($type) {
            'task' => [$this->resolver->task($user, $ref), 'created_by'],
            'bug' => [$this->resolver->bug($user, $ref), 'reported_by'],
            'project' => [$this->resolver->project($user, $ref), 'created_by'],
            'invoice' => [$this->invoice($user, $ref), 'created_by'],
        };

        $creator = $record->{$creatorColumn} ? User::find($record->{$creatorColumn}) : null;

        $history = Activity::query()
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', $record->getKey())
            ->with('causer')
            ->latest('id')
            ->limit(30)
            ->get();

        return [
            'record' => $record->title ?? $record->invoice_number ?? "#{$record->getKey()}",
            'created_by' => $creator?->name,
            'created_at' => $record->created_at?->toDateTimeString(),
            'history' => $history->map(fn (Activity $a) => [
                'when' => $a->created_at?->toDateTimeString(),
                'who' => $a->causer?->name ?? 'system',
                'event' => $a->event,
                'source' => $a->properties['source'] ?? null,
                'changes' => $this->describe($a),
            ])->all(),
        ];
    }

    private function describe(Activity $activity): array
    {
        $new = collect($activity->properties['attributes'] ?? []);
        $old = collect($activity->properties['old'] ?? []);

        if ($activity->event !== 'updated') {
            return [];
        }

        return $new->map(fn ($value, $field) => ['field' => $field, 'from' => $old[$field] ?? null, 'to' => $value])->values()->all();
    }

    private function invoice(User $user, string $ref): Model
    {
        $visible = ListInvoices::visible($user);

        return (clone $visible)->where('invoice_number', trim($ref))->first()
            ?? (preg_match('/^#?(\d+)$/', trim($ref), $m) ? (clone $visible)->whereKey((int) $m[1])->first() : null)
            ?? throw new ToolInputException(__('No invoice matches ":ref" that this user can see.', ['ref' => $ref]));
    }
}
