<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses — Smart Event Planner</title>
    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/expanses.css') }}?v={{ time() }}">
    <script>
        window.expenseCategories = {!! json_encode($categories ?? []) !!};
        window.vendorCategoriesUrl = "{{ url('/events') }}";
        window.expensesStoreUrl = "{{ route('expenses.store') }}";
        window.expensesUpdateUrlBase = "{{ url('/expenses') }}";
        window.csrfToken = "{{ csrf_token() }}";
    </script>
</head>
<body>

    @include('partials.header')
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    @include('partials.sidebar')

    <main class="main-content">
        <div class="expenses-dashboard">

            {{-- Page Header --}}
            <div class="expenses-header">
                <div class="expenses-header-title">
                    <h1>Expense Management</h1>
                    <p>Track and manage your event financial ecosystem in real-time.</p>
                </div>
                <div class="header-actions">
                    <button class="btn-icon" title="Print" onclick="window.print()"><i class="fas fa-print"></i></button>
                    <button class="btn btn-primary" onclick="openExpenseModal()">
                        <i class="fas fa-plus"></i> <span class="btn-text">Add Expense</span>
                    </button>
                </div>
            </div>

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
            @endif

            {{-- Summary Cards --}}
            <div class="summary-cards">

                <div class="summary-card">
                    <div class="summary-title">Total Expenses</div>
                    <div class="summary-amount">PKR {{ number_format($totalExpenses, 2) }}</div>
                    <div class="summary-subtext {{ $vsLastMonth >= 0 ? '' : 'down' }}">
                        <i class="fas fa-arrow-{{ $vsLastMonth >= 0 ? 'up' : 'down' }}"></i>
                        {{ abs($vsLastMonth) }}% vs last month
                    </div>
                    <div class="summary-icon"><i class="fas fa-money-bill-wave fa-5x"></i></div>
                </div>

                <div class="summary-card pending">
                    <div class="summary-title">Pending Approval</div>
                    <div class="summary-amount">PKR {{ number_format($pendingApproval, 2) }}</div>
                    <div class="summary-subtext neutral">
                        <i class="fas fa-clock"></i>
                        {{ $pendingCount }} {{ Str::plural('item', $pendingCount) }} require review
                    </div>
                    <div class="summary-icon"><i class="fas fa-clipboard-list fa-5x"></i></div>
                </div>

                <div class="summary-card budget">
                    <div class="summary-title">Remaining Budget</div>
                    <div class="summary-amount">PKR {{ number_format($remainingBudget, 2) }}</div>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" style="width: {{ min($budgetPercentage, 100) }}%;"></div>
                    </div>
                    <div class="summary-subtext neutral" style="margin-top:6px;">
                        {{ round($budgetPercentage) }}% of total event budgets used
                    </div>
                    <div class="summary-icon"><i class="fas fa-university fa-5x"></i></div>
                </div>

            </div>

            {{-- Filters + Table --}}
            <div class="expenses-content">

                <form method="GET" action="{{ route('expenses.index') }}" id="filterForm">
                    <div class="filters-section">
                        <div class="filter-group">

                            {{-- Event Filter --}}
                            <select name="event" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                                <option value="">All Events</option>
                                @foreach($userEvents as $evt)
                                    <option value="{{ $evt->id }}" {{ request('event') == $evt->id ? 'selected' : '' }}>
                                        {{ $evt->event_name }}
                                    </option>
                                @endforeach
                            </select>

                            {{-- Category Filter --}}
                            <select name="category" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                                <option value="">All Categories</option>
                                @foreach($vendorCategories->unique('category_name') as $vc)
                                    <option value="{{ $vc->category_name }}" {{ request('category') == $vc->category_name ? 'selected' : '' }}>
                                        {{ $vc->category_name }}
                                    </option>
                                @endforeach
                            </select>

                            {{-- Status Filter --}}
                            <select name="status" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                                <option value="">All Statuses</option>
                                <option value="Paid"          {{ request('status') == 'Paid'           ? 'selected' : '' }}>Paid</option>
                                <option value="Pending"       {{ request('status') == 'Pending'        ? 'selected' : '' }}>Pending</option>
                                <option value="Partially Paid"{{ request('status') == 'Partially Paid' ? 'selected' : '' }}>Partially Paid</option>
                            </select>

                            {{-- Date Filter --}}
                            <input type="date" name="date" class="filter-select"
                                   value="{{ request('date') }}"
                                   onchange="document.getElementById('filterForm').submit()">
                        </div>

                        @if(request()->hasAny(['category','status','date','event']))
                            <a href="{{ route('expenses.index') }}" class="clear-filters">
                                <i class="fas fa-times-circle"></i> Clear All Filters
                            </a>
                        @else
                            <span class="clear-filters is-disabled">
                                <i class="fas fa-filter"></i> Clear All Filters
                            </span>
                        @endif
                    </div>
                </form>

                {{-- Table --}}
                <div class="table-responsive">
                    <table class="expenses-table">
                        <thead>
                            <tr>
                                <th>Vendor / Item</th>
                                <th>Event</th>
                                <th>Category</th>
                                <th>Estimated</th>
                                <th>Actual Cost</th>
                                <th>Status</th>
                                <th>Date Logged</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expenses as $expense)
                            @php
                                $catName = $expense->category->category_name ?? '—';
                                $cat     = strtolower($catName);
                                $iconClass  = 'fa-building'; $iconBg = '#e0e7ff'; $iconColor = '#4f46e5';
                                if(str_contains($cat,'catering'))  { $iconClass='fa-utensils';  $iconBg='#fef9c3'; $iconColor='#ca8a04'; }
                                if(str_contains($cat,'decor'))     { $iconClass='fa-star';      $iconBg='#fce7f3'; $iconColor='#db2777'; }
                                if(str_contains($cat,'marketing')) { $iconClass='fa-bullhorn';  $iconBg='#ede9fe'; $iconColor='#7c3aed'; }
                                if(str_contains($cat,'production')){ $iconClass='fa-video';     $iconBg='#ccfbf1'; $iconColor='#0d9488'; }
                                if(str_contains($cat,'travel'))    { $iconClass='fa-plane';     $iconBg='#fee2e2'; $iconColor='#ef4444'; }
                                if(str_contains($cat,'venue'))     { $iconClass='fa-map-marker-alt'; $iconBg='#dbeafe'; $iconColor='#2563eb'; }
                                $statusClass = strtolower(str_replace(' ','-',$expense->payment_status));
                                $expenseJson = [
                                    'id' => $expense->id,
                                    'event_id' => $expense->event_id,
                                    'category_id' => $expense->category_id,
                                    'vendor_item_name' => $expense->vendor_item_name,
                                    'estimated_cost' => $expense->estimated_cost,
                                    'actual_cost' => $expense->actual_cost,
                                    'payment_status' => $expense->payment_status,
                                    'date_logged' => optional($expense->date_logged)->format('Y-m-d'),
                                    'notes' => $expense->notes,
                                ];
                            @endphp
                            <tr>
                                <td>
                                    <div class="vendor-cell">
                                        <div class="vendor-icon" style="background:{{ $iconBg }};color:{{ $iconColor }};">
                                            <i class="fas {{ $iconClass }}"></i>
                                        </div>
                                        <div class="vendor-info">
                                            <h4>{{ $expense->vendor_item_name }}</h4>
                                            @if($expense->notes)
                                                <p>{{ Str::limit($expense->notes, 40) }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Event" class="text-muted">
                                    {{ $expense->event->event_name ?? '—' }}
                                </td>
                                <td data-label="Category">
                                    <span class="badge badge-category">{{ $catName }}</span>
                                </td>
                                <td class="text-muted" data-label="Estimated">
                                    PKR {{ number_format($expense->estimated_cost, 2) }}
                                </td>
                                <td class="actual-cost" data-label="Actual">
                                    PKR {{ number_format($expense->actual_cost, 2) }}
                                    @if($expense->actual_cost > $expense->estimated_cost)
                                        <span class="over-budget-tag">Over</span>
                                    @endif
                                </td>
                                <td data-label="Status">
                                    <div class="status-indicator status-{{ $statusClass }}">
                                        <span class="status-dot"></span>
                                        {{ $expense->payment_status }}
                                    </div>
                                </td>
                                <td class="text-muted" data-label="Date">
                                    {{ $expense->date_logged ? $expense->date_logged->format('M d, Y') : '—' }}
                                </td>
                                <td class="row-actions-cell">
                                    <div class="row-menu">
                                        <button type="button" class="kebab-btn" onclick="toggleRowMenu(event, {{ $expense->id }})" title="More actions">
                                            <i class="fas fa-ellipsis-vertical"></i>
                                        </button>
                                        <div class="row-dropdown" id="row-dropdown-{{ $expense->id }}">
                                            <button type="button" class="row-dropdown-item"
                                                    data-expense='@json($expenseJson)'
                                                    onclick="editExpense(this)">
                                                <i class="fas fa-pen"></i> Edit
                                            </button>
                                            <button type="button" class="row-dropdown-item danger" onclick="deleteExpense({{ $expense->id }})">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                    <form id="delete-form-{{ $expense->id }}"
                                          action="{{ route('expenses.destroy', $expense) }}"
                                          method="POST" style="display:none;">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-receipt fa-2x"></i>
                                        <p>No expenses found{{ request()->hasAny(['category','status','date','event']) ? ' for the selected filters' : '' }}.</p>
                                        @if(!request()->hasAny(['category','status','date','event']))
                                            <button class="btn btn-primary" onclick="openExpenseModal()">Add your first expense</button>
                                        @else
                                            <a href="{{ route('expenses.index') }}" class="btn btn-secondary">Clear Filters</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($expenses->total() > 0)
                <div class="table-footer">
                    <span class="table-info">
                        Showing {{ $expenses->firstItem() }}–{{ $expenses->lastItem() }} of {{ $expenses->total() }} entries
                    </span>
                    <div class="pagination-wrap">
                        {{ $expenses->appends(request()->query())->links('pagination::bootstrap-4') }}
                    </div>
                </div>
                @endif
            </div>

            {{-- Charts --}}
            <div class="charts-grid">

                {{-- Doughnut --}}
                <div class="chart-card">
                    <div class="chart-title">Spending by Category</div>
                    @if(empty($categories))
                        <div class="empty-chart">
                            <i class="fas fa-chart-pie fa-2x"></i>
                            <p>No expense data yet.</p>
                        </div>
                    @else
                        <div class="chart-canvas-wrap">
                            <canvas id="categoryChart"></canvas>
                        </div>
                        <div class="chart-legend">
                            @php $chartColors = ['#4f46e5','#8b5cf6','#0d9488','#ef4444','#f59e0b','#06b6d4']; $ci=0; @endphp
                            @foreach($categories as $catName => $catTotal)
                                <div class="legend-item">
                                    <span class="legend-dot" style="background:{{ $chartColors[$ci % count($chartColors)] }};"></span>
                                    <span class="legend-label">{{ $catName }}</span>
                                    <span class="legend-value">PKR {{ number_format($catTotal,0) }}</span>
                                </div>
                                @php $ci++; @endphp
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Budget Forecast Bar --}}
                <div class="chart-card">
                    <div class="chart-title">
                        Budget Forecast
                        @php
                            $actualTotal = 0;
                            foreach ($monthlyData as $idx => $v) { if (!($monthProjected[$idx] ?? false)) $actualTotal += $v; }
                            $isSafe = ($totalBudget ?? 0) <= 0 || $actualTotal <= ($totalBudget ?? 0);
                        @endphp
                        <span class="safe-badge {{ $isSafe ? 'safe' : 'over' }}">
                            {{ $isSafe ? 'SAFE' : 'OVER' }}
                        </span>
                    </div>
                    <p class="chart-subtitle">Projected vs actual spend timeline</p>

                    @if(array_sum($monthlyData ?? []) == 0)
                        <div class="empty-chart">
                            <i class="fas fa-chart-bar fa-2x"></i>
                            <p>No spending data yet.</p>
                        </div>
                    @else
                        @php
                            $maxSpend = max($monthlyData) ?: 1;
                            $currentIndex = 0;
                            foreach ($monthProjected as $idx => $p) { if (!$p) $currentIndex = $idx; }
                        @endphp
                        <div class="bar-chart-wrap">
                            @foreach($monthlyData as $index => $spend)
                                @php
                                    $pct = max(($spend / $maxSpend) * 100, 4);
                                    $projected = $monthProjected[$index] ?? false;
                                @endphp
                                <div class="bar-col {{ $projected ? 'projected' : '' }}">
                                    <div class="bar-value">
                                        @if($spend > 0)<small>{{ number_format($spend/1000,0) }}k</small>@endif
                                    </div>
                                    <div class="bar-outer">
                                        <div class="bar-inner {{ $projected ? 'bar-projected' : 'bar-color-'.$index }} {{ $index === $currentIndex ? 'bar-current' : '' }}"
                                             style="height:{{ $pct }}%;"
                                             title="{{ $projected ? 'Projected' : 'Actual' }}: PKR {{ number_format($spend, 0) }}"></div>
                                    </div>
                                    <div class="bar-month {{ $index === $currentIndex ? 'active-month' : '' }}">
                                        {{ $months[$index] }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>

        </div>
    </main>

    {{-- Add / Edit Expense Modal --}}
    <div id="expenseModal" class="modal" onclick="handleModalClick(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2 id="modalTitle"><i class="fas fa-plus-circle" style="color:#4f46e5;margin-right:8px;"></i>Add New Expense</h2>
                <button class="action-btn close-btn" onclick="closeExpenseModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="expenseForm" action="{{ route('expenses.store') }}" method="POST">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="">

                {{-- Step 1: Pick Event --}}
                <div class="form-group">
                    <label>Event <span class="required">*</span></label>
                    <select name="event_id" id="modal_event_id" class="form-control" required onchange="loadCategories(this.value)">
                        <option value="">— Select an event —</option>
                        @foreach($userEvents as $evt)
                            <option value="{{ $evt->id }}">{{ $evt->event_name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Step 2: Pick Category (loaded dynamically) --}}
                <div class="form-group">
                    <label>Category <span class="required">*</span></label>
                    <select name="category_id" id="modal_category_id" class="form-control" required disabled>
                        <option value="">— Select event first —</option>
                    </select>
                    <small id="cat-hint" style="color:#6b7280;font-size:12px;display:none;">
                        No categories yet for this event.
                        <a href="{{ route('events.index') }}">Add one from the Events page.</a>
                    </small>
                </div>

                {{-- Vendor / Item Name --}}
                <div class="form-group">
                    <label>Vendor / Item Name <span class="required">*</span></label>
                    <input type="text" name="vendor_item_name" id="modal_vendor_item_name" class="form-control"
                           placeholder="e.g. Grand Plaza Hotel — Banquet Hall" required>
                </div>

                {{-- Costs --}}
                <div class="form-row">
                    <div class="form-group">
                        <label>Estimated Cost (PKR) <span class="required">*</span></label>
                        <input type="number" step="0.01" min="0" name="estimated_cost" id="modal_estimated_cost"
                               class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label>Actual Cost (PKR) <span class="required">*</span></label>
                        <input type="number" step="0.01" min="0" name="actual_cost" id="modal_actual_cost"
                               class="form-control" placeholder="0.00" required>
                    </div>
                </div>

                {{-- Status + Date --}}
                <div class="form-row">
                    <div class="form-group">
                        <label>Payment Status <span class="required">*</span></label>
                        <select name="payment_status" id="modal_payment_status" class="form-control" required>
                            <option value="Pending" selected>Pending</option>
                            <option value="Partially Paid">Partially Paid</option>
                            <option value="Paid">Paid</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date Logged</label>
                        <input type="date" name="date_logged" id="modal_date_logged" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>
                </div>

                {{-- Notes --}}
                <div class="form-group">
                    <label>Notes (optional)</label>
                    <textarea name="notes" id="modal_notes" class="form-control" rows="2"
                              placeholder="Extra details, invoice number, etc."></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeExpenseModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="modalSubmitBtn">
                        <i class="fas fa-save"></i> <span>Save Expense</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="{{ asset('js/expanses.js') }}?v={{ time() }}"></script>

</body>
</html>
