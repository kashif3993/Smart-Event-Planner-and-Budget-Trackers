<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $event->event_name }} — Smart Event Planner</title>
    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/events.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/tasks.css') }}?v={{ time() }}">
    <script src="{{ asset('js/events.js') }}?v={{ time() }}" defer></script>
    <script src="{{ asset('js/tasks.js') }}?v={{ time() }}" defer></script>
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
                <a href="{{ route('events.index') }}">Events</a>
                <span>›</span>
                <span class="breadcrumb-current">{{ $event->event_name }}</span>
            </div>

            <div class="page-header event-page-header">
                <div>
                    <h1 class="page-title">{{ $event->event_name }}</h1>
                    <p class="page-subtitle">{{ $event->description ?: 'No description added yet.' }}</p>
                </div>
                <div class="page-header-actions">
                    <button type="button" class="btn btn-outline" id="shareEventBtn">
                        <i class="fas fa-share-alt"></i> Share
                    </button>
                    <button type="button" class="btn btn-primary" data-open-modal="eventModal">
                        <i class="fas fa-pen"></i> Edit Details
                    </button>
                </div>
            </div>

            {{-- Stat Cards --}}
            <div class="stats-grid event-stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Countdown</div>
                    @if ($event->days_remaining > 0)
                        <div class="stat-value"><span class="countdown-number">{{ $event->days_remaining }}</span> Days</div>
                    @elseif ($event->days_remaining === 0)
                        <div class="stat-value" style="font-size:24px;">Today!</div>
                    @else
                        <div class="stat-value" style="font-size:20px; color: var(--text-muted);">Event Passed</div>
                    @endif
                    <div class="stat-desc">
                        <i class="fas fa-clock"></i>
                        {{ $event->event_date->format('F d, Y') }}
                        @if ($event->event_time)
                            • {{ \Carbon\Carbon::parse($event->event_time)->format('g:i A') }}
                        @endif
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-title">Guest Capacity</div>
                    <div class="stat-value" style="display:flex; align-items:baseline; gap:6px;">
                        {{ $event->guest_count }}
                        @if ($event->max_guests)
                            <span style="font-size:16px; color: var(--text-muted); font-weight:600;">/ {{ $event->max_guests }}</span>
                        @endif
                    </div>
                    @if ($event->max_guests)
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: {{ $event->guest_percent }}%;"></div>
                        </div>
                        <div class="stat-desc">{{ $event->guest_percent }}% attendance confirmed</div>
                    @else
                        <div class="stat-desc">Guests confirmed</div>
                    @endif
                </div>

                <div class="stat-card stat-card--venue">
                    <div class="stat-title">Venue</div>
                    <div class="venue-media">
                        @if ($event->venue_image)
                            <img src="{{ asset('storage/'.$event->venue_image) }}" alt="{{ $event->venue_name }}">
                        @else
                            <div class="venue-placeholder"><i class="fas fa-building"></i></div>
                        @endif
                    </div>
                    <div class="venue-name">{{ $event->venue_name ?: 'Venue TBD' }}</div>
                    @if ($event->location)
                        <a class="venue-map-link" target="_blank" rel="noopener"
                           href="https://www.google.com/maps/search/?api=1&query={{ urlencode($event->venue_name.' '.$event->location) }}">
                            <i class="fas fa-map-marker-alt"></i> View on Map
                        </a>
                    @endif
                </div>

                <div class="stat-card">
                    <div class="stat-title">Budget Utilization</div>
                    <div class="stat-value" style="font-size:28px;">
                        {{ $event->currencySymbol() }}{{ \App\Models\Event::abbreviateMoney((float) $event->budget_spent) }}
                    </div>
                    @php
                        $percent = $event->budget_percent;
                        $chipLabel = \App\Models\Event::budgetStatusLabel($percent);
                        $chipClass = match ($chipLabel) {
                            'Over Budget' => 'chip-danger',
                            'On Track' => 'chip-warning',
                            default => 'chip-success',
                        };
                        $remaining = $event->budget_remaining;
                    @endphp
                    <div class="budget-chip-row">
                        <span class="chip {{ $chipClass }}">{{ $chipLabel }}</span>
                        <span class="stat-desc">
                            {{ $event->currencySymbol() }}{{ \App\Models\Event::abbreviateMoney(abs($remaining)) }}
                            {{ $remaining < 0 ? 'over' : 'remaining' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- AI Task Checklist --}}
            <div class="card task-checklist-card">
                <div class="card-header task-checklist-header">
                    <div>
                        <h2 class="card-title">AI Task Checklist</h2>
                        <p class="task-checklist-subtitle">Recommended actions generated by Smart Planner AI</p>
                    </div>
                    <div class="task-checklist-actions">
                        <div class="dropdown phase-filter-dropdown">
                            <button type="button" class="icon-btn" aria-expanded="false" aria-haspopup="true" title="Filter by phase">
                                <i class="fas fa-filter"></i>
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="{{ route('events.show', $event) }}">All Phases</a>
                                <div class="dropdown-divider"></div>
                                @foreach (['Pre-Planning', 'Preparation', 'Day-Of'] as $phase)
                                    <a class="dropdown-item" href="{{ route('events.show', ['event' => $event, 'phase' => $phase]) }}">{{ $phase }}</a>
                                @endforeach
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline" id="generateAiBtn" data-url="{{ route('events.tasks.generateAi', $event) }}">
                            <i class="fas fa-wand-magic-sparkles"></i> Generate with AI
                        </button>
                        <button type="button" class="btn btn-primary" data-open-modal="taskModalAdd">
                            <i class="fas fa-plus"></i> Add Task
                        </button>
                    </div>
                </div>

                <div id="aiGenerateStatus" class="ai-generate-status" style="display:none;"></div>

                @if ($tasks->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-list-check"></i></div>
                        <h3>No Tasks Yet</h3>
                        <p>Add a task manually, or let AI build the checklist for you.</p>
                    </div>
                @else
                    <div class="task-table-wrap">
                        <table class="task-table">
                            <thead>
                                <tr>
                                    <th>Task Name</th>
                                    <th>Due Date</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (['Pre-Planning', 'Preparation', 'Day-Of'] as $phase)
                                    @if ($tasksByPhase->has($phase))
                                        <tr class="phase-group-row">
                                            <td colspan="4">{{ strtoupper($phase) }}</td>
                                        </tr>
                                        @foreach ($tasksByPhase->get($phase) as $task)
                                            <tr class="task-row"
                                                data-task-id="{{ $task->id }}"
                                                data-task-name="{{ $task->task_name }}"
                                                data-task-phase="{{ $task->phase }}"
                                                data-task-priority="{{ $task->priority }}"
                                                data-task-status="{{ $task->status }}"
                                                data-task-due-date="{{ optional($task->due_date)->toDateString() }}"
                                                data-task-dependency-id="{{ $task->dependency_task_id }}"
                                                data-task-notes="{{ $task->notes }}">
                                                <td>
                                                    <div class="task-name-cell">{{ $task->task_name }}</div>
                                                    @if ($task->dependsOn)
                                                        <div class="task-depends-cell"><i class="fas fa-link"></i> Depends on: {{ $task->dependsOn->task_name }}</div>
                                                    @endif
                                                    @if ($task->notes)
                                                        <div class="task-notes-cell">{{ $task->notes }}</div>
                                                    @endif
                                                </td>
                                                <td>{{ optional($task->due_date)->format('M d, Y') ?? '—' }}</td>
                                                <td><span class="chip priority-{{ strtolower($task->priority) }}">{{ $task->priority }}</span></td>
                                                <td>
                                                    <div class="status-cell">
                                                        <span class="status-text">{{ $task->status === 'Completed' ? 'Done' : $task->status }}</span>
                                                        <button type="button"
                                                                class="task-toggle {{ $task->status === 'Completed' ? 'is-on' : '' }}"
                                                                data-toggle-url="{{ route('events.tasks.toggle', [$event, $task]) }}"
                                                                aria-label="Toggle task status"></button>
                                                        <button type="button" class="task-edit-btn" data-open-modal="taskModalEdit" title="Edit task">
                                                            <i class="fas fa-pen"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="task-pagination">
                        <span>Showing {{ $tasks->count() }} of {{ $tasks->total() }} tasks</span>
                        <div class="pagination-controls">
                            <a href="{{ $tasks->previousPageUrl() ?? '#' }}" class="page-btn {{ $tasks->onFirstPage() ? 'disabled' : '' }}">‹</a>
                            <span class="page-current">{{ $tasks->currentPage() }}</span>
                            <a href="{{ $tasks->nextPageUrl() ?? '#' }}" class="page-btn {{ $tasks->hasMorePages() ? '' : 'disabled' }}">›</a>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Smart Planning Insight --}}
            <div class="pro-tip insight-box">
                <h4><i class="fas fa-sparkles"></i> SMART PLANNING INSIGHT</h4>
                <div class="insight-row">
                    <p>{{ $event->ai_insight ?: 'Generate tasks with AI to get a personalized planning insight for this event.' }}</p>
                    <button type="button" class="btn btn-light" title="Coming soon" disabled>Apply Optimization</button>
                </div>
            </div>

        </div>
    </main>

    @include('events.partials.event-form-modal', ['event' => $event])
    @include('events.partials.task-form-modal', ['event' => $event])

</body>
</html>
