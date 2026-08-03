<?php

namespace App\Http\Controllers;

use App\Exceptions\AiTaskGeneratorNotConfiguredException;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Services\AiTaskGeneratorService;
use App\Services\ContentionDetectionService;
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

    public function show(Request $request, Event $event, ContentionDetectionService $contention): View
    {
        $this->authorizeEvent($event);

        // FR-17 — an event caught in its group's pooled-budget contention
        // shows a secondary indicator here linking back to the pool-level
        // conflict, so the user isn't only told about it via the dashboard.
        $groupContention = null;
        if ($event->event_group_id && $event->eventGroup && $event->eventGroup->isPooled() && $contention->isContended($event->eventGroup)) {
            $groupContention = [
                'group' => $event->eventGroup,
                'deficit' => $contention->globalDeficit($event->eventGroup),
            ];
        }

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

        return view('events.show', compact('event', 'tasks', 'tasksByPhase', 'allEventTasks', 'groupContention'));
    }

    public function store(
        StoreEventRequest $request,
        AiTaskGeneratorService $taskGenerator,
        VendorCategorySuggestionService $categorySuggester
    ): RedirectResponse {
        $data = $request->validated();

        if ($request->hasFile('venue_image')) {
            $data['venue_image'] = $request->file('venue_image')->store('venue-images', 'public');
        }

        $data['user_id'] = Auth::id();

        $event = Event::create($data);

        $categorySuggester->suggestFor($event);

        // The AI call below can legitimately take longer than PHP's default
        // execution limit once retries are factored in — extend it rather than
        // let the whole "create event" request die with a fatal error.
        set_time_limit(120);

        $tasksGenerated = 0;
        try {
            $result = $taskGenerator->generateTasks($event);

            $tasksGenerated = $taskGenerator->createTasks($event, $result['tasks'])->count();

            if (! empty($result['insight'])) {
                $event->update(['ai_insight' => $result['insight']]);
            }
        } catch (AiTaskGeneratorNotConfiguredException) {
            // AI isn't configured in this environment — the event still gets its
            // suggested vendor categories, and tasks can be added manually or via
            // the "Generate with AI" button on the event page once it is configured.
        } catch (\Throwable $e) {
            report($e);
        }

        $message = $event->event_name.' has been created';
        $message .= $tasksGenerated > 0
            ? ", with {$tasksGenerated} AI-generated starter tasks and suggested vendor categories."
            : ', with suggested vendor categories.';

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
