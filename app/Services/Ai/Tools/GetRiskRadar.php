<?php

namespace App\Services\Ai\Tools;

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectHealthService;

/** The Risk Radar page: health of the open projects the user may see, worst first. */
class GetRiskRadar extends AiTool
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly ProjectHealthService $health,
    ) {}

    public function name(): string
    {
        return 'get_risk_radar';
    }

    public function description(): string
    {
        return 'Risk Radar: the health score (0-100) of every planning, active and on-hold project, worst first, with the reasons (overdue tasks, budget, scope creep…). Use it for "which projects are at risk?".';
    }

    public function permissions(): array
    {
        return ['agent_advanced_insights'];
    }

    public function parameters(): array
    {
        return [
            'status' => ['type' => 'enum', 'options' => ['critical', 'at_risk', 'healthy'], 'description' => 'Only projects with this health.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $projects = $this->resolver->projects($user)
            ->whereIn('status', ['planning', 'active', 'on_hold'])
            ->get()
            ->map(function (Project $p) {
                $h = $this->health->calculate($p);

                return [
                    'project' => $p->title,
                    'status' => $p->status,
                    'progress' => (int) $p->progress,
                    'deadline' => $p->deadline?->format('Y-m-d'),
                    'health_score' => $h['score'],
                    'health' => $h['status'],
                    'reasons' => collect($h['factors'])->pluck('message')->all(),
                    'link' => route('projects.show', $p->id, false),
                ];
            })
            ->sortBy('health_score')
            ->values();

        $shown = !empty($args['status']) ? $projects->where('health', $args['status'])->values() : $projects;

        return [
            'summary' => [
                'critical' => $projects->where('health', 'critical')->count(),
                'at_risk' => $projects->where('health', 'at_risk')->count(),
                'healthy' => $projects->where('health', 'healthy')->count(),
            ],
            'projects' => $shown->take(20)->all(),
            'link' => route('risk-radar.index', [], false),
        ];
    }
}
