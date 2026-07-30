<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $group->name }} — Smart Event Planner</title>
    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/event-groups.css') }}?v={{ time() }}">
    <script src="{{ asset('js/event-groups.js') }}?v={{ time() }}" defer></script>
    @if (Route::has('event-groups.contention.snapshot'))
        <script>
            window.contentionSnapshotUrl = @json(route('event-groups.contention.snapshot', $group));
            window.contentionNegotiateUrl = @json(Route::has('event-groups.contention.negotiate') ? route('event-groups.contention.negotiate', $group) : null);
            window.contentionCommitUrl = @json(Route::has('event-groups.contention.commit') ? route('event-groups.contention.commit', $group) : null);
            window.contentionCurrencySymbol = @json($group->currencySymbol());
        </script>
        <script src="{{ asset('js/contention-sandbox.js') }}?v={{ time() }}" defer></script>
    @endif
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

            <div class="breadcrumb">
                <a href="{{ route('event-groups.index') }}">Event Groups</a>
                <span>/</span>
                <span class="breadcrumb-current">{{ $group->name }}</span>
            </div>

            <div class="page-header">
                <div>
                    <h1 class="page-title">{{ $group->name }}</h1>
                    <div class="group-header-meta">
                        <span class="chip {{ $group->status === 'Archived' ? 'chip-muted' : 'chip-success' }}">{{ $group->status }}</span>
                        <span class="mode-badge mode-badge--{{ strtolower($group->budget_mode) }}">
                            <i class="fas fa-{{ $group->isPooled() ? 'circle-dollar-to-slot' : 'layer-group' }}"></i> {{ $group->budget_mode }} Budget
                        </span>
                        <span class="page-subtitle">
                            @if ($group->start_date && $group->end_date)
                                {{ $group->start_date->format('M d') }} – {{ $group->end_date->format('M d, Y') }}
                            @endif
                            • {{ $group->displayType() }} • {{ $group->events->count() }} events
                        </span>
                    </div>
                </div>
                @if (! $group->isArchived())
                    <div class="page-header-actions">
                        @if (Route::has('event-groups.budgetMode.update'))
                            <button type="button" class="btn btn-outline" data-open-modal="budgetModeModal">
                                <i class="fas fa-right-left"></i> Switch Mode
                            </button>
                        @endif
                        <button type="button" class="btn btn-outline" data-open-modal="addEventToGroupModal">
                            <i class="fas fa-plus"></i> Add Event
                        </button>
                        <form action="{{ route('event-groups.archive', $group) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-outline" onclick="return confirm('Archive this group? It will become read-only.')">
                                <i class="fas fa-box-archive"></i> Archive
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            @include('event-groups.partials.subnav')

            @if ($isContended)
                @include('event-groups.partials.contention-banner')
            @endif

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Combined Budget</div>
                    <div class="stat-value">{{ $group->currencySymbol() }}{{ number_format($overview['combined_budget'], 0) }}</div>
                    <div class="stat-desc">{{ $group->isPooled() ? 'Pooled cap' : 'Sum of member budgets' }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Combined Spend</div>
                    <div class="stat-value">{{ $group->currencySymbol() }}{{ number_format($overview['combined_spent'], 0) }}</div>
                    <div class="stat-desc">{{ $overview['combined_percent'] }}% of combined budget</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Remaining</div>
                    <div class="stat-value">{{ $group->currencySymbol() }}{{ number_format($overview['combined_remaining'], 0) }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Group Health</div>
                    <div class="stat-value" style="font-size:20px;">
                        <span class="chip {{ $overview['health'] === 'Over Budget' ? 'chip-danger' : ($overview['health'] === 'On Track' ? 'chip-warning' : 'chip-success') }}">
                            {{ $overview['health'] }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Events in This Group</h2>
                </div>
                <div style="overflow-x:auto;">
                    <table class="rollup-table">
                        <thead>
                            <tr>
                                <th>Event</th><th>Budget</th><th>Spent</th><th>Remaining</th><th>Status</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($overview['rows'] as $row)
                                <tr>
                                    <td><a href="{{ route('events.show', $row->event) }}">{{ $row->event->event_name }}</a></td>
                                    <td>{{ $group->currencySymbol() }}{{ number_format($row->budget, 0) }}</td>
                                    <td>{{ $group->currencySymbol() }}{{ number_format($row->spent, 0) }}</td>
                                    <td>{{ $group->currencySymbol() }}{{ number_format($row->remaining, 0) }}</td>
                                    <td>
                                        <span class="chip {{ $row->status === 'Over Budget' ? 'chip-danger' : ($row->status === 'On Track' ? 'chip-warning' : 'chip-success') }}">
                                            {{ $row->status }}
                                        </span>
                                    </td>
                                    <td>
                                        @if (! $group->isArchived())
                                            <form action="{{ route('event-groups.events.detach', [$group, $row->event]) }}" method="POST"
                                                  onsubmit="return confirm('Remove this event from the group? It becomes standalone; nothing is deleted.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline btn-sm">Remove</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($overview['category_breakdown']->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Combined Category Breakdown</h2></div>
                    <div style="overflow-x:auto;">
                        <table class="rollup-table">
                            <thead><tr><th>Category</th><th>Allocated</th><th>Spent</th></tr></thead>
                            <tbody>
                                @foreach ($overview['category_breakdown'] as $cat)
                                    <tr>
                                        <td>{{ $cat->category_name }}</td>
                                        <td>{{ $group->currencySymbol() }}{{ number_format($cat->allocated, 0) }}</td>
                                        <td>{{ $group->currencySymbol() }}{{ number_format($cat->spent, 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </main>

    @if (! $group->isArchived())
        {{-- Add existing event to group --}}
        <div class="modal-overlay" id="addEventToGroupModal">
            <div class="modal-dialog modal-dialog--sm">
                <div class="modal-header">
                    <h3 class="modal-title">Add an Event</h3>
                    <button type="button" class="modal-close" data-close-modal="addEventToGroupModal">&times;</button>
                </div>
                @php
                    $ungrouped = \App\Models\Event::where('user_id', Auth::id())->whereNull('event_group_id')->orderBy('event_date')->get();
                @endphp
                @if ($group->events->count() >= 6)
                    <div class="modal-body"><p>This group already has the maximum of 6 events.</p></div>
                @elseif ($ungrouped->isEmpty())
                    <div class="modal-body"><p>You don't have any standalone events to add. <a href="{{ route('events.index') }}">Create one</a> first.</p></div>
                @else
                    <form action="{{ route('event-groups.events.attach', $group) }}" method="POST" class="modal-body">
                        @csrf
                        <div class="form-group form-group--full">
                            <label for="event_id">Event</label>
                            <select id="event_id" name="event_id" required>
                                @foreach ($ungrouped as $e)
                                    <option value="{{ $e->id }}">{{ $e->event_name }} ({{ $e->currency }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="modal-footer" style="padding:0;border-top:none;">
                            <button type="button" class="btn btn-outline" data-close-modal="addEventToGroupModal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add to Group</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        @if (Route::has('event-groups.budgetMode.update'))
            @include('event-groups.partials.budget-mode-modal')
        @endif

        @if (Route::has('event-groups.contention.snapshot'))
            @include('event-groups.partials.contention-sandbox-modal')
        @endif
    @endif

</body>
</html>
