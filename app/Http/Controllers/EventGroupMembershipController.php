<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventGroup;
use App\Services\ContentionDetectionService;
use App\Services\GroupRollupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class EventGroupMembershipController extends Controller
{
    /**
     * EG-2 — attach an existing standalone event to a group.
     */
    public function attach(EventGroup $group, GroupRollupService $rollup): RedirectResponse
    {
        $this->authorizeGroup($group);

        if ($group->events()->count() >= 6) {
            return back()->with('error', 'A group can hold at most 6 events.');
        }

        $data = request()->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
        ]);

        $event = Event::findOrFail($data['event_id']);
        abort_unless($event->user_id === Auth::id(), 403);

        if ($event->event_group_id !== null) {
            return back()->with('error', "\"{$event->event_name}\" already belongs to another group.");
        }

        if ($group->currency !== null && $event->currency !== $group->currency) {
            return back()->with('error', "\"{$event->event_name}\" is in {$event->currency}, but this group is in {$group->currency}.");
        }

        $event->update(['event_group_id' => $group->id]);
        $rollup->recalculateDates($group);

        return back()->with('success', "\"{$event->event_name}\" has been added to the group.");
    }

    /**
     * EG-8 — removing an event returns it to standalone status with all data
     * intact. EG-54 — a group left with one member prompts add-or-dissolve.
     */
    public function detach(EventGroup $group, Event $event, GroupRollupService $rollup, ContentionDetectionService $contention): RedirectResponse
    {
        $this->authorizeGroup($group);
        abort_unless($event->event_group_id === $group->id, 404);

        if ($group->isPooled() && $contention->isContended($group)) {
            return back()->with('error', 'Resolve the active budget contention before changing this group\'s membership.');
        }

        $event->update(['event_group_id' => null]);
        $rollup->recalculateDates($group);

        $remaining = $group->events()->count();
        $message = "\"{$event->event_name}\" is now a standalone event.";

        if ($remaining === 1) {
            $message .= ' This group has only one event left — add another or dissolve it.';
        }

        return back()->with('success', $message);
    }

    protected function authorizeGroup(EventGroup $group): void
    {
        abort_unless($group->user_id === Auth::id(), 403);
    }
}
