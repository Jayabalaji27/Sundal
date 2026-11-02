@php($display = [\App\Services\Reports\ReportRenderer::class, 'display'])
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $doc->title }}</title>
    <style>
        @page { margin: 24px 28px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 0; }
        h1 { font-size: 20px; margin: 0 0 4px 0; }
        h2 { font-size: 14px; margin: 0 0 10px 0; color: #111827; }
        .muted { color: #6b7280; }
        .accent { height: 3px; background: {{ $primaryColor }}; margin-bottom: 14px; }
        .meta-table { border-collapse: collapse; margin: 10px 0 18px 0; }
        .meta-table td { padding: 2px 16px 2px 0; font-size: 10px; vertical-align: top; }
        .meta-table td.label { color: #6b7280; }
        .section { margin-bottom: 20px; }
        .stats-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .stats-table td { padding: 10px; border: 1px solid #e5e7eb; text-align: center; }
        .stats-table .stat-value { font-size: 16px; font-weight: bold; color: {{ $primaryColor }}; }
        .stats-table .stat-label { font-size: 9px; color: #6b7280; text-transform: uppercase; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        table.data th {
            background: #f3f4f6;
            text-align: left;
            padding: 6px 8px;
            font-size: 9px;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
        }
        table.data td { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; font-size: 10px; }
        .empty { font-style: italic; color: #6b7280; margin: 0 0 18px 0; }
        .footer { margin-top: 20px; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="accent"></div>
    <h1>{{ $doc->title }}</h1>
    @if($doc->subtitle)
        <div class="muted">{{ $doc->subtitle }}</div>
    @endif

    @if($doc->meta)
        <table class="meta-table">
            @foreach($doc->meta as $label => $value)
                <tr>
                    <td class="label">{{ $label }}</td>
                    <td>{{ $display($value) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if($doc->summary)
        <table class="stats-table">
            <tr>
                @foreach($doc->summary as $label => $value)
                    <td style="width: {{ round(100 / count($doc->summary), 2) }}%;">
                        <div class="stat-value">{{ $display($value) }}</div>
                        <div class="stat-label">{{ $label }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @foreach($doc->sections as $section)
        <div class="section">
            <h2>{{ $section['title'] }}</h2>
            @if(count($section['rows']))
                <table class="data">
                    <thead>
                        <tr>
                            @foreach($section['columns'] as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['rows'] as $row)
                            <tr>
                                @foreach($row as $cell)
                                    <td>{{ $display($cell) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if(!empty($section['note']))
                    <p class="empty" style="margin-top: -10px;">{{ $section['note'] }}</p>
                @endif
            @else
                <p class="empty">{{ $section['empty'] }}</p>
            @endif
        </div>
    @endforeach

    <div class="footer">
        Generated on {{ now()->format('M d, Y H:i') }}
    </div>
</body>
</html>
