<?php

namespace App\Http\Controllers;

use App\Models\EventGroup;
use App\Services\ContentionDetectionService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(ContentionDetectionService $contention): View
    {
        $user = auth()->user();

        // CR-13/CR-14 — contention is evaluated per pooled group; every group
        // currently in contention gets its own critical banner here.
        $contendedGroups = EventGroup::where('user_id', $user->id)
            ->where('budget_mode', 'Pooled')
            ->where('status', 'Active')
            ->with('events')
            ->get()
            ->filter(fn (EventGroup $group) => $contention->isContended($group))
            ->map(fn (EventGroup $group) => [
                'group' => $group,
                'contentionData' => $contention->snapshot($group),
            ]);

        $totalEvents = \App\Models\Event::where('user_id', $user->id)->count();
        $upcomingEventsCount = \App\Models\Event::where('user_id', $user->id)->where('event_date', '>=', now())->count();
        
        $totalBudget = \App\Models\Event::where('user_id', $user->id)->sum('total_budget');
        $budgetSpent = \App\Models\Event::where('user_id', $user->id)->sum('budget_spent');
        
        $budgetPercentage = $totalBudget > 0 ? min(($budgetSpent / $totalBudget) * 100, 100) : 0;
        
        $pendingTasks = \App\Models\Task::whereHas('event', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('status', 'pending')->count();
        
        $highPriorityTasks = \App\Models\Task::whereHas('event', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('status', 'pending')->where('priority', 'high')->count();
        
        $recentEvents = \App\Models\Event::where('user_id', $user->id)
            ->orderBy('event_date', 'desc')
            ->take(3)
            ->get();
            
        // Assuming tasks have completed/pending status for global progress
        $totalTasks = \App\Models\Task::whereHas('event', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })->count();
        $completedTasks = \App\Models\Task::whereHas('event', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('status', 'completed')->count();
        
        $globalProgress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
        $activeStagesCount = \App\Models\Event::where('user_id', $user->id)->where('status', '!=', 'completed')->count();
        
        $dueToday = \App\Models\Task::whereHas('event', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('status', 'pending')->whereDate('due_date', now()->toDateString())->count();

        // Monthly expenses for the chart — scoped through user's events (new schema)
        $userEventIds   = \App\Models\Event::where('user_id', $user->id)->pluck('id');
        $sixMonthsAgo   = now()->subMonths(5)->startOfMonth();
        $recentExpenses = \App\Models\Expense::whereIn('event_id', $userEventIds)
            ->where('date_logged', '>=', $sixMonthsAgo)
            ->get();

        $monthlyLabels       = [];
        $monthlySpendData    = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStr          = now()->subMonths($i)->format('Y-m');
            $monthlyLabels[]   = strtoupper(now()->subMonths($i)->format('M'));
            $monthlySpendData[] = $recentExpenses->filter(function ($expense) use ($monthStr) {
                return $expense->date_logged && $expense->date_logged->format('Y-m') === $monthStr;
            })->sum('actual_cost');
        }

        $maxSpend = max($monthlySpendData) ?: 1;
        $monthlySpendPercentages = array_map(function ($spend) use ($maxSpend) {
            return max(($spend / $maxSpend) * 100, 5);
        }, $monthlySpendData);

        return view('dashboard', compact(
            'contendedGroups',
            'totalEvents',
            'upcomingEventsCount',
            'budgetPercentage',
            'pendingTasks',
            'highPriorityTasks',
            'recentEvents',
            'globalProgress',
            'activeStagesCount',
            'dueToday',
            'monthlyLabels',
            'monthlySpendPercentages'
        ));
    }
}
