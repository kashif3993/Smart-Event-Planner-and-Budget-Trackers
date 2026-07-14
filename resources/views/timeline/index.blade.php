<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Timeline — Smart Event Planner</title>
    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/events.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/timeline.css') }}?v={{ time() }}">
    <script src="{{ asset('js/events.js') }}?v={{ time() }}" defer></script>
    <script src="{{ asset('js/tasks.js') }}?v={{ time() }}" defer></script>
    <script src="{{ asset('js/timeline.js') }}?v={{ time() }}" defer></script>
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

            <div class="page-header timeline-page-header">
                <div>
                    <h1 class="page-title">Event Timeline</h1>
                    <p class="page-subtitle">
                        @if($selectedEvent)
                            Project: <strong class="timeline-project-name">{{ $selectedEvent->event_name }}</strong> • Track your milestones and logistics in a real-time chronological view.
                        @else
                            Create an event to start building its timeline.
                        @endif
                    </p>
                </div>

                @if ($userEvents->isNotEmpty())
                    <div class="page-header-actions timeline-header-actions">
                        <form method="GET" action="{{ route('timeline.index') }}" id="timelineEventForm">
                            <input type="hidden" name="view" value="{{ $view ?? 'vertical' }}">
                            <input type="hidden" name="status" value="{{ $statusFilter ?? 'all' }}">
                            <select name="event" class="event-switch-select" onchange="this.form.submit()">
                                @foreach ($userEvents as $evt)
                                    <option value="{{ $evt->id }}" @selected($selectedEvent && $selectedEvent->id === $evt->id)>
                                        {{ $evt->event_name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>

                        @if($selectedEvent)
                            <div class="timeline-stat-pill">
                                <span class="timeline-stat-icon"><i class="fas fa-stopwatch"></i></span>
                                <div>
                                    <div class="timeline-stat-label">Days Left</div>
                                    <div class="timeline-stat-value">{{ $daysLeft >= 0 ? $daysLeft : 'Passed' }}</div>
                                </div>
                            </div>
                            <div class="timeline-stat-pill">
                                <span class="timeline-stat-icon timeline-stat-icon--success"><i class="fas fa-circle-check"></i></span>
                                <div>
                                    <div class="timeline-stat-label">Completed</div>
                                    <div class="timeline-stat-value">{{ $completedPercent }}%</div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            @if ($userEvents->isEmpty())
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-timeline"></i></div>
                    <h3>No Events Yet</h3>
                    <p>Create an event first, then come back here to build its timeline.</p>
                    <a href="{{ route('events.index') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create an Event
                    </a>
                </div>
            @elseif ($totalTasks === 0)
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-list-check"></i></div>
                    <h3>No Milestones Yet</h3>
                    <p>Add tasks to "{{ $selectedEvent->event_name }}" to see them laid out on a timeline.</p>
                    <a href="{{ route('events.show', $selectedEvent) }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Tasks
                    </a>
                </div>
            @else
                @if ($leadTimeTier !== 'standard')
                    <div class="lead-time-banner lead-time-banner--{{ $leadTimeTier }}" id="timelinePrintHide">
                        <i class="fas fa-gauge-high"></i>
                        <div>
                            @if ($leadTimeTier === 'urgent')
                                <strong>Compressed timeline</strong> — only {{ $daysLeft }} {{ Str::plural('day', $daysLeft) }} remain, so this checklist skips long-lead Pre-Planning research and focuses on what's actionable now.
                            @else
                                <strong>Condensed timeline</strong> — {{ $daysLeft }} days remain, so Pre-Planning is trimmed to the essentials and Preparation/Day-Of carry most of the milestones.
                            @endif
                            @if ($missingPhases->isNotEmpty())
                                <span class="lead-time-missing">No {{ $missingPhases->implode(' or ') }} milestones yet.</span>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="timeline-controls" id="timelinePrintHide">
                    <div class="view-pills">
                        <a href="{{ route('timeline.index', ['event' => $selectedEvent->id, 'status' => $statusFilter, 'view' => 'vertical']) }}"
                           class="view-pill {{ $view === 'vertical' ? 'active' : '' }}">Vertical View</a>
                        <a href="{{ route('timeline.index', ['event' => $selectedEvent->id, 'status' => $statusFilter, 'view' => 'horizontal']) }}"
                           class="view-pill {{ $view === 'horizontal' ? 'active' : '' }}">Horizontal</a>
                    </div>

                    <div class="timeline-controls-right">
                        <div class="dropdown status-filter-dropdown">
                            <button type="button" class="btn btn-outline" aria-expanded="false" aria-haspopup="true">
                                <i class="fas fa-filter"></i>
                                {{ $statusFilter === 'all' ? 'All Statuses' : ucfirst($statusFilter) }}
                            </button>
                            <div class="dropdown-menu">
                                @foreach (['all' => 'All Statuses', 'completed' => 'Completed', 'pending' => 'Pending', 'upcoming' => 'Upcoming', 'skipped' => 'Skipped'] as $value => $label)
                                    <a class="dropdown-item {{ $statusFilter === $value ? 'is-active' : '' }}"
                                       href="{{ route('timeline.index', ['event' => $selectedEvent->id, 'view' => $view, 'status' => $value]) }}">{{ $label }}</a>
                                @endforeach
                            </div>
                        </div>

                        <button type="button" class="btn btn-outline" id="exportTimelineBtn">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                    </div>
                </div>

                @if ($items->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-filter-circle-xmark"></i></div>
                        <h3>No Milestones Match This Filter</h3>
                        <p>Try a different status filter to see more of your timeline.</p>
                        <a href="{{ route('timeline.index', ['event' => $selectedEvent->id, 'view' => $view]) }}" class="btn btn-outline">
                            Clear Filter
                        </a>
                    </div>
                @elseif ($view === 'vertical')
                    <div class="timeline-track" id="timelineExportArea">
                        <div class="timeline-line"></div>

                        @foreach ($items as $item)
                            <div class="timeline-row">
                                @foreach (['left' => ($item['side'] === 'left' ? 'header' : 'card'), 'right' => ($item['side'] === 'left' ? 'card' : 'header')] as $col => $type)
                                    <div class="timeline-col timeline-col-{{ $col }} timeline-col--{{ $type }}">
                                        @if ($type === 'header')
                                            <span class="phase-tag">{{ $item['phase'] }}</span>
                                            <h3 class="timeline-title">{{ $item['title'] }}</h3>
                                            <p class="timeline-subtitle">{{ $item['subtitle'] }}</p>
                                        @else
                                            <div class="timeline-card">
                                                <div class="timeline-card-top">
                                                    <span class="timeline-due">
                                                        Due Date: {{ $item['dueDate']?->format('M d, Y') ?? 'Not set' }}
                                                    </span>
                                                    <span class="timeline-status timeline-status--{{ $item['statusKey'] }}">
                                                        <i class="fas fa-circle"></i> {{ $item['statusLabel'] }}
                                                    </span>
                                                </div>
                                                <p class="timeline-desc">{{ $item['description'] }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach

                                <div class="timeline-node">
                                    <span class="timeline-dot timeline-dot--{{ $item['statusKey'] }}">
                                        @if ($item['statusKey'] === 'completed')
                                            <i class="fas fa-check"></i>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="timeline-horizontal" id="timelineExportArea">
                        <div class="timeline-h-line"></div>
                        <div class="timeline-h-track">
                            @foreach ($items as $item)
                                <div class="timeline-h-card">
                                    <span class="timeline-dot timeline-dot--{{ $item['statusKey'] }} timeline-dot--sm">
                                        @if ($item['statusKey'] === 'completed')
                                            <i class="fas fa-check"></i>
                                        @endif
                                    </span>
                                    <span class="phase-tag">{{ $item['phase'] }}</span>
                                    <h3 class="timeline-title">{{ $item['title'] }}</h3>
                                    <p class="timeline-subtitle">{{ $item['subtitle'] }}</p>
                                    <div class="timeline-card-top">
                                        <span class="timeline-due">
                                            Due: {{ $item['dueDate']?->format('M d, Y') ?? 'Not set' }}
                                        </span>
                                        <span class="timeline-status timeline-status--{{ $item['statusKey'] }}">
                                            <i class="fas fa-circle"></i> {{ $item['statusLabel'] }}
                                        </span>
                                    </div>
                                    <p class="timeline-desc">{{ $item['description'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="timeline-bottom-grid" id="timelinePrintHide">
                    <div class="card timeline-velocity-card">
                        <h2 class="card-title">Timeline Velocity</h2>
                        <p class="timeline-velocity-text">
                            You are completing milestones
                            <strong class="{{ $velocityChange >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ abs($velocityChange) }}% {{ $velocityChange >= 0 ? 'faster' : 'slower' }}
                            </strong>
                            than last week. Keep up the momentum!
                        </p>

                        <div class="velocity-bar-chart">
                            @foreach ($weeklyBarPercents as $percent)
                                <div class="velocity-bar" style="height: {{ $percent }}%;"></div>
                            @endforeach
                        </div>
                    </div>

                    <div class="card launch-mode-card">
                        <div class="launch-mode-icon"><i class="fas fa-rocket"></i></div>
                        <h2 class="card-title">Launch Mode</h2>
                        <p class="launch-mode-text">Let AI generate the next milestone for this event automatically.</p>

                        <div id="aiGenerateStatus" class="ai-generate-status" style="display:none;"></div>

                        <button type="button" class="btn btn-dark launch-mode-btn" id="generateAiBtn"
                                data-url="{{ route('events.tasks.generateAi', $selectedEvent) }}">
                            Enable Automation
                        </button>
                    </div>
                </div>
            @endif

        </div>
    </main>

</body>
</html>
