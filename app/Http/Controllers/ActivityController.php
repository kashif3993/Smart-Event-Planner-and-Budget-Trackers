<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityController extends Controller
{
    /**
     * Display a paginated list of the logged-in user's activities.
     */
    public function index(Request $request)
    {
        $filter = $request->query('type', 'all');

        $query = Activity::with('event')
            ->forUser(Auth::id())
            ->latest();

        // Filter by activity type if provided
        if ($filter !== 'all') {
            $query->where('type', $filter);
        }

        $activities = $query->paginate(15)->withQueryString();

        // Distinct types for the filter dropdown
        $types = Activity::forUser(Auth::id())
            ->select('type')
            ->distinct()
            ->pluck('type');

        return view('activity.index', compact('activities', 'filter', 'types'));
    }

    /**
     * Remove a single activity log entry.
     */
    public function destroy(Activity $activity)
    {
        // Only the owner can delete their activity
        if ($activity->user_id !== Auth::id()) {
            abort(403);
        }

        $activity->delete();

        return back()->with('success', 'Activity log deleted.');
    }

    /**
     * Clear all activity logs for the logged-in user.
     */
    public function clearAll()
    {
        Activity::forUser(Auth::id())->delete();

        return back()->with('success', 'All activity logs cleared.');
    }

    /**
     * Static helper: log an activity from anywhere in the app.
     *
     * Usage:
     *   ActivityController::log('event_created', 'You created Birthday Party', 'fa-calendar', 'blue', $eventId);
     */
    public static function log(
        string $type,
        string $description,
        string $icon = 'fa-circle',
        string $color = 'blue',
        ?int $eventId = null
    ): Activity {
        return Activity::create([
            'user_id'     => Auth::id(),
            'event_id'    => $eventId,
            'type'        => $type,
            'description' => $description,
            'icon'        => $icon,
            'color'       => $color,
        ]);
    }
}
