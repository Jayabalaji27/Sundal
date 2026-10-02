<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $project->title ?? $project->name }} - Project Report</title>
    <style>
        @page { margin: 24px 28px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 0; }
        h1 { font-size: 20px; margin: 0 0 4px 0; }
        h2 { font-size: 14px; margin: 0 0 10px 0; color: #111827; }
        .muted { color: #6b7280; }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
        }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .header-table td { vertical-align: top; }
        .section { margin-bottom: 20px; }
        .stats-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .stats-table td {
            width: 20%;
            padding: 10px;
            border: 1px solid #e5e7eb;
            text-align: center;
        }
        .stats-table .stat-value { font-size: 16px; font-weight: bold; color: {{ $primaryColor }}; }
        .stats-table .stat-label { font-size: 9px; color: #6b7280; text-transform: uppercase; }
        .charts-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .charts-table td { width: 50%; text-align: center; padding: 6px; }
        .charts-table img { max-width: 100%; height: auto; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.data th {
            background: #f3f4f6;
            text-align: left;
            padding: 6px 8px;
            font-size: 9px;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
        }
        table.data td {
            padding: 6px 8px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 10px;
        }
        .footer { margin-top: 20px; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 70%;">
                <h1>{{ $project->title ?? $project->name }}</h1>
                <span class="badge" style="background: {{ $statusStyle['bg'] }}; color: {{ $statusStyle['color'] }};">
                    {{ $projectStatusText }}
                </span>
                <p class="muted" style="margin-top: 8px;">
                    {{ $project->description ?? '' }}
                </p>
            </td>
            <td style="width: 30%; text-align: right;">
                <p class="muted" style="margin: 0;">Start Date</p>
                <p style="margin: 0 0 8px 0;">{{ optional($project->start_date)->format('M d, Y') ?? '-' }}</p>
                <p class="muted" style="margin: 0;">Deadline</p>
                <p style="margin: 0;">{{ optional($project->deadline)->format('M d, Y') ?? '-' }}</p>
            </td>
        </tr>
    </table>

    <table class="stats-table">
        <tr>
            <td>
                <div class="stat-value">{{ $stats['completion_percentage'] }}%</div>
                <div class="stat-label">Progress</div>
            </td>
            <td>
                <div class="stat-value">{{ $stats['completed_tasks'] }}/{{ $stats['total_tasks'] }}</div>
                <div class="stat-label">Tasks Done</div>
            </td>
            <td>
                <div class="stat-value">{{ $stats['completed_milestones'] }}/{{ $stats['total_milestones'] }}</div>
                <div class="stat-label">Milestones</div>
            </td>
            <td>
                <div class="stat-value">{{ $stats['total_logged_hours'] }}h</div>
                <div class="stat-label">Logged Hours</div>
            </td>
            <td>
                <div class="stat-value">{{ $stats['days_left'] !== null ? $stats['days_left'] : '-' }}</div>
                <div class="stat-label">Days Left</div>
            </td>
        </tr>
    </table>

    <div class="section">
        <h2>Overview</h2>
        <table class="charts-table">
            <tr>
                <td><img src="data:image/png;base64,{{ $base64Image }}" alt="Task completion"></td>
                <td><img src="data:image/png;base64,{{ $base64ArcImage }}" alt="Milestone progress"></td>
            </tr>
            <tr>
                <td><img src="data:image/png;base64,{{ $base64PriorityImage }}" alt="Task priority"></td>
                <td><img src="data:image/png;base64,{{ $base64StatusImage }}" alt="Task status"></td>
            </tr>
        </table>
        <img src="data:image/png;base64,{{ $base64HoursImage }}" alt="Hours logged" style="max-width: 100%;">
    </div>

    @if(count($userStats))
    <div class="section">
        <h2>Team Performance</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Assigned Tasks</th>
                    <th>Completed Tasks</th>
                </tr>
            </thead>
            <tbody>
                @foreach($userStats as $u)
                <tr>
                    <td>{{ $u['name'] }}</td>
                    <td>{{ $u['assigned_tasks'] }}</td>
                    <td>{{ $u['done_tasks'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(count($tasks))
    <div class="section">
        <h2>Tasks</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Stage</th>
                    <th>Priority</th>
                    <th>Assigned To</th>
                    <th>Progress</th>
                    <th>Due Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tasks as $task)
                <tr>
                    <td>{{ $task->title }}</td>
                    <td>{{ $task->taskStage->name ?? '-' }}</td>
                    <td>{{ ucfirst($task->priority ?? '-') }}</td>
                    <td>{{ $task->assignedUser->name ?? '-' }}</td>
                    <td>{{ $task->progress ?? 0 }}%</td>
                    <td>{{ optional($task->end_date)->format('M d, Y') ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        Generated on {{ now()->format('M d, Y H:i') }}
    </div>
</body>
</html>
