<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventGroup;
use Illuminate\Support\Collection;

class ContentionDetectionService
{
    /**
     * A sub-event's combined draw on the pool. No "committed cost" field
     * exists in this app, so the higher of budget and actual spend is used
     * as the pool draw (a budget not yet spent still reserves that much).
     */
    public function combinedCost(Event $event): float
    {
        return max((float) $event->total_budget, (float) $event->budget_spent);
    }

    public function globalDeficit(EventGroup $group): float
    {
        $combined = $group->events->sum(fn (Event $e) => $this->combinedCost($e));

        return $combined - (float) $group->pooled_budget_cap;
    }

    public function isBreached(Event $event): bool
    {
        return (float) $event->budget_spent > (float) $event->total_budget;
    }

    /**
     * No forecast field exists in this app, so "projecting toward a breach"
     * reuses the existing 90%-spent threshold the rest of the app already
     * treats as the edge of "On Track" (see Event::budgetStatusLabel()).
     */
    public function isProjectingBreach(Event $event): bool
    {
        return ! $this->isBreached($event) && $event->budget_percent >= 90;
    }

    /**
     * @return Collection<int, Event>
     */
    protected function eventsInBreachOrProjecting(EventGroup $group): Collection
    {
        return $group->events->filter(fn (Event $e) => $this->isBreached($e) || $this->isProjectingBreach($e));
    }

    /**
     * CR-9 — contended only when the cap is breached AND two or more
     * sub-events are contributing to (or projecting toward) that breach.
     * CR-10 — a single contributing event routes elsewhere instead.
     */
    public function isContended(EventGroup $group): bool
    {
        if (! $group->isPooled() || $group->pooled_budget_cap === null) {
            return false;
        }

        if ($this->globalDeficit($group) <= 0) {
            return false;
        }

        return $this->eventsInBreachOrProjecting($group)->count() >= 2;
    }

    /**
     * CR-10 — when the pool is breached by exactly one event, callers should
     * route the user to the existing single-event rebalancer instead.
     */
    public function singleBreachingEvent(EventGroup $group): ?Event
    {
        if (! $group->isPooled() || $group->pooled_budget_cap === null) {
            return null;
        }

        if ($this->globalDeficit($group) <= 0) {
            return null;
        }

        $contributing = $this->eventsInBreachOrProjecting($group);

        return $contributing->count() === 1 ? $contributing->first() : null;
    }

    /**
     * CR-20 — an immutable snapshot of every sub-event in contention, taken
     * at read time. Callers (the sandbox) hold onto this rather than
     * re-querying, so figures can't shift mid-review.
     *
     * @return array{
     *   pooled_budget_cap: float, global_deficit: float, events: Collection
     * }
     */
    public function snapshot(EventGroup $group): array
    {
        $contributing = $this->eventsInBreachOrProjecting($group);

        $events = $contributing->map(fn (Event $e) => (object) [
            'id' => $e->id,
            'name' => $e->event_name,
            'type' => $e->event_type,
            'date' => $e->event_date->toDateString(),
            'days_remaining' => max(1, $e->days_remaining),
            'total_budget' => (float) $e->total_budget,
            'budget_spent' => (float) $e->budget_spent,
            'overrun' => max(0, (float) $e->budget_spent - (float) $e->total_budget),
            'headroom' => max(0, (float) $e->total_budget - (float) $e->budget_spent),
        ])->values();

        return [
            'pooled_budget_cap' => (float) $group->pooled_budget_cap,
            'global_deficit' => $this->globalDeficit($group),
            'events' => $events,
        ];
    }
}
