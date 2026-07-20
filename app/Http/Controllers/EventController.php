<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Services\VendorCategorySuggestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        $events = Event::where('user_id', Auth::id())
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($query) => $query->where('status', 'Completed'),
            ])
            ->orderBy('event_date')
            ->get();

        foreach ($events as $event) {
            $event->progress = $event->tasks_count > 0
                ? (int) round(($event->completed_tasks_count / $event->tasks_count) * 100)
                : 0;
        }

        return view('events.index', compact('events'));
    }

    public function show(Request $request, Event $event): View
    {
        $this->authorizeEvent($event);

        $query = $event->tasks()
            ->with('dependsOn:id,task_name')
            ->orderByRaw("FIELD(phase, 'Pre-Planning', 'Preparation', 'Day-Of')")
            ->orderByRaw("FIELD(priority, 'High', 'Medium', 'Low')")
            ->orderByRaw('dependency_task_id IS NOT NULL')
            ->orderBy('due_date');

        if ($request->filled('phase')) {
            $query->where('phase', $request->string('phase'));
        }

        $tasks = $query->paginate(12)->withQueryString();

        // Full (unpaginated) task list for the "Depends On" dropdown, so it always
        // offers every task in the event regardless of which page is being viewed.
        $allEventTasks = $event->tasks()->orderBy('task_name')->get(['id', 'task_name']);

        $tasksByPhase = $tasks->getCollection()->groupBy('phase');

        return view('events.show', compact('event', 'tasks', 'tasksByPhase', 'allEventTasks'));
    }

    public function store(
        StoreEventRequest $request,
        VendorCategorySuggestionService $categorySuggester
    ): RedirectResponse {
        $data = $request->validated();

        if ($request->hasFile('venue_image')) {
            $data['venue_image'] = $request->file('venue_image')->store('venue-images', 'public');
        }

        $data['user_id'] = Auth::id();

        $event = Event::create($data);

        $categorySuggester->suggestFor($event);

        // AI task generation is not run here anymore — it's a slow external API
        // call that used to make event creation take several seconds. Tasks can
        // be added manually or via the "Generate with AI" button on the event page.
        $message = $event->event_name.' has been created, with suggested vendor categories.';

        return redirect()->route('events.show', $event)->with('success', $message);
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $this->authorizeEvent($event);

        $data = $request->validated();

        if ($request->hasFile('venue_image')) {
            if ($event->venue_image) {
                Storage::disk('public')->delete($event->venue_image);
            }

            $data['venue_image'] = $request->file('venue_image')->store('venue-images', 'public');
        }

        $event->update($data);

        return back()->with('success', $event->event_name.' has been updated.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorizeEvent($event);

        if ($event->venue_image) {
            Storage::disk('public')->delete($event->venue_image);
        }

        $eventName = $event->event_name;
        $event->delete();

        return redirect()->route('events.index')
            ->with('success', $eventName.' has been deleted.');
    }

    protected function authorizeEvent(Event $event): void
    {
        abort_unless($event->user_id === Auth::id(), 403);
    }
}
