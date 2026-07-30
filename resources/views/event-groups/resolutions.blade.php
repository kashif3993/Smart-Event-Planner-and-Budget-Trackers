<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $group->name }} — Resolution History</title>
    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/event-groups.css') }}?v={{ time() }}">
    <script src="{{ asset('js/event-groups.js') }}?v={{ time() }}" defer></script>
</head>
<body>

    @include('partials.header')
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    @include('partials.sidebar')

    <main class="main-content">
        <div class="dashboard-body">

            <div class="breadcrumb">
                <a href="{{ route('event-groups.index') }}">Event Groups</a>
                <span>/</span>
                <a href="{{ route('event-groups.show', $group) }}">{{ $group->name }}</a>
                <span>/</span>
                <span class="breadcrumb-current">Resolutions</span>
            </div>

            <div class="page-header">
                <div>
                    <h1 class="page-title">Resolution History</h1>
                    <p class="page-subtitle">Every committed contention resolution for this group, with its full rationale.</p>
                </div>
            </div>

            @include('event-groups.partials.subnav')

            @if ($resolutions->isEmpty())
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-clock-rotate-left"></i></div>
                    <h3>No Resolutions Yet</h3>
                    <p>Once a budget contention is resolved and committed, it will appear here.</p>
                </div>
            @else
                @foreach ($resolutions as $resolution)
                    <div class="resolution-history-row">
                        <div class="resolution-history-top">
                            <div>
                                <strong>{{ $resolution->strategy }}</strong>
                                @if ($resolution->ai_used)
                                    <span class="chip chip-warning">AI-generated</span>
                                @endif
                                <span class="chip chip-muted">{{ $resolution->created_at->format('M d, Y g:ia') }}</span>
                            </div>
                            <div>
                                <span style="font-size:13px;color:var(--text-muted);">Deficit absorbed:</span>
                                <strong>{{ $group->currencySymbol() }}{{ number_format($resolution->global_deficit, 0) }}</strong>
                            </div>
                        </div>
                        <table class="roster-table">
                            <thead><tr><th>Event</th><th>Before</th><th>After</th><th>Concession</th><th>Rationale</th></tr></thead>
                            <tbody>
                                @foreach ($resolution->concessions as $c)
                                    <tr>
                                        <td>{{ $c['event_name'] }} {{ $c['immune'] ? '(immune)' : '' }}</td>
                                        <td>{{ $group->currencySymbol() }}{{ number_format($c['before_total_budget'], 0) }}</td>
                                        <td>{{ $group->currencySymbol() }}{{ number_format($c['after_total_budget'], 0) }}</td>
                                        <td>{{ $group->currencySymbol() }}{{ number_format($c['concession_amount'], 0) }}</td>
                                        <td style="font-size:12.5px;color:var(--text-muted);">{{ $c['rationale'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            @endif

        </div>
    </main>

</body>
</html>
