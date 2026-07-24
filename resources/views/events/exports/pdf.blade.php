<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $event->event_name }} — Event Report</title>
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
            color: #5c3ce6;
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
            margin-bottom: 18px;
        }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #ffffff;
            background-color: #5c3ce6;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
            margin: 22px 0 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e5e7eb;
        }

        table.summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.summary-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }

        table.summary-table td.label {
            width: 32%;
            color: #6b7280;
            font-weight: bold;
        }

        .budget-callout {
            margin-top: 6px;
            padding: 10px 14px;
            background-color: #f0edff;
            border-radius: 6px;
            font-size: 11px;
            color: #4b2ccf;
        }

        .description-text {
            color: #374151;
            white-space: pre-wrap;
        }

        .phase-heading {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #5c3ce6;
            background-color: #f0edff;
            padding: 6px 10px;
            margin-top: 16px;
        }

        table.task-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        table.task-table th {
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 1px solid #d1d5db;
            padding: 6px 8px;
        }

        table.task-table td {
            font-size: 11px;
            padding: 7px 8px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }

        .priority-high { color: #b91c1c; font-weight: bold; }
        .priority-medium { color: #92400e; font-weight: bold; }
        .priority-low { color: #374151; }

        .status-completed { color: #059669; font-weight: bold; }
        .status-pending { color: #7c3aed; font-weight: bold; }
        .status-skipped { color: #6b7280; }

        .task-name { font-weight: bold; color: #111827; }
        .task-notes { color: #6b7280; font-size: 10px; }

        .empty-note { color: #9ca3af; font-style: italic; padding: 8px; }

        .footer-note {
            margin-top: 28px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            font-size: 10px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="brand">EventPro — Smart Event Planner</div>
    <h1 class="report-title">{{ $event->event_name }}</h1>
    <div class="report-meta">
        Event Report generated {{ now()->format('M d, Y') }}
        &nbsp;|&nbsp;
        <span class="badge">{{ $event->status }}</span>
    </div>

    <div class="section-title">Event Summary</div>
    <table class="summary-table">
        <tr>
            <td class="label">Event Type</td>
            <td>{{ $event->event_type === 'Custom' ? $event->custom_event_type : $event->event_type }}</td>
        </tr>
        <tr>
            <td class="label">Date &amp; Time</td>
            <td>
                {{ optional($event->event_date)->format('l, F j, Y') ?? 'Not set' }}
                @if ($event->event_time)
                    at {{ \Illuminate\Support\Carbon::parse($event->event_time)->format('g:i A') }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Venue</td>
            <td>{{ $event->venue_name ?: 'Not set' }}{{ $event->location ? ' — '.$event->location : '' }}</td>
        </tr>
        <tr>
            <td class="label">Guests</td>
            <td>{{ $event->guest_count }} confirmed{{ $event->max_guests ? ' of '.$event->max_guests.' capacity' : '' }}</td>
        </tr>
        @if ($event->description)
            <tr>
                <td class="label">Description</td>
                <td class="description-text">{{ $event->description }}</td>
            </tr>
        @endif
    </table>

    <div class="budget-callout">
        Total Budget: {{ $event->currencySymbol() }}{{ number_format((float) $event->total_budget, 2) }}
        &nbsp;|&nbsp;
        Spent: {{ $event->currencySymbol() }}{{ number_format((float) $event->budget_spent, 2) }}
        &nbsp;|&nbsp;
        Remaining: {{ $event->currencySymbol() }}{{ number_format($event->budget_remaining, 2) }}
        &nbsp;|&nbsp;
        {{ \App\Models\Event::budgetStatusLabel($event->budget_percent) }}
    </div>

    <div class="section-title">Task Checklist</div>

    @forelse ($tasksByPhase as $phase => $tasks)
        <div class="phase-heading">{{ $phase }}</div>
        <table class="task-table">
            <tr>
                <th style="width: 30%;">Task</th>
                <th style="width: 14%;">Due Date</th>
                <th style="width: 12%;">Priority</th>
                <th style="width: 12%;">Status</th>
                <th style="width: 32%;">Notes</th>
            </tr>
            @foreach ($tasks as $task)
                <tr>
                    <td class="task-name">{{ $task->task_name }}</td>
                    <td>{{ optional($task->due_date)->format('M d, Y') ?? 'Not set' }}</td>
                    <td class="priority-{{ strtolower($task->priority) }}">{{ $task->priority }}</td>
                    <td class="status-{{ strtolower($task->status) }}">{{ $task->status }}</td>
                    <td class="task-notes">{{ $task->notes ?: '—' }}</td>
                </tr>
            @endforeach
        </table>
    @empty
        <p class="empty-note">No tasks have been added to this event yet.</p>
    @endforelse

    <div class="footer-note">
        Generated by EventPro Smart Event Planner &amp; Budget Tracker.
    </div>
</body>
</html>
