<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Budget Analytics — Smart Event Planner</title>
    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/events.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/budget.css') }}?v={{ time() }}">
    <script src="{{ asset('js/events.js') }}?v={{ time() }}" defer></script>
    @if($selectedEvent)
        <script>
            window.budgetCategoryLabels = {!! json_encode($categories->pluck('category_name')) !!};
            window.budgetCategoryAllocations = {!! json_encode($categories->pluck('allocated_amount')->map(fn($v) => (float) $v)) !!};
            window.budgetTrendLabels = {!! json_encode($trendLabels) !!};
            window.budgetTrendData = {!! json_encode($trendData) !!};
        </script>
    @endif
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="{{ asset('js/budget.js') }}?v={{ time() }}" defer></script>
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
            @if ($errors->any())
                <div class="alert alert-error">{{ $errors->first() }}</div>
            @endif

            <div class="page-header">
                <div>
                    <h1 class="page-title">Budget Analytics</h1>
                    <p class="page-subtitle">
                        @if($selectedEvent)
                            Track expenses and manage allocations for "{{ $selectedEvent->event_name }}".
                        @else
                            Create an event to start tracking its budget.
                        @endif
                    </p>
                </div>
                <div class="page-header-actions">
                    @if ($userEvents->isNotEmpty())
                        <form method="GET" action="{{ route('budget.index') }}" id="eventSwitchForm">
                            <input type="hidden" name="days" value="{{ $days ?? 30 }}">
                            <select name="event" class="event-switch-select" onchange="this.form.submit()">
                                @foreach ($userEvents as $evt)
                                    <option value="{{ $evt->id }}" @selected($selectedEvent && $selectedEvent->id === $evt->id)>
                                        {{ $evt->event_name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                        @if($selectedEvent)
                            <a href="{{ route('budget.export', ['event' => $selectedEvent->id]) }}" class="btn btn-outline">
                                <i class="fas fa-download"></i> <span class="btn-text">Export Report</span>
                            </a>
                            <button type="button" class="btn btn-primary" data-open-modal="addExpenseModal">
                                <i class="fas fa-plus"></i> <span class="btn-text">Add Expense</span>
                            </button>
                        @endif
                    @endif
                </div>
            </div>

            @if ($userEvents->isEmpty())
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-sack-dollar"></i></div>
                    <h3>No Events Yet</h3>
                    <p>Create an event first, then come back here to analyze its budget.</p>
                    <a href="{{ route('events.index') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create an Event
                    </a>
                </div>
            @else
                {{-- Stat Cards --}}
                <div class="stats-grid budget-stats-grid">
                    <div class="stat-card">
                        <div class="stat-title">Total Budget</div>
                        <div class="stat-value" style="font-size:26px;">
                            {{ $selectedEvent->currencySymbol() }}{{ number_format($totalBudget, 2) }}
                        </div>
                        <div class="stat-desc {{ $velocityPercent >= 0 ? 'positive' : 'danger' }}">
                            <i class="fas fa-arrow-{{ $velocityPercent >= 0 ? 'up' : 'down' }}"></i>
                            {{ abs($velocityPercent) }}% from last month
                        </div>
                    </div>

                    <div class="stat-card">
                        @if($isHighVelocity)
                            <span class="corner-tag corner-tag--danger">High Velocity</span>
                        @endif
                        <div class="stat-title">Total Spent</div>
                        <div class="stat-value" style="font-size:26px;">
                            {{ $selectedEvent->currencySymbol() }}{{ number_format($totalSpent, 2) }}
                        </div>
                        <div class="stat-desc">{{ $categories->count() }} {{ Str::plural('category', $categories->count()) }} tracked</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-title">Remaining Budget</div>
                        <div class="stat-value" style="font-size:26px; {{ $remainingBudget < 0 ? 'color: var(--danger);' : '' }}">
                            {{ $remainingBudget < 0 ? '-' : '' }}{{ $selectedEvent->currencySymbol() }}{{ number_format(abs($remainingBudget), 2) }}
                        </div>
                        <div class="stat-desc">{{ $remainingBudget < 0 ? 'over allocated budget' : 'available to allocate' }}</div>
                    </div>

                    <div class="stat-card">
                        @if($healthStatus === 'Under Budget')
                            <span class="corner-tag corner-tag--success">Under Budget</span>
                        @endif
                        <div class="stat-title">Budget Health</div>
                        <div class="stat-value" style="font-size:26px;">{{ $healthPercent }}%</div>
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: {{ $healthPercent }}%; {{ $healthStatus !== 'Under Budget' ? 'background-color:' . ($healthStatus === 'Over Budget' ? 'var(--danger)' : '#f59e0b') . ';' : '' }}"></div>
                        </div>
                        <span class="chip {{ $healthChipClass }}">{{ $healthStatus }}</span>
                    </div>
                </div>

                {{-- Allocation + Actual vs Estimated --}}
                <div class="budget-charts-grid">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Allocation by Category</h2>
                        </div>

                        @if($categories->isEmpty())
                            <div class="chart-empty">
                                <i class="fas fa-chart-pie fa-2x"></i>
                                <p>No vendor categories yet.</p>
                                <a href="{{ route('vendor-categories.index', ['event' => $selectedEvent->id]) }}" class="btn btn-outline">Add Categories</a>
                            </div>
                        @else
                            <div class="donut-wrap">
                                <canvas id="allocationChart"></canvas>
                                <div class="donut-center">
                                    <span class="donut-center-label">Top Category</span>
                                    <span class="donut-center-value">{{ $topCategory->category_name ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="chart-legend">
                                @php $legendColors = ['#4f46e5','#8b5cf6','#0d9488','#ef4444','#f59e0b','#06b6d4']; $li = 0; @endphp
                                @foreach($categories as $cat)
                                    @php
                                        $pct = $totalBudget > 0 ? round(($cat->allocated_amount / $totalBudget) * 100) : 0;
                                    @endphp
                                    <div class="legend-item">
                                        <span class="legend-dot" style="background:{{ $legendColors[$li % count($legendColors)] }};"></span>
                                        <span class="legend-label">{{ $cat->category_name }}</span>
                                        <span class="legend-value">{{ $pct }}%</span>
                                    </div>
                                    @php $li++; @endphp
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h2 class="card-title">Actual vs. Estimated</h2>
                                <p class="chart-subtitle">Comparison across vendor categories</p>
                            </div>
                            <div class="compare-legend">
                                <span><span class="legend-dot legend-dot--light"></span> Estimated</span>
                                <span><span class="legend-dot legend-dot--dark"></span> Actual</span>
                            </div>
                        </div>

                        @if($categories->isEmpty())
                            <div class="chart-empty">
                                <i class="fas fa-chart-bar fa-2x"></i>
                                <p>No vendor categories yet.</p>
                            </div>
                        @else
                            @php $maxCompare = $categories->reduce(fn($carry, $c) => max($carry, (float) $c->allocated_amount, $c->spent), 1) ?: 1; @endphp
                            <div class="compare-list">
                                @foreach($categories as $cat)
                                    @php
                                        $estPct = min(((float) $cat->allocated_amount / $maxCompare) * 100, 100);
                                        $actPct = min(($cat->spent / $maxCompare) * 100, 100);
                                        $overPct = $cat->allocated_amount > 0 ? round((($cat->spent - $cat->allocated_amount) / $cat->allocated_amount) * 100) : 0;
                                    @endphp
                                    <div class="compare-row">
                                        <div class="compare-row-top">
                                            <span class="compare-label">{{ $cat->category_name }}</span>
                                            <span class="compare-values">
                                                {{ $selectedEvent->currencySymbol() }}{{ number_format($cat->spent, 0) }}
                                                / {{ $selectedEvent->currencySymbol() }}{{ number_format($cat->allocated_amount, 0) }}
                                            </span>
                                        </div>
                                        <div class="compare-track">
                                            <div class="compare-bar-estimated" style="width: {{ $estPct }}%;"></div>
                                            <div class="compare-bar-actual {{ $cat->is_over_budget ? 'is-over' : '' }}" style="width: {{ $actPct }}%;"></div>
                                            @if($cat->is_over_budget)
                                                <span class="compare-over-tag">Over budget by {{ $overPct }}%</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Spending Trend + Alerts --}}
                <div class="budget-bottom-grid">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h2 class="card-title">Spending Trend</h2>
                                <p class="chart-subtitle">Daily expenditure over the selected period</p>
                            </div>
                            <form method="GET" action="{{ route('budget.index') }}" id="daysSwitchForm">
                                <input type="hidden" name="event" value="{{ $selectedEvent->id }}">
                                <select name="days" class="event-switch-select event-switch-select--sm" onchange="this.form.submit()">
                                    <option value="7" @selected($days == 7)>Last 7 Days</option>
                                    <option value="30" @selected($days == 30)>Last 30 Days</option>
                                    <option value="90" @selected($days == 90)>Last 90 Days</option>
                                </select>
                            </form>
                        </div>

                        @if(array_sum($trendData) == 0)
                            <div class="chart-empty">
                                <i class="fas fa-chart-line fa-2x"></i>
                                <p>No spending logged in this period.</p>
                            </div>
                        @else
                            <div class="trend-chart-wrap">
                                <canvas id="trendChart"></canvas>
                            </div>
                        @endif
                    </div>

                    <div class="card alerts-card">
                        <div class="card-header">
                            <h2 class="card-title"><i class="fas fa-triangle-exclamation" style="color:#ef4444;margin-right:6px;"></i> Budget Alerts</h2>
                        </div>

                        @if($alerts->isEmpty())
                            <div class="chart-empty">
                                <i class="fas fa-circle-check fa-2x" style="color:#10b981;"></i>
                                <p>All categories are within budget.</p>
                            </div>
                        @else
                            <div class="alerts-list">
                                @foreach($alerts as $alert)
                                    <div class="alert-item">
                                        <div class="alert-item-top">
                                            <strong>{{ $alert['title'] }}</strong>
                                            <span class="chip {{ $alert['level'] === 'Critical' ? 'chip-danger' : 'chip-warning' }}">{{ $alert['level'] }}</span>
                                        </div>
                                        <p>{{ $alert['message'] }}</p>
                                        @if($alert['level'] === 'Critical')
                                            <a href="{{ route('vendor-categories.index', ['event' => $selectedEvent->id]) }}" class="btn-review">Review Items</a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    </main>

    @if ($selectedEvent)
        {{-- Add Expense modal --}}
        <div class="modal-overlay" id="addExpenseModal">
            <div class="modal-dialog modal-dialog--sm">
                <div class="modal-header">
                    <h3 class="modal-title">Add Expense</h3>
                    <button type="button" class="modal-close" data-close-modal="addExpenseModal">&times;</button>
                </div>

                <form action="{{ route('expenses.store') }}" method="POST" class="modal-body">
                    @csrf
                    <input type="hidden" name="event_id" value="{{ $selectedEvent->id }}">

                    <div class="form-grid">
                        <div class="form-group form-group--full">
                            <label for="budget_vendor_item_name">Vendor / Item Name</label>
                            <input type="text" id="budget_vendor_item_name" name="vendor_item_name"
                                   placeholder="e.g. Grand Plaza Hotel — Banquet Hall" required>
                        </div>

                        <div class="form-group form-group--full">
                            <label for="budget_category_id">Category</label>
                            @if($categories->isEmpty())
                                <select id="budget_category_id" name="category_id" disabled>
                                    <option value="">No categories yet for this event</option>
                                </select>
                                <small style="color:var(--text-muted);font-size:12px;">
                                    <a href="{{ route('vendor-categories.index', ['event' => $selectedEvent->id]) }}">Add one from the Vendors page</a> first.
                                </small>
                            @else
                                <select id="budget_category_id" name="category_id" required>
                                    <option value="">— Select a category —</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->category_name }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="budget_estimated_cost">Estimated Cost ({{ $selectedEvent->currency }})</label>
                            <input type="number" step="0.01" min="0" id="budget_estimated_cost" name="estimated_cost" placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label for="budget_actual_cost">Actual Cost ({{ $selectedEvent->currency }})</label>
                            <input type="number" step="0.01" min="0" id="budget_actual_cost" name="actual_cost" placeholder="0.00" required>
                        </div>

                        <div class="form-group">
                            <label for="budget_payment_status">Payment Status</label>
                            <select id="budget_payment_status" name="payment_status" required>
                                <option value="Pending" selected>Pending</option>
                                <option value="Partially Paid">Partially Paid</option>
                                <option value="Paid">Paid</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="budget_date_logged">Date Logged</label>
                            <input type="date" id="budget_date_logged" name="date_logged" value="{{ date('Y-m-d') }}">
                        </div>

                        <div class="form-group form-group--full">
                            <label for="budget_notes">Notes (optional)</label>
                            <textarea id="budget_notes" name="notes" rows="2" placeholder="Extra details, invoice number, etc."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-close-modal="addExpenseModal">Cancel</button>
                        <button type="submit" class="btn btn-primary" {{ $categories->isEmpty() ? 'disabled' : '' }}>
                            <i class="fas fa-save"></i> Save Expense
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</body>
</html>
