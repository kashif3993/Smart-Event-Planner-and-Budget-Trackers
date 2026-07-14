<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress — Smart Event Planner</title>
    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/events.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/progress.css') }}?v={{ time() }}">
    <script src="{{ asset('js/events.js') }}?v={{ time() }}" defer></script>
    @if($selectedEvent)
        <script>
            window.progressTrendLabels = {!! json_encode($trendLabels) !!};
            window.progressTrendData = {!! json_encode($trendData) !!};
        </script>
    @endif
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="{{ asset('js/progress.js') }}?v={{ time() }}" defer></script>
</head>
<body>

    @include('partials.header')

    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    @include('partials.sidebar')

    <main class="main-content">
        <div class="dashboard-body">

            <div class="page-header">
                <div>
                    <span class="eyebrow-tag">Project Health</span>
                    <h1 class="page-title">Strategic Progress Dashboard</h1>
                    <p class="page-subtitle">
                        @if($selectedEvent)
                            Real-time completion metrics for "{{ $selectedEvent->event_name }}".
                        @else
                            Create an event to start tracking its progress.
                        @endif
                    </p>
                </div>
                <div class="page-header-actions">
                    @if ($userEvents->isNotEmpty())
                        <form method="GET" action="{{ route('progress.index') }}" id="progressEventSwitchForm">
                            <input type="hidden" name="period" value="{{ $period ?? '1W' }}">
                            <select name="event" class="event-switch-select" onchange="this.form.submit()">
                                @foreach ($userEvents as $evt)
                                    <option value="{{ $evt->id }}" @selected($selectedEvent && $selectedEvent->id === $evt->id)>
                                        {{ $evt->event_name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                        @if($selectedEvent)
                            <a href="{{ route('progress.export', ['event' => $selectedEvent->id]) }}" class="btn btn-outline">
                                <i class="fas fa-download"></i> <span class="btn-text">Export Report</span>
                            </a>
                        @endif
                    @endif
                </div>
            </div>

            @if ($userEvents->isEmpty())
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-chart-line"></i></div>
                    <h3>No Events Yet</h3>
                    <p>Create an event first, then come back here to track its progress.</p>
                    <a href="{{ route('events.index') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create an Event
                    </a>
                </div>
            @elseif ($totalTasks === 0)
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-list-check"></i></div>
                    <h3>No Tasks Yet</h3>
                    <p>Add tasks to "{{ $selectedEvent->event_name }}" to start tracking progress.</p>
                    <a href="{{ route('events.show', $selectedEvent) }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Tasks
                    </a>
                </div>
            @else
                {{-- Efficiency donut + side stats --}}
                <div class="progress-grid">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Task Completion Efficiency</h2>
                        </div>

                        <div class="donut-chart" style="--pct: {{ $totalProgress }};">
                            <div class="donut-inner">
                                <div class="donut-value">{{ $totalProgress }}%</div>
                                <div class="donut-label">Total Progress</div>
                            </div>
                        </div>

                        <div class="milestone-row">
                            <div class="milestone-box">
                                <div class="c-stat-title">Milestones</div>
                                <div class="c-stat-val">{{ $milestonesCompleted }}/{{ $milestonesTotal }}</div>
                            </div>
                            <div class="milestone-box">
                                <div class="c-stat-title">Efficiency</div>
                                <div class="c-stat-val {{ $efficiencyChange >= 0 ? 'blue' : 'danger' }}">
                                    {{ $efficiencyChange >= 0 ? '+' : '' }}{{ $efficiencyChange }}%
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="progress-side-stats">
                        <div class="stat-card">
                            <span class="corner-tag {{ $efficiencyChange >= 0 ? 'corner-tag--success' : 'corner-tag--danger' }}">
                                <i class="fas fa-arrow-{{ $efficiencyChange >= 0 ? 'up' : 'down' }}"></i> {{ abs($efficiencyChange) }}%
                            </span>
                            <div class="stat-title">Completed Tasks</div>
                            <div class="stat-value" style="font-size:28px;">{{ $completedCount }}</div>
                        </div>

                        <div class="stat-card">
                            <span class="corner-tag {{ $overdueCount === 0 ? 'corner-tag--success' : 'corner-tag--warning' }}">
                                {{ $overdueCount === 0 ? 'on track' : 'attention' }}
                            </span>
                            <div class="stat-title">Upcoming</div>
                            <div class="stat-value" style="font-size:28px;">{{ $upcomingCount }}</div>
                        </div>

                        <div class="stat-card">
                            <span class="corner-tag {{ $overdueCount > 0 ? 'corner-tag--danger' : 'corner-tag--success' }}">
                                {{ $overdueCount > 0 ? 'urgent' : 'clear' }}
                            </span>
                            <div class="stat-title">Overdue</div>
                            <div class="stat-value" style="font-size:28px; {{ $overdueCount > 0 ? 'color:var(--danger);' : '' }}">{{ $overdueCount }}</div>
                        </div>

                        <div class="stat-card">
                            <span class="corner-tag corner-tag--info">this week</span>
                            <div class="stat-title">Average Vel.</div>
                            <div class="stat-value" style="font-size:28px;">{{ $velocity }}</div>
                        </div>
                    </div>
                </div>

                {{-- Trend + immediate actions --}}
                <div class="progress-bottom-grid">
                    <div class="progress-bottom-left">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Completion Trend</h2>
                                <div class="period-pills">
                                    @foreach (['1W' => '1W', '1M' => '1M', 'ALL' => 'ALL'] as $value => $label)
                                        <a href="{{ route('progress.index', ['event' => $selectedEvent->id, 'period' => $value]) }}"
                                           class="period-pill {{ $period === $value ? 'active' : '' }}">{{ $label }}</a>
                                    @endforeach
                                </div>
                            </div>

                            @if(array_sum($trendData) == 0)
                                <div class="chart-empty">
                                    <i class="fas fa-chart-line fa-2x"></i>
                                    <p>No tasks completed in this period.</p>
                                </div>
                            @else
                                <div class="trend-chart-wrap">
                                    <canvas id="progressTrendChart"></canvas>
                                </div>
                            @endif
                        </div>

                        <div class="progress-mini-grid">
                            <div class="card mini-card">
                                <div class="mini-card-icon mini-card-icon--teal"><i class="fas fa-hourglass-half"></i></div>
                                <div>
                                    <div class="mini-card-title">Days Remaining</div>
                                    <span class="chip {{ $daysRemaining >= 0 ? 'chip-success' : 'chip-warning' }}">
                                        {{ $daysRemaining >= 0 ? $daysRemaining.' '.\Illuminate\Support\Str::plural('day', $daysRemaining) : 'Event Passed' }}
                                    </span>
                                </div>
                            </div>
                            <div class="card mini-card">
                                <div class="mini-card-icon"><i class="fas fa-sack-dollar"></i></div>
                                <div>
                                    <div class="mini-card-title">Budget Health — {{ $budgetPercent }}%</div>
                                    <span class="chip {{ $budgetStatus === 'Over Budget' ? 'chip-danger' : ($budgetStatus === 'On Track' ? 'chip-warning' : 'chip-success') }}">
                                        {{ $budgetStatus }}
                                    </span>
                                </div>
                            </div>
                            <div class="card mini-card">
                                <div class="mini-card-icon"><i class="fas fa-diagram-project"></i></div>
                                <div>
                                    <div class="mini-card-title">AI Task Generator</div>
                                    <span class="chip {{ $aiConfigured ? 'chip-success' : 'chip-warning' }}">
                                        {{ $aiConfigured ? 'Active' : 'Not Configured' }}
                                    </span>
                                </div>
                            </div>
                            <div class="card mini-card">
                                <div class="mini-card-icon mini-card-icon--teal"><i class="fas fa-shield-halved"></i></div>
                                <div>
                                    <div class="mini-card-title">Risk Score</div>
                                    <span class="chip {{ $riskLevel === 'Low' ? 'chip-success' : ($riskLevel === 'Medium' ? 'chip-warning' : 'chip-danger') }}">
                                        {{ $riskLevel }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="progress-bottom-right">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Immediate Actions</h2>
                                <a href="{{ route('events.show', $selectedEvent) }}" class="card-header-link">View All</a>
                            </div>

                            @if($immediateActions->isEmpty() && $unfilledCategories->isEmpty())
                                <div class="chart-empty">
                                    <i class="fas fa-circle-check fa-2x" style="color:#10b981;"></i>
                                    <p>Nothing pending — you're all caught up.</p>
                                </div>
                            @else
                                <div class="action-list">
                                    @foreach($unfilledCategories->take(3) as $category)
                                        <a href="{{ route('vendor-categories.index', ['event' => $selectedEvent->id]) }}" class="action-item">
                                            <div class="action-item-icon priority-medium">
                                                <i class="fas fa-tag"></i>
                                            </div>
                                            <div class="action-item-body">
                                                <div class="action-item-title">{{ $category->category_name }}</div>
                                                <div class="action-item-meta">No budget allocated yet</div>
                                            </div>
                                            <span class="action-item-chevron">›</span>
                                        </a>
                                    @endforeach
                                    @foreach($immediateActions as $task)
                                        @php
                                            $isOverdue = $task->due_date && $task->due_date->isPast();
                                            $dueLabel = 'No due date';
                                            if ($task->due_date) {
                                                if ($isOverdue) {
                                                    $dueLabel = 'Overdue by '.$task->due_date->diffInDays(now()).' '.Str::plural('day', $task->due_date->diffInDays(now()));
                                                } elseif ($task->due_date->isToday()) {
                                                    $dueLabel = 'Due today';
                                                } else {
                                                    $dueLabel = 'Due in '.now()->diffInDays($task->due_date).' '.Str::plural('day', now()->diffInDays($task->due_date));
                                                }
                                            }
                                        @endphp
                                        <a href="{{ route('events.show', $selectedEvent) }}" class="action-item">
                                            <div class="action-item-icon priority-{{ strtolower($task->priority) }}">
                                                <i class="fas fa-{{ $isOverdue ? 'triangle-exclamation' : 'circle-notch' }}"></i>
                                            </div>
                                            <div class="action-item-body">
                                                <div class="action-item-title">{{ $task->task_name }}</div>
                                                <div class="action-item-meta">{{ $dueLabel }} • {{ $task->phase }}</div>
                                            </div>
                                            @if($isOverdue)
                                                <span class="chip chip-danger">Overdue</span>
                                            @else
                                                <span class="action-item-chevron">›</span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Latest Activity</h2>
                                <a href="{{ route('activity.index') }}" class="card-header-link">View All</a>
                            </div>

                            @if($recentActivity->isEmpty())
                                <div class="chart-empty">
                                    <i class="fas fa-clock-rotate-left fa-2x"></i>
                                    <p>No activity logged yet.</p>
                                </div>
                            @else
                                <div class="mini-activity-list">
                                    @foreach($recentActivity as $activity)
                                        <div class="mini-activity-item">
                                            <div class="mini-activity-icon mini-activity-icon--{{ $activity->color ?? 'blue' }}">
                                                <i class="fas {{ $activity->icon ?? 'fa-circle' }}"></i>
                                            </div>
                                            <div class="mini-activity-body">
                                                <div class="mini-activity-desc">{{ $activity->description }}</div>
                                                <div class="mini-activity-time">{{ $activity->created_at->diffForHumans() }}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </main>

</body>
</html>
