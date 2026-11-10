<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('System update') }} · {{ config('app.name') }}</title>
    {{-- Self-contained on purpose: this page must open before the update has run. --}}
    <style>
        :root { --bg: #f6f7f9; --card: #fff; --text: #1f2937; --muted: #6b7280; --line: #e5e7eb; --ok: #15803d; --bad: #b91c1c; --warn-bg: #fff7ed; --warn-line: #fdba74; --btn: #111827; --btn-text: #fff; --code: #f3f4f6; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0f1115; --card: #171a21; --text: #e5e7eb; --muted: #9ca3af; --line: #2a2f3a; --ok: #4ade80; --bad: #f87171; --warn-bg: #2a1d0f; --warn-line: #9a5b13; --btn: #e5e7eb; --btn-text: #111827; --code: #0b0d11; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        main { max-width: 760px; margin: 0 auto; padding: 32px 16px 64px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 16px; margin: 0 0 12px; }
        .muted { color: var(--muted); }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 20px; margin-top: 16px; }
        .warn { background: var(--warn-bg); border-color: var(--warn-line); }
        .ok { color: var(--ok); }
        .bad { color: var(--bad); }
        ul { margin: 8px 0 0; padding-left: 20px; }
        code, pre { font: 12.5px/1.45 ui-monospace, SFMono-Regular, Consolas, monospace; }
        pre { background: var(--code); border: 1px solid var(--line); border-radius: 6px; padding: 10px; margin: 6px 0 0; white-space: pre-wrap; word-break: break-word; max-height: 260px; overflow: auto; }
        button { background: var(--btn); color: var(--btn-text); border: 0; border-radius: 8px; padding: 10px 18px; font: inherit; font-weight: 600; cursor: pointer; }
        button[disabled] { opacity: .6; cursor: wait; }
        .step { border-top: 1px solid var(--line); padding: 10px 0; }
        .step:first-of-type { border-top: 0; }
        details summary { cursor: pointer; }
        a { color: inherit; }
    </style>
</head>
<body>
<main>
    <h1>{{ __('System update') }}</h1>
    <p class="muted">{{ __('After uploading a new version of :app, run its database changes here. No terminal is needed.', ['app' => config('app.name')]) }}</p>

    @if (session('system_update_error'))
        <div class="card warn"><strong class="bad">{{ session('system_update_error') }}</strong></div>
    @endif

    <section class="card">
        <h2>{{ __('Status') }}</h2>
        @if (count($pending))
            <p><strong>{{ trans_choice(':count database change is waiting.|:count database changes are waiting.', count($pending), ['count' => count($pending)]) }}</strong></p>
            <details>
                <summary>{{ __('Show them') }}</summary>
                <ul>@foreach ($pending as $migration)<li><code>{{ $migration }}</code></li>@endforeach</ul>
            </details>
        @else
            <p class="ok"><strong>{{ __('The database is up to date.') }}</strong></p>
        @endif

        <p style="margin-bottom:0">{{ __('Uploaded files link (public/storage):') }}
            @if ($storageLink === 'link')
                <span class="ok">{{ __('OK') }}</span>
            @elseif ($storageLink === 'folder')
                <span class="bad">{{ __('it is a normal folder, so new uploads may not show. In File Manager, move its files into storage/app/public, delete public/storage, and run the update.') }}</span>
            @else
                <span class="bad">{{ __('missing; the update creates it.') }}</span>
            @endif
        </p>
    </section>

    <section class="card warn">
        <h2>{{ __('Before you run it') }}</h2>
        <p style="margin:0">{{ __('Back up the database first: cPanel → phpMyAdmin → select the database → Export → Go. Some changes move data and cannot be undone without that backup.') }}</p>
    </section>

    <section class="card">
        <h2>{{ __('Run the update') }}</h2>
        <p class="muted">{{ __('Runs the waiting database changes, updates permissions and roles, and clears the caches. It is safe to run again. Keep this tab open until it finishes.') }}</p>
        <form method="POST" action="{{ route('system-update.run') }}" onsubmit="if (!confirm(@js(__('Have you backed up the database? Run the update now?')))) return false; this.querySelector('button').disabled = true; this.querySelector('button').textContent = @js(__('Updating… keep this tab open'));">
            @csrf
            <button type="submit">{{ __('Run update') }}</button>
        </form>
    </section>

    @if ($lastRun)
        <section class="card">
            <h2>{{ __('Last run') }}: {{ $lastRun['at'] }}
                @if ($lastRun['ok'])<span class="ok">· {{ __('finished') }}</span>@else<span class="bad">· {{ __('stopped with an error') }}</span>@endif
            </h2>
            @foreach ($lastRun['steps'] as $step)
                <div class="step">
                    <strong class="{{ $step['ok'] ? 'ok' : 'bad' }}">{{ $step['ok'] ? '✓' : '✗' }} {{ $step['label'] }}</strong>
                    @if ($step['output'] !== '')
                        <details @if (!$step['ok']) open @endif><summary class="muted">{{ __('Details') }}</summary><pre>{{ $step['output'] }}</pre></details>
                    @endif
                </div>
            @endforeach
            @unless ($lastRun['ok'])
                <p class="bad" style="margin-bottom:0">{{ __('Nothing after the failed step ran. Fix the cause, or restore the backup, then run it again.') }}</p>
            @endunless
        </section>
    @endif

    <p style="margin-top:24px"><a href="{{ route('dashboard') }}">← {{ __('Back to the dashboard') }}</a></p>
</main>
</body>
</html>
