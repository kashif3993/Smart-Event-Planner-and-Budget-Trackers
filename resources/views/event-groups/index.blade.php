<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Event Groups — Smart Event Planner</title>
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

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif

            <div class="page-header">
                <div>
                    <h1 class="page-title">Event Groups</h1>
                    <p class="page-subtitle">Manage related events — a wedding weekend, a multi-part summit — as one undertaking.</p>
                </div>
                <div class="page-header-actions">
                    <a href="{{ route('event-groups.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Group
                    </a>
                </div>
            </div>

            @if ($groups->isEmpty())
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-layer-group"></i></div>
                    <h3>No Event Groups Yet</h3>
                    <p>
                        @if ($ungroupedCount >= 2)
                            You have {{ $ungroupedCount }} events that could be combined into a group.
                        @else
                            Create at least two events, then combine them into a group here.
                        @endif
                    </p>
                    @if ($ungroupedCount >= 2)
                        <a href="{{ route('event-groups.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Your First Group
                        </a>
                    @else
                        <a href="{{ route('events.index') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Events
                        </a>
                    @endif
                </div>
            @else
                <div class="groups-grid">
                    @foreach ($groups as $group)
                        <a href="{{ route('event-groups.show', $group) }}" class="group-card {{ $group->status === 'Archived' ? 'group-card--archived' : '' }}">
                            <div class="group-card-body">
                                <div class="group-card-title">{{ $group->name }}</div>
                                <div class="group-card-meta">
                                    <i class="fas fa-calendar"></i>
                                    @if ($group->start_date && $group->end_date)
                                        {{ $group->start_date->format('M d') }} – {{ $group->end_date->format('M d, Y') }}
                                    @else
                                        Dates pending
                                    @endif
                                </div>
                                <span class="chip {{ $group->status === 'Archived' ? 'chip-muted' : 'chip-success' }}">{{ $group->status }}</span>
                                <span class="mode-badge mode-badge--{{ strtolower($group->budget_mode) }}">{{ $group->budget_mode }}</span>
                            </div>
                            <div class="group-card-footer">
                                <span><i class="fas fa-calendar-days"></i> {{ $group->events_count }} events</span>
                                <span>{{ $group->displayType() }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

        </div>
    </main>

</body>
</html>
