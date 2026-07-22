<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Expense Report</title>
    <style>
        @page { margin: 32px 36px; }

        body {
            font-family: 'Helvetica', Arial, sans-serif;
            color: #1f2937;
            font-size: 12px;
            line-height: 1.5;
        }

        .brand {
            font-size: 11px;
            font-weight: bold;
            color: #4f46e5;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .report-title {
            font-size: 24px;
            font-weight: bold;
            color: #111827;
            margin: 0 0 4px;
        }

        .report-meta {
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 6px;
        }

        .filters-line {
            font-size: 10px;
            color: #6b7280;
            margin-bottom: 18px;
        }

        .filters-line strong {
            color: #374151;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
            margin: 22px 0 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e5e7eb;
        }

        .currency-tag {
            display: inline-block;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4f46e5;
            background-color: #eef2ff;
            padding: 2px 8px;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        table.kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        table.kpi-table td {
            width: 25%;
            padding: 8px 10px;
            background-color: #f9fafb;
            border: 4px solid #ffffff;
        }

        .kpi-label {
            display: block;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #6b7280;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .kpi-value {
            display: block;
            font-size: 14px;
            font-weight: bold;
            color: #111827;
        }

        .kpi-negative .kpi-value { color: #b91c1c; }

        table.detail-table, table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        table.detail-table th, table.items-table th {
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 1px solid #d1d5db;
            padding: 6px 8px;
        }

        table.detail-table td, table.items-table td {
            font-size: 10.5px;
            padding: 6px 8px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }

        .sublabel {
            font-size: 9px;
            color: #6b7280;
        }

        .status-tag {
            display: inline-block;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 2px 7px;
            border-radius: 8px;
        }

        .status-on-track  { background: #d1fae5; color: #065f46; }
        .status-watch     { background: #fef3c7; color: #92400e; }
        .status-at-risk   { background: #ffedd5; color: #9a3412; }
        .status-over      { background: #fee2e2; color: #991b1b; }
        .status-no-budget { background: #e5e7eb; color: #4b5563; }

        .status-paid            { color: #059669; font-weight: bold; }
        .status-pending         { color: #b45309; font-weight: bold; }
        .status-partially-paid  { color: #1d4ed8; font-weight: bold; }

        .negative { color: #b91c1c; font-weight: bold; }
        .over-tag { color: #b91c1c; font-size: 9px; font-weight: bold; }

        .empty-note { color: #9ca3af; font-style: italic; padding: 8px 0; }

        .subsection-title {
            font-size: 11px;
            font-weight: bold;
            color: #111827;
            margin: 14px 0 6px;
        }

        .alerts-box {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 10px;
        }

        .alerts-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #991b1b;
            margin-bottom: 3px;
        }

        .alert-item {
            font-size: 10px;
            color: #7f1d1d;
        }

        .footer-note {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            font-size: 10px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="brand">EventPro — Smart Event Planner</div>
    <h1 class="report-title">Expense Report</h1>
    @php
        $periodLabels = ['all' => 'All Time (Till Date)', 'month' => 'This Month', 'week' => 'This Week'];
    @endphp
    <div class="report-meta">
        Generated {{ now()->format('M d, Y') }}
        &nbsp;|&nbsp;
        Scope: {{ $scopeEvent ? $scopeEvent->event_name : 'All Events Combined' }}
        &nbsp;|&nbsp;
        Period: {{ $periodLabels[$reportPeriod] ?? $periodLabels['all'] }}
    </div>

    @if (! empty($appliedFilters))
        <div class="filters-line">
            Filters applied:
            @foreach ($appliedFilters as $label => $value)
                <strong>{{ $label }}:</strong> {{ $value }}@if (! $loop->last) &nbsp;|&nbsp; @endif
            @endforeach
        </div>
    @endif

    <div class="section-title">Budget &amp; Spend Summary</div>

    @forelse ($reportGroups as $group)
        @if (count($reportGroups) > 1)
            <div class="currency-tag">{{ $group->currency }}</div>
        @endif

        @if ($group->alerts->isNotEmpty())
            <div class="alerts-box">
                <div class="alerts-title">Over Budget Alerts</div>
                @foreach ($group->alerts as $alert)
                    <div class="alert-item">
                        {{ $alert->label }}
                        {{ $alert->status === 'over' ? 'is over budget by' : 'is projected to go over budget by' }}
                        {{ $group->symbol }}{{ number_format($alert->overBy, 0) }}
                    </div>
                @endforeach
            </div>
        @endif

        <table class="kpi-table">
            <tr>
                <td>
                    <span class="kpi-label">Total Budget</span>
                    <span class="kpi-value">{{ $group->symbol }}{{ number_format($group->budget, 0) }}</span>
                </td>
                <td>
                    <span class="kpi-label">Spent ({{ $periodLabels[$reportPeriod] ?? $periodLabels['all'] }}, {{ number_format($group->utilization, 0) }}%)</span>
                    <span class="kpi-value">{{ $group->symbol }}{{ number_format($group->spent, 0) }}</span>
                </td>
                <td>
                    <span class="kpi-label">Expected / Remaining Expenses</span>
                    <span class="kpi-value">{{ $group->symbol }}{{ number_format($group->expected, 0) }}</span>
                </td>
                <td class="{{ $group->remaining < 0 ? 'kpi-negative' : '' }}">
                    <span class="kpi-label">{{ $group->remaining < 0 ? 'Projected Overrun' : 'Remaining Budget' }}</span>
                    <span class="kpi-value">{{ $group->remaining < 0 ? '-' : '' }}{{ $group->symbol }}{{ number_format(abs($group->remaining), 0) }}</span>
                </td>
            </tr>
        </table>

        @if ($group->rows->isNotEmpty())
            <table class="detail-table">
                <tr>
                    <th style="width: 28%;">{{ $group->detailLabel }}</th>
                    <th style="width: 16%;">Budget</th>
                    <th style="width: 16%;">Spent</th>
                    <th style="width: 16%;">Expected</th>
                    <th style="width: 14%;">Remaining</th>
                    <th style="width: 10%;">Status</th>
                </tr>
                @foreach ($group->rows as $row)
                    <tr>
                        <td>
                            {{ $row->label }}
                            @if ($row->sublabel)
                                <div class="sublabel">{{ $row->sublabel }}</div>
                            @endif
                        </td>
                        <td>{{ $group->symbol }}{{ number_format($row->budget, 0) }}</td>
                        <td>{{ $group->symbol }}{{ number_format($row->spent, 0) }}</td>
                        <td>{{ $group->symbol }}{{ number_format($row->expected, 0) }}</td>
                        <td class="{{ $row->remaining < 0 ? 'negative' : '' }}">
                            {{ $row->remaining < 0 ? '-' : '' }}{{ $group->symbol }}{{ number_format(abs($row->remaining), 0) }}
                        </td>
                        <td>
                            <span class="status-tag status-{{ $row->status }}">{{ ucwords(str_replace('-', ' ', $row->status)) }}</span>
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif

        @if ($scopeEvent === null && $group->categoryRows->isNotEmpty())
            <div class="subsection-title">Expense Breakdown by Category</div>
            <table class="detail-table">
                <tr>
                    <th style="width: 28%;">Category</th>
                    <th style="width: 16%;">Budget</th>
                    <th style="width: 16%;">Spent</th>
                    <th style="width: 16%;">Expected</th>
                    <th style="width: 14%;">Remaining</th>
                    <th style="width: 10%;">Status</th>
                </tr>
                @foreach ($group->categoryRows as $row)
                    <tr>
                        <td>{{ $row->label }}</td>
                        <td>{{ $group->symbol }}{{ number_format($row->budget, 0) }}</td>
                        <td>{{ $group->symbol }}{{ number_format($row->spent, 0) }}</td>
                        <td>{{ $group->symbol }}{{ number_format($row->expected, 0) }}</td>
                        <td class="{{ $row->remaining < 0 ? 'negative' : '' }}">
                            {{ $row->remaining < 0 ? '-' : '' }}{{ $group->symbol }}{{ number_format(abs($row->remaining), 0) }}
                        </td>
                        <td>
                            <span class="status-tag status-{{ $row->status }}">{{ ucwords(str_replace('-', ' ', $row->status)) }}</span>
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif
    @empty
        <p class="empty-note">No events to report on.</p>
    @endforelse

    <div class="section-title">{{ $scopeEvent ? 'Detailed Expense List' : 'Expense Line Items' }}</div>

    @php
        // Single-event scope: show that event's own period-scoped item list
        // (matches the on-screen report exactly). All-events scope: show
        // whatever the page's own filters (event/category/status/date) matched.
        $itemRows = $scopeEvent ? ($reportGroups[0]->expenseItems ?? collect()) : $expenses;
    @endphp

    @if ($itemRows->isEmpty())
        <p class="empty-note">No expenses match the current filters.</p>
    @else
        <table class="items-table">
            <tr>
                <th style="width: 20%;">Vendor / Item</th>
                @unless ($scopeEvent)
                    <th style="width: 14%;">Event</th>
                @endunless
                <th style="width: 14%;">Category</th>
                <th style="width: 12%;">Estimated</th>
                <th style="width: 12%;">Actual</th>
                <th style="width: 12%;">Status</th>
                <th style="width: 12%;">Date Logged</th>
            </tr>
            @foreach ($itemRows as $expense)
                <tr>
                    <td>{{ $expense->vendor_item_name }}</td>
                    @unless ($scopeEvent)
                        <td>{{ $expense->event->event_name ?? '—' }}</td>
                    @endunless
                    <td>{{ $expense->category->category_name ?? '—' }}</td>
                    <td>{{ number_format($expense->estimated_cost, 2) }}</td>
                    <td>
                        {{ number_format($expense->actual_cost, 2) }}
                        @if ($expense->actual_cost > $expense->estimated_cost)
                            <span class="over-tag">Over</span>
                        @endif
                    </td>
                    <td class="status-{{ strtolower(str_replace(' ', '-', $expense->payment_status)) }}">{{ $expense->payment_status }}</td>
                    <td>{{ $expense->date_logged ? $expense->date_logged->format('M d, Y') : '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="footer-note">
        Generated by EventPro Smart Event Planner &amp; Budget Tracker.
    </div>
</body>
</html>
