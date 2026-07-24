<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organization Dashboard — Smart Event Planner</title>
    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/expanses.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/organization.css') }}?v={{ time() }}">
    @if(!empty($reportGroups))
        <script>
            window.orgGroups = {!! json_encode(collect($reportGroups)->values()->map(function ($g, $i) {
                return [
                    'index' => $i,
                    'eventLabels' => $g->rows->pluck('label'),
                    'eventBudget' => $g->rows->pluck('budget'),
                    'eventSpent' => $g->rows->pluck('spent'),
                    'eventForecast' => $g->rows->pluck('forecast'),
                    'remainingConsumed' => max($g->spent + $g->expected, 0),
                    'remainingLeft' => max($g->remaining, 0),
                    'vendorLabels' => $g->topVendors->pluck('vendor'),
                    'vendorPaid' => $g->topVendors->pluck('totalPaid'),
                    'cashFlowLabels' => $g->cashFlowLabels,
                    'cashFlowActual' => $g->cashFlowActual,
                    'cashFlowBudget' => $g->cashFlowBudgetLine,
                ];
            })) !!};
        </script>
    @endif
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="{{ asset('js/organization-dashboard.js') }}?v={{ time() }}" defer></script>
</head>
<body>

    @include('partials.header')
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    @include('partials.sidebar')

    <main class="main-content">
        <div class="dashboard-body">

            <div class="page-header">
                <div>
                    <h1 class="page-title">Organization Dashboard</h1>
                    <p class="page-subtitle">Every event you run, combined into one executive financial view.</p>
                </div>
                @if($userEvents->isNotEmpty())
                    <form method="GET" action="{{ route('organization.index') }}" class="page-header-actions">
                        <select name="period" class="filter-select" onchange="this.form.submit()">
                            <option value="all" @selected($period === 'all')>All Time (Till Date)</option>
                            <option value="month" @selected($period === 'month')>This Month</option>
                            <option value="week" @selected($period === 'week')>This Week</option>
                        </select>
                    </form>
                @endif
            </div>

            @if($userEvents->isEmpty())
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-building"></i></div>
                    <h3>No Events Yet</h3>
                    <p>Create your first event, then come back here to see your organization-wide financial picture.</p>
                    <a href="{{ route('events.index') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create an Event
                    </a>
                </div>
            @else
                @foreach($reportGroups as $i => $group)
                    <div class="org-currency-block">
                        @if(count($reportGroups) > 1)
                            <div class="report-currency-tag">{{ $group->currency }}</div>
                        @endif

                        {{-- Financial Alerts --}}
                        @if($group->alerts->isNotEmpty())
                            <div class="report-alerts">
                                <div class="report-alerts-title"><i class="fas fa-triangle-exclamation"></i> Budget Health Alerts</div>
                                @foreach($group->alerts as $alert)
                                    <div class="report-alert-item">
                                        <strong>{{ $alert->label }}</strong>
                                        {{ $alert->status === 'over' ? 'is over budget by' : 'is projected to go over budget by' }}
                                        {{ $group->symbol }}{{ number_format($alert->overBy, 0) }}
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- KPI Cards --}}
                        <div class="stats-grid org-stats-grid">
                            <div class="stat-card">
                                <div class="stat-title">Total Events</div>
                                <div class="stat-value">{{ $group->eventCount }}</div>
                                <div class="stat-icon"><i class="fas fa-calendar-days"></i></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-title">Total Budget</div>
                                <div class="stat-value" style="font-size:26px;">{{ $group->symbol }}{{ number_format($group->budget, 0) }}</div>
                                <div class="stat-icon"><i class="fas fa-sack-dollar"></i></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-title">Actual Spending</div>
                                <div class="stat-value" style="font-size:26px;">{{ $group->symbol }}{{ number_format($group->spent, 0) }}</div>
                                <div class="stat-desc">{{ number_format($group->utilization, 0) }}% of budget</div>
                                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-title">Expected Spending</div>
                                <div class="stat-value" style="font-size:26px;">{{ $group->symbol }}{{ number_format($group->expected, 0) }}</div>
                                <div class="stat-desc">Outstanding on logged items</div>
                                <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-title">Forecast Total Cost</div>
                                <div class="stat-value" style="font-size:26px;">{{ $group->symbol }}{{ number_format($group->projectedTotal, 0) }}</div>
                                <div class="stat-desc">{{ number_format($group->projectedUtilization, 0) }}% of budget</div>
                                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-title">{{ $group->remaining < 0 ? 'Projected Overrun' : 'Remaining Budget' }}</div>
                                <div class="stat-value {{ $group->remaining < 0 ? 'is-danger' : '' }}" style="font-size:26px;">
                                    {{ $group->remaining < 0 ? '-' : '' }}{{ $group->symbol }}{{ number_format(abs($group->remaining), 0) }}
                                </div>
                                <div class="stat-icon"><i class="fas fa-university"></i></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-title">Budget Utilization</div>
                                <div class="stat-value" style="font-size:26px;">{{ number_format($group->utilization, 0) }}%</div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: {{ min($group->utilization, 100) }}%;"></div>
                                </div>
                                <div class="stat-icon"><i class="fas fa-gauge-high"></i></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-title">Paid Expenses</div>
                                <div class="stat-value" style="font-size:26px;">{{ $group->symbol }}{{ number_format($group->paidSum, 0) }}</div>
                                <div class="stat-desc">{{ $group->paidCount }} {{ Str::plural('item', $group->paidCount) }}</div>
                                <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-title">Pending Expenses</div>
                                <div class="stat-value" style="font-size:26px;">{{ $group->symbol }}{{ number_format($group->pendingSum, 0) }}</div>
                                <div class="stat-desc">{{ $group->pendingCount }} pending, {{ $group->partiallyPaidCount }} partial</div>
                                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-title">Over-Budget Events</div>
                                <div class="stat-value {{ $group->overBudgetEventCount > 0 ? 'is-danger' : '' }}">{{ $group->overBudgetEventCount }}</div>
                                <div class="stat-desc">of {{ $group->eventCount }} total</div>
                                <div class="stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
                            </div>
                        </div>

                        {{-- Charts --}}
                        <div class="org-charts-row">
                            <div class="chart-card org-chart-wide">
                                <div class="chart-card-title">Budget vs Actual vs Forecast — per Event</div>
                                <div class="chart-card-body">
                                    <canvas id="orgBarChart-{{ $i }}"></canvas>
                                </div>
                            </div>
                            <div class="chart-card">
                                <div class="chart-card-title">Remaining Budget</div>
                                <div class="chart-card-body">
                                    <canvas id="orgDoughnut-{{ $i }}"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="org-charts-row">
                            <div class="chart-card">
                                <div class="chart-card-title">Top Vendors by Amount Paid</div>
                                <div class="chart-card-body">
                                    @if($group->topVendors->isNotEmpty())
                                        <canvas id="orgVendorChart-{{ $i }}"></canvas>
                                    @else
                                        <p class="report-empty-note">No vendor names recorded on categories yet.</p>
                                    @endif
                                </div>
                            </div>
                            <div class="chart-card org-chart-wide">
                                <div class="chart-card-title">Cash Flow — Last 6 Months</div>
                                <div class="chart-card-body">
                                    <canvas id="orgCashflow-{{ $i }}"></canvas>
                                </div>
                            </div>
                        </div>

                        {{-- Event Financial Comparison --}}
                        <h3 class="report-subsection-title">Event Financial Comparison</h3>
                        @if($group->rows->isNotEmpty())
                            <div class="table-responsive">
                                <table class="expenses-table report-detail-table org-sortable-table">
                                    <thead>
                                        <tr>
                                            <th data-sort data-type="text">Event</th>
                                            <th data-sort data-type="number">Budget</th>
                                            <th data-sort data-type="number">Actual Spent</th>
                                            <th data-sort data-type="number">Expected Cost</th>
                                            <th data-sort data-type="number">Forecast Cost</th>
                                            <th data-sort data-type="number">Remaining Budget</th>
                                            <th data-sort data-type="number">Utilization %</th>
                                            <th data-sort data-type="text">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($group->rows as $row)
                                            <tr>
                                                <td data-value="{{ $row->label }}"><strong>{{ $row->label }}</strong></td>
                                                <td data-value="{{ $row->budget }}">{{ $group->symbol }}{{ number_format($row->budget, 0) }}</td>
                                                <td data-value="{{ $row->spent }}">{{ $group->symbol }}{{ number_format($row->spent, 0) }}</td>
                                                <td data-value="{{ $row->expected }}">{{ $group->symbol }}{{ number_format($row->expected, 0) }}</td>
                                                <td data-value="{{ $row->forecast }}">{{ $group->symbol }}{{ number_format($row->forecast, 0) }}</td>
                                                <td data-value="{{ $row->remaining }}" class="{{ $row->remaining < 0 ? 'report-negative-cell' : '' }}">
                                                    {{ $row->remaining < 0 ? '-' : '' }}{{ $group->symbol }}{{ number_format(abs($row->remaining), 0) }}
                                                </td>
                                                <td data-value="{{ $row->utilization }}">{{ number_format($row->utilization, 0) }}%</td>
                                                <td data-value="{{ $row->status }}">
                                                    <span class="report-status-badge report-status-{{ $row->status }}">{{ ucwords(str_replace('-', ' ', $row->status)) }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="report-empty-note">No events to compare yet.</p>
                        @endif

                        {{-- Spending by Category --}}
                        <h3 class="report-subsection-title">Spending by Category</h3>
                        @if($group->categoryRows->isNotEmpty())
                            <div class="table-responsive">
                                <table class="expenses-table report-detail-table">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Allocated Budget</th>
                                            <th>Actual Spending</th>
                                            <th>Expected Spending</th>
                                            <th>Remaining Budget</th>
                                            <th>Utilization %</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($group->categoryRows as $row)
                                            <tr>
                                                <td><strong>{{ $row->label }}</strong></td>
                                                <td>{{ $group->symbol }}{{ number_format($row->budget, 0) }}</td>
                                                <td>{{ $group->symbol }}{{ number_format($row->spent, 0) }}</td>
                                                <td>{{ $group->symbol }}{{ number_format($row->expected, 0) }}</td>
                                                <td class="{{ $row->remaining < 0 ? 'report-negative-cell' : '' }}">
                                                    {{ $row->remaining < 0 ? '-' : '' }}{{ $group->symbol }}{{ number_format(abs($row->remaining), 0) }}
                                                </td>
                                                <td>{{ number_format($row->utilization, 0) }}%</td>
                                                <td>
                                                    <span class="report-status-badge report-status-{{ $row->status }}">{{ ucwords(str_replace('-', ' ', $row->status)) }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="report-empty-note">No category data logged yet.</p>
                        @endif

                        {{-- Vendor Spending Report --}}
                        <h3 class="report-subsection-title">
                            Vendor Spending Report
                            <span class="report-subsection-note">(grouped by vendor name on categories — add a vendor to a category to see it here)</span>
                        </h3>
                        @if($group->topVendors->isNotEmpty())
                            <div class="table-responsive">
                                <table class="expenses-table report-detail-table">
                                    <thead>
                                        <tr>
                                            <th>Vendor</th>
                                            <th>Total Paid</th>
                                            <th>Pending Amount</th>
                                            <th>Categories / Contracts</th>
                                            <th>Outstanding Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($group->topVendors as $vendor)
                                            <tr>
                                                <td><strong>{{ $vendor->vendor }}</strong></td>
                                                <td>{{ $group->symbol }}{{ number_format($vendor->totalPaid, 0) }}</td>
                                                <td>{{ $group->symbol }}{{ number_format($vendor->pendingAmount, 0) }}</td>
                                                <td>{{ $vendor->contracts }}</td>
                                                <td>{{ $group->symbol }}{{ number_format($vendor->outstandingBalance, 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="report-empty-note">No vendor names recorded on categories yet.</p>
                        @endif

                        {{-- Outstanding Payments --}}
                        <h3 class="report-subsection-title">
                            Outstanding Payments
                            <span class="report-subsection-note">(bucketed by when logged — there's no due-date field yet)</span>
                        </h3>
                        <div class="org-outstanding-grid">
                            @foreach([
                                'Today' => $group->outstandingToday,
                                'This Week' => $group->outstandingThisWeek,
                                'This Month' => $group->outstandingThisMonth,
                            ] as $bucketLabel => $items)
                                <div class="org-outstanding-card">
                                    <div class="org-outstanding-card-title">{{ $bucketLabel }}</div>
                                    @forelse($items as $item)
                                        <div class="org-outstanding-item">
                                            <div>
                                                <strong>{{ $item->category->vendor_name ?? $item->vendor_item_name }}</strong>
                                                <div class="org-outstanding-sub">{{ $item->event->event_name ?? '' }} &middot; {{ $item->category->category_name ?? '—' }}</div>
                                            </div>
                                            <div class="org-outstanding-amount">
                                                {{ $group->symbol }}{{ number_format($item->estimated_cost - $item->actual_cost, 0) }}
                                                <div class="status-indicator status-{{ strtolower(str_replace(' ', '-', $item->payment_status)) }}">
                                                    <span class="status-dot"></span>{{ $item->payment_status }}
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="report-empty-note">Nothing outstanding.</p>
                                    @endforelse
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif

        </div>
    </main>

</body>
</html>
