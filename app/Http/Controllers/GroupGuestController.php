<?php

namespace App\Http\Controllers;

use App\Models\EventGroup;
use App\Models\Guest;
use App\Services\GroupRosterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class GroupGuestController extends Controller
{
    public function index(EventGroup $group, GroupRosterService $roster): View
    {
        $this->authorizeGroup($group);

        $data = $roster->guestRoster($group);

        return view('event-groups.guests', [
            'group' => $group,
            'guests' => $data['guests'],
            'uniqueCount' => $data['unique_count'],
            'attendanceCount' => $data['attendance_count'],
            'duplicates' => $data['duplicates'],
        ]);
    }

    public function vendors(EventGroup $group, GroupRosterService $roster): View
    {
        $this->authorizeGroup($group);

        return view('event-groups.vendors', [
            'group' => $group,
            'vendors' => $roster->vendorRoster($group),
        ]);
    }

    /**
     * EG-32 — add a guest to several sub-events in a single action.
     */
    public function store(EventGroup $group): RedirectResponse
    {
        $this->authorizeGroup($group);

        $data = request()->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'event_ids' => ['required', 'array', 'min:1'],
            'event_ids.*' => ['integer'],
        ]);

        $groupEventIds = $group->events()->pluck('id');
        $eventIds = collect($data['event_ids'])->intersect($groupEventIds);

        if ($eventIds->isEmpty()) {
            return back()->with('error', 'Select at least one event in this group.');
        }

        $guest = Guest::create([
            'user_id' => Auth::id(),
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        $guest->events()->attach($eventIds);

        return back()->with('success', "\"{$guest->name}\" added to the roster.");
    }

    /**
     * EG-31/EG-35 — merge is always an explicit user decision; the duplicate
     * is deleted and every attendance repoints to the surviving guest.
     */
    public function merge(EventGroup $group, GroupRosterService $roster): RedirectResponse
    {
        $this->authorizeGroup($group);

        $data = request()->validate([
            'survivor_id' => ['required', 'integer', 'exists:guests,id'],
            'duplicate_id' => ['required', 'integer', 'exists:guests,id', 'different:survivor_id'],
        ]);

        $survivor = Guest::findOrFail($data['survivor_id']);
        $duplicate = Guest::findOrFail($data['duplicate_id']);
        abort_unless($survivor->user_id === Auth::id() && $duplicate->user_id === Auth::id(), 403);

        $roster->mergeGuests($survivor, $duplicate);

        return back()->with('success', "Merged into \"{$survivor->name}\".");
    }

    public function dismissDuplicate(EventGroup $group, GroupRosterService $roster): RedirectResponse
    {
        $this->authorizeGroup($group);

        $data = request()->validate([
            'guest_id_a' => ['required', 'integer', 'exists:guests,id'],
            'guest_id_b' => ['required', 'integer', 'exists:guests,id', 'different:guest_id_a'],
        ]);

        $a = Guest::findOrFail($data['guest_id_a']);
        $b = Guest::findOrFail($data['guest_id_b']);
        abort_unless($a->user_id === Auth::id() && $b->user_id === Auth::id(), 403);

        $roster->dismissDuplicate($group, $a, $b);

        return back()->with('success', 'Noted — these will no longer be suggested as duplicates.');
    }

    protected function authorizeGroup(EventGroup $group): void
    {
        abort_unless($group->user_id === Auth::id(), 403);
    }
}
