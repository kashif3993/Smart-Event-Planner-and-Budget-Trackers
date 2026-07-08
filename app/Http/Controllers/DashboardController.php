<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $userId = Auth::id();

        // Total Events
        $totalEvents = DB::table('events')
            ->where('user_id', $userId)
            ->count();

        // Upcoming Events
        $upcomingEventsCount = DB::table('events')
            ->where('user_id', $userId)
            ->where('event_date', '>=', now()->toDateString())
            ->count();

        // Pending Tasks
        $pendingTasks = DB::table('tasks')
            ->join('events', 'tasks.event_id', '=', 'events.id')
            ->where('events.user_id', $userId)
            ->where('tasks.status', 'Pending')
            ->count();

        // High priority pending tasks
        $highPriorityTasks = DB::table('tasks')
            ->join('events', 'tasks.event_id', '=', 'events.id')
            ->where('events.user_id', $userId)
            ->where('tasks.status', 'Pending')
            ->where('tasks.priority', 'High')
            ->count();

        // Recent Events
        $recentEvents = DB::table('events')
            ->where('user_id', $userId)
            ->orderBy('event_date', 'asc')
            ->take(3)
            ->get();

        return view('dashboard', compact(
            'totalEvents',
            'upcomingEventsCount',
            'pendingTasks',
            'highPriorityTasks',
            'recentEvents'
        ));
    }
}
