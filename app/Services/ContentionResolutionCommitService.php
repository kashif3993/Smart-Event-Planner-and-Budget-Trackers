<?php

namespace App\Services;

use App\Models\ContentionResolution;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\VendorCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContentionResolutionCommitService
{
    public function __construct(protected ContentionDetectionService $contention)
    {
    }

    /**
     * CR-64…CR-71 — apply an approved reallocation across every affected
     * sub-event, all-or-nothing (CR-65/CR-70), reducing each event's budget
     * by its concession and its categories proportionally (CR-66), then
     * writes a full audit record to the group's history (CR-69).
     *
     * @param  array<int, array{event_id: int, concession_amount: float, rationale: string}>  $concessions
     */
    public function commit(EventGroup $group, array $concessions, string $strategy, bool $aiUsed, ?string $fallbackReason, bool $manuallyAmended = false): ContentionResolution
    {
        $globalDeficit = $this->contention->globalDeficit($group);

        return DB::transaction(function () use ($group, $concessions, $strategy, $aiUsed, $fallbackReason, $manuallyAmended, $globalDeficit) {
            $participatingIds = [];
            $concessionRecords = [];

            foreach ($concessions as $row) {
                $event = $group->events()->find($row['event_id']);

                if (! $event) {
                    throw ValidationException::withMessages(['concessions' => 'One of the events in this proposal no longer belongs to this group. Refresh and try again.']);
                }

                $concessionAmount = round((float) $row['concession_amount'], 2);
                $before = (float) $event->total_budget;
                $newBudget = $before - $concessionAmount;

                // CR-62 — a concession may never drive a budget below what's
                // already been spent, no matter which strategy produced it.
                if ($newBudget < (float) $event->budget_spent) {
                    throw ValidationException::withMessages([
                        'concessions' => "\"{$event->event_name}\"'s concession would drive its budget below what's already been spent.",
                    ]);
                }

                $event->update(['total_budget' => round($newBudget, 2)]);
                $this->reduceCategoriesProportionally($event, $newBudget);

                $participatingIds[] = $event->id;
                $concessionRecords[] = [
                    'event_id' => $event->id,
                    'event_name' => $event->event_name,
                    'immune' => $concessionAmount <= 0,
                    'before_total_budget' => $before,
                    'after_total_budget' => round($newBudget, 2),
                    'concession_amount' => $concessionAmount,
                    'rationale' => $row['rationale'] ?? '',
                ];
            }

            return ContentionResolution::create([
                'event_group_id' => $group->id,
                'user_id' => $group->user_id,
                'strategy' => $strategy,
                'pooled_budget_cap' => (float) $group->pooled_budget_cap,
                'global_deficit' => $globalDeficit,
                'ai_used' => $aiUsed,
                'fallback_reason' => $fallbackReason,
                'participating_event_ids' => $participatingIds,
                'concessions' => $concessionRecords,
            ]);
        });
    }

    /**
     * CR-66 — an event's internal categories are reduced proportionally to
     * fit its new (lower) total budget, never below what a category has
     * already spent.
     */
    protected function reduceCategoriesProportionally(Event $event, float $newBudget): void
    {
        $categories = $event->vendorCategories()->withSum('expenses as spent_amount', 'actual_cost')->get();
        $totalAllocated = (float) $categories->sum('allocated_amount');

        if ($totalAllocated <= 0 || $totalAllocated <= $newBudget) {
            return;
        }

        $reductionNeeded = $totalAllocated - $newBudget;

        // Flexible categories can absorb a cut down to their own spend floor;
        // categories already at (or below) their spend floor are untouched.
        $flexible = $categories->filter(fn (VendorCategory $c) => (float) $c->allocated_amount > (float) ($c->spent_amount ?? 0));
        $flexibleHeadroom = $flexible->sum(fn (VendorCategory $c) => (float) $c->allocated_amount - (float) ($c->spent_amount ?? 0));

        if ($flexibleHeadroom <= 0) {
            return;
        }

        foreach ($flexible as $category) {
            $headroom = (float) $category->allocated_amount - (float) ($category->spent_amount ?? 0);
            $share = $headroom / $flexibleHeadroom;
            $cut = min($headroom, round($reductionNeeded * $share, 2));

            $category->update(['allocated_amount' => round((float) $category->allocated_amount - $cut, 2)]);
        }
    }
}
