<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\AiTaskGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TimelineController extends Controller
{
    protected const STATUS_FILTERS = ['all', 'completed', 'pending', 'upcoming', 'skipped'];

    public function index(Request $request): View
    {
        $userEvents = Auth::user()->events()->orderBy('event_date')->get();

        $selectedEvent = null;
        if ($userEvents->isNotEmpty()) {
            $selectedEventId = $request->integer('event') ?: $userEvents->first()->id;
            $selectedEvent = $userEvents->firstWhere('id', $selectedEventId) ?? $userEvents->first();
        }

        if (! $selectedEvent) {
            return view('timeline.index', [
                'userEvents' => $userEvents,
                'selectedEvent' => null,
            ]);
        }

        $view = $request->query('view', 'vertical');
        $view = in_array($view, ['vertical', 'horizontal'], true) ? $view : 'vertical';

        $statusFilter = $request->query('status', 'all');
        $statusFilter = in_array($statusFilter, self::STATUS_FILTERS, true) ? $statusFilter : 'all';

        $allTasks = $selectedEvent->tasks()
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->get();

        $totalTasks = $allTasks->count();
        $completedCount = $allTasks->where('status', 'Completed')->count();
        $completedPercent = $totalTasks > 0 ? (int) round(($completedCount / $totalTasks) * 100) : 0;
        $daysLeft = $selectedEvent->days_remaining;

        // Same lead-time cutoffs the AI checklist generator uses, so the timeline
        // explains itself with the same "compressed" language rather than
        // inventing its own separate notion of what counts as short notice.
        if ($daysLeft < 0) {
            $leadTimeTier = 'standard';
        } elseif ($daysLeft <= AiTaskGeneratorService::URGENT_LEAD_DAYS) {
            $leadTimeTier = 'urgent';
        } elseif ($daysLeft <= AiTaskGeneratorService::CONDENSED_LEAD_DAYS) {
            $leadTimeTier = 'condensed';
        } else {
            $leadTimeTier = 'standard';
        }

        $missingPhases = collect(['Pre-Planning', 'Preparation', 'Day-Of'])
            ->reject(fn ($phase) => $allTasks->where('phase', $phase)->isNotEmpty())
            ->values();

        $items = $allTasks->map(fn (Task $task) => $this->buildTimelineItem($task));

        if ($statusFilter !== 'all') {
            $items = $items->filter(fn ($item) => $item['statusKey'] === $statusFilter)->values();
        }

        $items = $items->values()->map(function ($item, $index) {
            $item['side'] = $index % 2 === 0 ? 'left' : 'right';

            return $item;
        });

        // Velocity: completed this week vs last week. There's no completed_at column,
        // so updated_at is used as the best available proxy for "when it was finished".
        $completedTasks = $allTasks->where('status', 'Completed');
        $completedThisWeek = $completedTasks->filter(
            fn ($t) => $t->updated_at->greaterThanOrEqualTo(now()->startOfWeek())
        )->count();
        $completedLastWeek = $completedTasks->filter(
            fn ($t) => $t->updated_at->between(now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek())
        )->count();
        $velocityChange = $completedLastWeek > 0
            ? (int) round((($completedThisWeek - $completedLastWeek) / $completedLastWeek) * 100)
            : ($completedThisWeek > 0 ? 100 : 0);

        $weeklyCounts = [];
        for ($i = 5; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = now()->subWeeks($i)->endOfWeek();
            $weeklyCounts[] = $completedTasks->filter(
                fn ($t) => $t->updated_at->between($weekStart, $weekEnd)
            )->count();
        }
        $maxWeekly = max($weeklyCounts) ?: 1;
        $weeklyBarPercents = array_map(fn ($c) => $c > 0 ? max(($c / $maxWeekly) * 100, 8) : 4, $weeklyCounts);

        return view('timeline.index', compact(
            'userEvents',
            'selectedEvent',
            'view',
            'statusFilter',
            'items',
            'totalTasks',
            'completedPercent',
            'daysLeft',
            'velocityChange',
            'weeklyBarPercents',
            'leadTimeTier',
            'missingPhases'
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildTimelineItem(Task $task): array
    {
        if ($task->status === 'Completed') {
            $statusKey = 'completed';
            $statusLabel = 'Complete';
        } elseif ($task->status === 'Skipped') {
            $statusKey = 'skipped';
            $statusLabel = 'Skipped';
        } elseif ($task->due_date && $task->due_date->isPast()) {
            $statusKey = 'pending';
            $statusLabel = 'Overdue';
        } elseif ($task->due_date && $task->due_date->lessThanOrEqualTo(now()->addDays(14))) {
            $statusKey = 'pending';
            $statusLabel = 'Pending';
        } else {
            $statusKey = 'upcoming';
            $statusLabel = 'Upcoming';
        }

        return [
            'task' => $task,
            'phase' => $task->phase,
            'title' => $task->task_name,
            'subtitle' => $task->priority.' Priority · '.$task->source.' sourced',
            'dueDate' => $task->due_date,
            'statusKey' => $statusKey,
            'statusLabel' => $statusLabel,
            'description' => $task->notes ?: 'No additional notes for this milestone.',
        ];
    }
}
