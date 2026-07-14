<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Smart Event Planner</title>
    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <script src="{{ asset('js/dashboard.js') }}" defer></script>
</head>
<body>

    @include('partials.header')
    
    <!-- Sidebar Overlay for mobile -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    
    @include('partials.sidebar')

    <!-- Main Content -->
    <main class="main-content">

        <!-- Dashboard Body -->
        <div class="dashboard-body">
            <div class="page-header">
                <h1 class="page-title">Welcome back, {{ explode(' ', Auth::user()->full_name ?? 'User')[0] }}</h1>
                <p class="page-subtitle">Your planning ecosystem is looking optimized for the week ahead.</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Total Events</div>
                    <div class="stat-value">{{ $totalEvents ?? 0 }}</div>
                    <div class="stat-desc positive">All time events</div>
                    <div class="stat-icon">📅</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-title">Upcoming Events</div>
                    <div class="stat-value">{{ $upcomingEventsCount ?? 0 }}</div>
                    <div class="stat-desc">Scheduled for future</div>
                    <div class="stat-icon">🔜</div>
                </div>

                <div class="stat-card">
                    <div class="stat-title">Budget Status</div>
                    <div class="stat-value" style="color: #0d9488;">On Track</div>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: {{ $budgetPercentage ?? 0 }}%;"></div>
                    </div>
                    <div class="stat-desc">{{ round($budgetPercentage ?? 0) }}% of total budget used</div>
                </div>

                <div class="stat-card">
                    <div class="stat-title">Pending Tasks</div>
                    <div class="stat-value" style="color: #ef4444;">{{ $pendingTasks ?? 0 }}</div>
                    <div class="stat-desc danger">! {{ $highPriorityTasks ?? 0 }} high priority</div>
                    <div class="stat-icon">📋</div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="charts-row">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Monthly Spending</h2>
                        <select style="border: 1px solid var(--border); padding: 6px 12px; border-radius: 6px; outline:none; background:var(--bg-color);">
                            <option>Last 6 Months</option>
                        </select>
                    </div>
                    
                    <div class="bar-chart">
                        @if(isset($monthlySpendPercentages))
                            @foreach($monthlySpendPercentages as $index => $percentage)
                                @php
                                    $bg = '';
                                    if ($index == 2 || $index == 5) {
                                        $bg = 'background-color:#38bdf8;';
                                    }
                                @endphp
                                <div class="bar-group">
                                    <div class="bar" style="height: 100%;">
                                        <div class="bar-fill" style="height: {{ $percentage }}%; {{ $bg }}"></div>
                                    </div>
                                    <div class="bar-label">{{ $monthlyLabels[$index] }}</div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Global Progress</h2>
                    </div>
                    
                    <div class="donut-chart">
                        <div class="donut-inner">
                            <div class="donut-value">{{ $globalProgress ?? 0 }}%</div>
                            <div class="donut-label">COMPLETED</div>
                        </div>
                    </div>
                    
                    <div class="chart-stats">
                        <div>
                            <div class="c-stat-title">Active Stages</div>
                            <div class="c-stat-val">{{ sprintf('%02d', $activeStagesCount ?? 0) }}</div>
                        </div>
                        <div style="text-align: right;">
                            <div class="c-stat-title">Due Today</div>
                            <div class="c-stat-val blue">{{ sprintf('%02d', $dueToday ?? 0) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Row -->
            <div class="bottom-row">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Recent Events</h2>
                        <a href="#" style="color: var(--primary); font-size: 14px; font-weight: 600; text-decoration: none;">View All</a>
                    </div>
                    
                    <div class="event-list">
                        @if(isset($recentEvents) && $recentEvents->count() > 0)
                            @foreach($recentEvents as $event)
                                <div class="event-item">
                                    <div class="event-icon">🚀</div>
                                    <div class="event-info">
                                        <div class="event-name">{{ $event->event_name }}</div>
                                        <div class="event-meta">{{ \Carbon\Carbon::parse($event->event_date)->format('F d, Y') }} • {{ $event->location ?? 'TBD' }}</div>
                                    </div>
                                    <div class="event-status status-{{ strtolower(str_replace(' ', '-', $event->status ?? 'planning')) }}">{{ $event->status ?? 'Planning' }}</div>
                                    <div style="margin-left: 16px; color: var(--text-muted);">›</div>
                                </div>
                            @endforeach
                        @else
                            <!-- Fallback UI if no events -->
                            <div class="event-item">
                                <div class="event-icon">🚀</div>
                                <div class="event-info">
                                    <div class="event-name">Corporate Tech Summit</div>
                                    <div class="event-meta">March 14-16, 2026 • San Francisco</div>
                                </div>
                                <div class="event-status status-active">Active</div>
                                <div style="margin-left: 16px; color: var(--text-muted);">›</div>
                            </div>
                            
                            <div class="event-item">
                                <div class="event-icon">🍸</div>
                                <div class="event-info">
                                    <div class="event-name">Annual Gala Dinner</div>
                                    <div class="event-meta">April 05, 2026 • London</div>
                                </div>
                                <div class="event-status status-planning">Planning</div>
                                <div style="margin-left: 16px; color: var(--text-muted);">›</div>
                            </div>
                            
                            <div class="event-item">
                                <div class="event-icon">🎪</div>
                                <div class="event-info">
                                    <div class="event-name">Product Launch Expo</div>
                                    <div class="event-meta">June 20-22, 2026 • New York</div>
                                </div>
                                <div class="event-status status-draft">Draft</div>
                                <div style="margin-left: 16px; color: var(--text-muted);">›</div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card" style="display: flex; flex-direction: column;">
                    <div class="card-header">
                        <h2 class="card-title">Quick Actions</h2>
                    </div>
                    
                    <div class="quick-actions" style="margin-bottom: 24px;">
                        <div class="action-card">
                            <div class="action-icon">+</div>
                            <div>
                                <div class="action-title">Create Event</div>
                                <div class="action-desc">Start a new planning journey</div>
                            </div>
                        </div>
                        
                        <div class="action-card">
                            <div class="action-icon">✓</div>
                            <div>
                                <div class="action-title">Add Task</div>
                                <div class="action-desc">Assign to team or self</div>
                            </div>
                        </div>
                        
                        <div class="action-card">
                            <div class="action-icon">💵</div>
                            <div>
                                <div class="action-title">Log Expense</div>
                                <div class="action-desc">Track vendor payments</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pro-tip">
                        <h4>PRO TIP</h4>
                        <p>Integrate calendars to sync all deadlines automatically.</p>
                    </div>
                </div>
            </div>

        </div>
    </main>

</body>
</html>
