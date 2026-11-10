<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\StandupReport;

/** The Standup Bot: per person, yesterday's work, today's tasks and blockers. */
class GetStandup extends AiTool
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly StandupReport $standup,
    ) {}

    public function name(): string
    {
        return 'get_standup';
    }

    public function description(): string
    {
        return 'Standup Bot: for each person on the team (or one person), what they worked on the day before, what they are on today and what blocks them (overdue tasks, critical bugs). Use it for daily stand-ups.';
    }

    public function permissions(): array
    {
        return ['agent_advanced_insights'];
    }

    public function parameters(): array
    {
        return [
            'person' => ['type' => 'string', 'description' => '"me" or a person\'s name or email. Leave out for the whole team.'],
            'date' => ['type' => 'string', 'description' => 'The stand-up day, YYYY-MM-DD. Defaults to today.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $date = $this->resolver->date($args['date'] ?? null, __('Date')) ?? now()->toDateString();
        $person = !empty($args['person']) ? $this->resolver->member($user, (string) $args['person']) : null;

        $standups = $this->standup->forUser($user, $date)
            ->when($person, fn ($all) => $all->filter(fn (array $s) => (int) $s['user']->id === (int) $person->id));

        if ($person && $standups->isEmpty()) {
            throw new ToolInputException(__(':name is not on your projects, so there is no stand-up for them.', ['name' => $person->name]));
        }

        return [
            'date' => $date,
            'people' => $standups->map(fn (array $s) => [
                'name' => $s['user']->name,
                'yesterday' => collect($s['data']['yesterday'])->map(fn ($i) => "{$i['item']} ({$i['project']})")->all(),
                'today' => collect($s['data']['today'])->map(fn ($t) => [
                    'task' => $t->title,
                    'project' => $t->project,
                    'priority' => $t->priority,
                    'progress' => (int) $t->progress,
                ])->all(),
                'blockers' => $s['data']['blockers'],
            ])->values()->all(),
            'link' => route('standup.index', ['date' => $date], false),
        ];
    }
}
