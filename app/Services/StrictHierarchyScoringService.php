<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Collection;

class StrictHierarchyScoringService
{
    /**
     * CR-40 — Event Weight reflects commitment class. Wedding/Corporate carry
     * the highest weight (typically contracted, unrecoverable commitments);
     * Birthday/Baby Shower/Graduation/Custom are lighter commitments.
     */
    protected const WEIGHTS = [
        'Wedding' => 3,
        'Corporate Event' => 3,
        'Birthday Party' => 1,
        'Baby Shower' => 1,
        'Graduation' => 1,
        'Custom' => 1,
    ];

    public function weight(Event $event): int
    {
        return self::WEIGHTS[$event->event_type] ?? 1;
    }

    /**
     * CR-40 — Priority Score = Event Weight ÷ Days Remaining. Runway is
     * floored at 1 day (CR — zero/negative runway scores as maximally
     * protected rather than producing an undefined score).
     */
    public function score(Event $event): float
    {
        return $this->weight($event) / max(1, $event->days_remaining);
    }

    /**
     * CR-41/CR-43/CR-45 — ranks eligible (non-immune) events ascending by
     * score (lowest concedes first), ties broken by later event date.
     *
     * @param  Collection<int, Event>  $events
     * @param  array<int>  $immuneIds
     * @return Collection<int, array{event: Event, score: float, rank: int}>
     */
    public function rank(Collection $events, array $immuneIds = []): Collection
    {
        return $events
            ->reject(fn (Event $e) => in_array($e->id, $immuneIds, true))
            ->map(fn (Event $e) => ['event' => $e, 'score' => $this->score($e)])
            ->sortBy([
                ['score', 'asc'],
                fn ($a, $b) => $b['event']->event_date <=> $a['event']->event_date,
            ])
            ->values()
            ->map(function ($row, $index) {
                $row['rank'] = $index + 1;

                return $row;
            });
    }

    /**
     * CR-41/CR-42 — the lowest-priority event absorbs the deficit first, up
     * to the limit of what it can reasonably surrender (its headroom above
     * already-incurred spend); any remainder cascades to the next-lowest.
     *
     * @param  Collection<int, Event>  $events
     * @param  array<int>  $immuneIds
     * @return array{concessions: array<int, array{event: Event, score: float, rank: int, concession: float}>, residual: float}
     */
    public function cascadeConcessions(Collection $events, float $deficit, array $immuneIds = []): array
    {
        $ranked = $this->rank($events, $immuneIds);
        $remaining = $deficit;
        $concessions = [];

        foreach ($ranked as $row) {
            $event = $row['event'];
            $headroom = max(0, (float) $event->total_budget - (float) $event->budget_spent);
            $take = min($headroom, $remaining);

            $concessions[] = [...$row, 'concession' => round($take, 2)];
            $remaining -= $take;

            if ($remaining <= 0) {
                break;
            }
        }

        return [
            'concessions' => $concessions,
            'residual' => max(0, round($remaining, 2)),
        ];
    }
}
