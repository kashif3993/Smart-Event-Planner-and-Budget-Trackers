<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventGroupRequest;
use App\Models\Event;
use App\Models\EventGroup;
use App\Services\ContentionDetectionService;
use App\Services\GroupRollupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EventGroupController extends Controller
{
    public function index(): View
    {
        $groups = EventGroup::where('user_id', Auth::id())
            ->withCount('events')
            ->orderByRaw("FIELD(status, 'Active', 'Archived')")
            ->orderBy('start_date')
            ->get();

        $ungroupedCount = Event::where('user_id', Auth::id())->whereNull('event_group_id')->count();

        return view('event-groups.index', compact('groups', 'ungroupedCount'));
    }

    public function create(): View
    {
        $candidateEvents = Event::where('user_id', Auth::id())
            ->whereNull('event_group_id')
            ->orderBy('event_date')
            ->get();

        return view('event-groups.create', compact('candidateEvents'));
    }

    public function store(StoreEventGroupRequest $request, GroupRollupService $rollup): RedirectResponse
    {
        $data = $request->validated();
        $eventIds = $data['event_ids'];
        unset($data['event_ids']);

        $firstEvent = Event::findOrFail($eventIds[0]);

        $group = DB::transaction(function () use ($data, $eventIds, $firstEvent) {
            $group = EventGroup::create([
                ...$data,
                'user_id' => Auth::id(),
                'currency' => $firstEvent->currency,
            ]);

            Event::whereIn('id', $eventIds)->update(['event_group_id' => $group->id]);

            return $group;
        });

        $rollup->recalculateDates($group);

        return redirect()->route('event-groups.show', $group)
            ->with('success', "\"{$group->name}\" has been created with ".count($eventIds).' events.');
    }

    public function show(EventGroup $group, GroupRollupService $rollup, ContentionDetectionService $contention): View
    {
        $this->authorizeGroup($group);

        $overview = $rollup->overview($group);
        $isContended = $group->isPooled() && $contention->isContended($group);
        $contentionData = $isContended ? $contention->snapshot($group) : null;

        return view('event-groups.show', compact('group', 'overview', 'isContended', 'contentionData'));
    }

    public function edit(EventGroup $group): View
    {
        $this->authorizeGroup($group);

        return view('event-groups.edit', compact('group'));
    }

    public function update(EventGroup $group): RedirectResponse
    {
        $this->authorizeGroup($group);

        $data = request()->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'group_type' => ['required', 'in:Wedding,Birthday Party,Corporate Event,Baby Shower,Graduation,Custom'],
            'custom_group_type' => ['nullable', 'required_if:group_type,Custom', 'string', 'max:100'],
        ]);

        $group->update($data);

        return redirect()->route('event-groups.show', $group)->with('success', 'Group details updated.');
    }

    /**
     * EG-52 — dissolve: returns every sub-event to standalone status, with no
     * event data lost or altered. EG-56 — blocked while contention is active.
     */
    public function destroy(EventGroup $group, ContentionDetectionService $contention): RedirectResponse
    {
        $this->authorizeGroup($group);

        if ($group->isPooled() && $contention->isContended($group)) {
            return back()->with('error', 'Resolve the active budget contention before dissolving this group.');
        }

        if ($group->isPooled()) {
            $splits = request()->input('splits', []);
            $events = $group->events;

            if (count($splits) !== $events->count()) {
                return back()->with('error', 'Confirm a standalone budget for every event before dissolving a pooled group.');
            }

            foreach ($events as $event) {
                if (! isset($splits[$event->id]) || (float) $splits[$event->id] < $event->budget_spent) {
                    return back()->with('error', "The standalone budget for \"{$event->event_name}\" can't be less than what's already spent.");
                }
            }

            DB::transaction(function () use ($events, $splits) {
                foreach ($events as $event) {
                    $event->update(['total_budget' => $splits[$event->id]]);
                }
            });
        }

        $groupName = $group->name;
        $group->delete();

        return redirect()->route('event-groups.index')->with('success', "\"{$groupName}\" has been dissolved. Its events are now standalone again.");
    }

    /**
     * EG-51 — archive once every sub-event has passed. EG-56 — blocked during
     * active contention.
     */
    public function archive(EventGroup $group, ContentionDetectionService $contention): RedirectResponse
    {
        $this->authorizeGroup($group);

        if ($group->isPooled() && $contention->isContended($group)) {
            return back()->with('error', 'Resolve the active budget contention before archiving this group.');
        }

        $stillUpcoming = $group->events()->where('event_date', '>=', now()->startOfDay())->exists();
        if ($stillUpcoming) {
            return back()->with('error', 'This group has events that haven\'t happened yet — it can only be archived once every event has passed.');
        }

        $group->update(['status' => 'Archived', 'archived_at' => now()]);

        return back()->with('success', "\"{$group->name}\" has been archived.");
    }

    public function timeline(EventGroup $group): View
    {
        $this->authorizeGroup($group);

        $events = $group->events()->orderBy('event_date')->get();

        // EG-27 — flag same-day overlaps, since two sub-events on the same day
        // is usually a scheduling error worth surfacing.
        $overlapDates = $events->groupBy(fn (Event $e) => $e->event_date->toDateString())
            ->filter(fn ($rows) => $rows->count() > 1)
            ->keys();

        return view('event-groups.timeline', compact('group', 'events', 'overlapDates'));
    }

    protected function authorizeGroup(EventGroup $group): void
    {
        abort_unless($group->user_id === Auth::id(), 403);
    }
}
