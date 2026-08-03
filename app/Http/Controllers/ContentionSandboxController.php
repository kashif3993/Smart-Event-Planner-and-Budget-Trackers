<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventGroup;
use App\Services\ContentionDetectionService;
use App\Services\StrictHierarchyScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ContentionSandboxController extends Controller
{
    /**
     * CR-20…CR-25 — an immutable snapshot of every sub-event in contention,
     * taken at request time. The sandbox holds onto this client-side for the
     * whole session, so figures can't shift beneath the user mid-review.
     */
    public function snapshot(EventGroup $group, ContentionDetectionService $contention, StrictHierarchyScoringService $scoring): JsonResponse
    {
        $this->authorizeGroup($group);

        if (! $group->isPooled()) {
            return response()->json(['success' => false, 'message' => 'This group is not in Pooled mode.'], 422);
        }

        if (! $contention->isContended($group)) {
            return response()->json(['success' => false, 'message' => 'This group is not currently in contention.'], 422);
        }

        $data = $contention->snapshot($group);

        $events = $data['events']->map(function ($row) use ($group, $scoring) {
            $event = $group->events->firstWhere('id', $row->id);

            return [
                'id' => $event->id,
                'name' => $event->event_name,
                'type' => $event->event_type,
                'date' => $event->event_date->toDateString(),
                'days_remaining' => max(1, $event->days_remaining),
                'weight' => $scoring->weight($event),
                'total_budget' => (float) $event->total_budget,
                'budget_spent' => (float) $event->budget_spent,
                'overrun' => max(0, (float) $event->budget_spent - (float) $event->total_budget),
                'headroom' => max(0, (float) $event->total_budget - (float) $event->budget_spent),
                'is_contributing' => $row->is_contributing,
                'categories' => $this->categoriesFor($event),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'pooled_budget_cap' => $data['pooled_budget_cap'],
            'global_deficit' => $data['global_deficit'],
            'currency_symbol' => $group->currencySymbol(),
            'events' => $events,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function categoriesFor(Event $event): array
    {
        return $event->vendorCategories()
            ->withSum('expenses as spent_amount', 'actual_cost')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'category_name' => $c->category_name,
                'allocated_amount' => (float) $c->allocated_amount,
                'spent' => (float) ($c->spent_amount ?? 0),
            ])
            ->values()
            ->all();
    }

    protected function authorizeGroup(EventGroup $group): void
    {
        abort_unless($group->user_id === Auth::id(), 403);
    }
}
