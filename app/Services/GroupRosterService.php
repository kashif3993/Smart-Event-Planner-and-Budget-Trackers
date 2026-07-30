<?php

namespace App\Services;

use App\Models\EventGroup;
use App\Models\Guest;
use App\Models\VendorCategory;
use Illuminate\Support\Collection;

class GroupRosterService
{
    /**
     * EG-29/EG-30/EG-34 — every guest across the group's sub-events, appearing
     * once with their per-sub-event attendances, plus unique-vs-total counts.
     *
     * @return array{guests: Collection, unique_count: int, attendance_count: int, duplicates: Collection}
     */
    public function guestRoster(EventGroup $group): array
    {
        $eventIds = $group->events->pluck('id');

        $guests = Guest::whereHas('events', fn ($q) => $q->whereIn('events.id', $eventIds))
            ->with(['events' => fn ($q) => $q->whereIn('events.id', $eventIds)])
            ->orderBy('name')
            ->get();

        return [
            'guests' => $guests,
            'unique_count' => $guests->count(),
            'attendance_count' => $guests->sum(fn (Guest $g) => $g->events->count()),
            'duplicates' => $this->duplicateCandidates($group, $guests),
        ];
    }

    /**
     * EG-35 — same-name guests across the group's incoming lists are surfaced
     * for the user to confirm or reject, minus pairs already dismissed.
     *
     * @return Collection<int, array{a: Guest, b: Guest}>
     */
    protected function duplicateCandidates(EventGroup $group, Collection $guests): Collection
    {
        $dismissed = $group->dismissedDuplicatePairs()
            ->get(['guest_id_a', 'guest_id_b'])
            ->map(fn ($row) => $row->guest_id_a.'-'.$row->guest_id_b)
            ->flip();

        $byName = $guests->groupBy(fn (Guest $g) => strtolower(trim($g->name)));

        $pairs = collect();

        foreach ($byName as $sameName) {
            if ($sameName->count() < 2) {
                continue;
            }

            $values = $sameName->values();

            for ($i = 0; $i < $values->count(); $i++) {
                for ($j = $i + 1; $j < $values->count(); $j++) {
                    [$a, $b] = $values[$i]->id < $values[$j]->id
                        ? [$values[$i], $values[$j]]
                        : [$values[$j], $values[$i]];

                    if (! $dismissed->has("{$a->id}-{$b->id}")) {
                        $pairs->push(['a' => $a, 'b' => $b]);
                    }
                }
            }
        }

        return $pairs;
    }

    /**
     * EG-31 — merging repoints every attendance to the surviving guest and
     * removes the duplicate record entirely (an explicit user decision, never
     * automatic — see EG-35).
     */
    public function mergeGuests(Guest $survivor, Guest $duplicate): void
    {
        $existingEventIds = $survivor->events()->pluck('events.id');

        $duplicate->events()
            ->whereNotIn('events.id', $existingEventIds)
            ->get()
            ->each(fn ($event) => $survivor->events()->attach($event->id));

        $duplicate->delete();
    }

    public function dismissDuplicate(EventGroup $group, Guest $a, Guest $b): void
    {
        [$idA, $idB] = $a->id < $b->id ? [$a->id, $b->id] : [$b->id, $a->id];

        $group->dismissedDuplicatePairs()->firstOrCreate([
            'guest_id_a' => $idA,
            'guest_id_b' => $idB,
        ]);
    }

    /**
     * EG-37/EG-38 — every vendor across the group's sub-events, listed once,
     * with per-sub-event cost and a combined total. No first-class Vendor
     * entity exists in this app, so vendors are matched by normalized name.
     *
     * @return Collection<int, object>
     */
    public function vendorRoster(EventGroup $group): Collection
    {
        $events = $group->events;

        $categories = VendorCategory::whereIn('event_id', $events->pluck('id'))
            ->whereNotNull('vendor_name')
            ->where('vendor_name', '!=', '')
            ->withSum('expenses as spent_amount', 'actual_cost')
            ->get();

        return $categories
            ->groupBy(fn (VendorCategory $c) => strtolower(trim($c->vendor_name)))
            ->map(function (Collection $rows) use ($events) {
                $perEvent = $rows->groupBy('event_id')->map(function (Collection $eventRows, $eventId) use ($events) {
                    return (object) [
                        'event' => $events->firstWhere('id', (int) $eventId),
                        'cost' => (float) $eventRows->sum('spent_amount'),
                    ];
                })->values();

                return (object) [
                    'vendor_name' => $rows->first()->vendor_name,
                    'per_event' => $perEvent,
                    'sub_event_count' => $perEvent->count(),
                    'combined_total' => (float) $perEvent->sum('cost'),
                ];
            })
            ->sortByDesc('combined_total')
            ->values();
    }
}
