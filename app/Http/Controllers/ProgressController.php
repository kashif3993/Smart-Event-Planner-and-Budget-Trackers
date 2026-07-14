<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProgressController extends Controller
{
    protected const PHASES = ['Pre-Planning', 'Preparation', 'Day-Of'];

    public function index(Request $request): View
    {
        $userEvents = Auth::user()->events()->orderBy('event_date')->get();

        $selectedEvent = null;
        if ($userEvents->isNotEmpty()) {
            $selectedEventId = $request->integer('event') ?: $userEvents->first()->id;
            $selectedEvent = $userEvents->firstWhere('id', $selectedEventId) ?? $userEvents->first();
        }

        if (! $selectedEvent) {
            return view('progress.index', [
                'userEvents' => $userEvents,
                'selectedEvent' => null,
            ]);
        }

        $period = $request->query('period', '1W');
        $period = in_array($period, ['1W', '1M', 'ALL'], true) ? $period : '1W';

        $tasks = $selectedEvent->tasks;

        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('status', 'Completed');
        $pendingTasks = $tasks->where('status', 'Pending');
        $overdueTasks = $pendingTasks->filter(fn ($t) => $t->due_date && $t->due_date->isPast());
        // "Upcoming" per the spec's three-way split: pending, but not yet overdue.
        $upcomingTasks = $pendingTasks->reject(fn ($t) => $t->due_date && $t->due_date->isPast());

        $completedCount = $completedTasks->count();
        $overdueCount = $overdueTasks->count();
        $upcomingCount = $upcomingTasks->count();
        $totalProgress = $totalTasks > 0 ? (int) round(($completedCount / $totalTasks) * 100) : 0;

        // Milestones: a planning phase counts as "reached" once every task in it is completed.
        $milestonesTotal = collect(self::PHASES)->filter(fn ($p) => $tasks->where('phase', $p)->isNotEmpty())->count();
        $milestonesCompleted = collect(self::PHASES)->filter(function ($phase) use ($tasks) {
            $phaseTasks = $tasks->where('phase', $phase);

            return $phaseTasks->isNotEmpty() && $phaseTasks->every(fn ($t) => $t->status === 'Completed');
        })->count();

        // There's no completed_at column, so updated_at is used as the best available
        // proxy for "when a task was marked done" (status flips are the main write path).
        $completedThisWeek = $completedTasks->filter(
            fn ($t) => $t->updated_at->greaterThanOrEqualTo(now()->startOfWeek())
        )->count();
        $completedLastWeek = $completedTasks->filter(
            fn ($t) => $t->updated_at->between(now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek())
        )->count();
        $efficiencyChange = $completedLastWeek > 0
            ? round((($completedThisWeek - $completedLastWeek) / $completedLastWeek) * 100, 1)
            : ($completedThisWeek > 0 ? 100.0 : 0.0);

        $velocity = round($completedTasks->filter(
            fn ($t) => $t->updated_at->greaterThanOrEqualTo(now()->subDays(6)->startOfDay())
        )->count() / 7, 1);

        [$trendLabels, $trendData] = $this->buildTrend($completedTasks, $period);

        $immediateActions = $pendingTasks
            ->sortBy(fn ($t) => $t->due_date ?? Carbon::now()->addYears(10))
            ->take(5)
            ->values();

        $recentActivity = Activity::forUser(Auth::id())->recent(5)->get();

        // Vendor categories with no budget allocated yet — surfaced alongside
        // pending tasks as things that need the user's attention next.
        $unfilledCategories = $selectedEvent->vendorCategories
            ->filter(fn ($c) => (float) $c->allocated_amount <= 0)
            ->values();

        $aiConfigured = filled(config('services.ai_task_generator.key'));

        if ($overdueCount === 0) {
            $riskLevel = 'Low';
        } elseif ($overdueCount <= 2) {
            $riskLevel = 'Medium';
        } else {
            $riskLevel = 'High';
        }

        $daysRemaining = $selectedEvent->days_remaining;

        // Computed straight from logged expenses rather than the event's manually-typed
        // budget_spent field, so this always reflects what's actually been logged.
        $totalBudget = (float) $selectedEvent->total_budget;
        $totalSpent = (float) $selectedEvent->expenses()->sum('actual_cost');
        $budgetPercentRaw = $totalBudget > 0 ? ($totalSpent / $totalBudget) * 100 : 0;
        $budgetPercent = (int) min(100, round($budgetPercentRaw));
        $budgetStatus = Event::budgetStatusLabel($budgetPercentRaw);

        return view('progress.index', compact(
            'userEvents',
            'selectedEvent',
            'period',
            'totalTasks',
            'completedCount',
            'upcomingCount',
            'overdueCount',
            'totalProgress',
            'milestonesTotal',
            'milestonesCompleted',
            'efficiencyChange',
            'velocity',
            'trendLabels',
            'trendData',
            'immediateActions',
            'unfilledCategories',
            'recentActivity',
            'aiConfigured',
            'riskLevel',
            'daysRemaining',
            'totalBudget',
            'totalSpent',
            'budgetPercent',
            'budgetStatus'
        ));
    }

    public function export(Request $request)
    {
        $userEvents = Auth::user()->events()->pluck('id');
        $eventId = $request->integer('event');

        $event = Event::findOrFail($eventId);
        abort_unless($userEvents->contains($event->id), 403);

        $tasks = $event->tasks()->orderBy('phase')->orderBy('due_date')->get();

        $filename = 'progress-report-'.Str::slug($event->event_name).'.csv';

        return response()->streamDownload(function () use ($tasks) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Task', 'Phase', 'Priority', 'Status', 'Due Date', 'Notes']);

            foreach ($tasks as $task) {
                fputcsv($handle, [
                    $task->task_name,
                    $task->phase,
                    $task->priority,
                    $task->status,
                    optional($task->due_date)->format('Y-m-d'),
                    $task->notes,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, int>}
     */
    protected function buildTrend($completedTasks, string $period): array
    {
        if ($period === '1M') {
            $labels = [];
            $data = [];
            for ($i = 29; $i >= 0; $i--) {
                $day = now()->subDays($i);
                $labels[] = $day->format('M d');
                $data[] = $completedTasks->filter(fn ($t) => $t->updated_at->isSameDay($day))->count();
            }

            return [$labels, $data];
        }

        if ($period === 'ALL') {
            $labels = [];
            $data = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $labels[] = strtoupper($month->format('M'));
                $data[] = $completedTasks->filter(
                    fn ($t) => $t->updated_at->isSameMonth($month) && $t->updated_at->isSameYear($month)
                )->count();
            }

            return [$labels, $data];
        }

        $labels = [];
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $labels[] = $day->format('D');
            $data[] = $completedTasks->filter(fn ($t) => $t->updated_at->isSameDay($day))->count();
        }

        return [$labels, $data];
    }
}
