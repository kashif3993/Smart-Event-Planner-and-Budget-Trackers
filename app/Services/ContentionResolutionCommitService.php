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
     * FR-51/FR-67 — events the user locked immune never appear in
     * $concessions (the sandbox excludes them from the proposal entirely),
     * but the audit record still needs to show they were part of the
     * negotiation and were explicitly protected — otherwise history quietly
     * omits them, which is exactly the kind of incomplete record section
     * 10.1 (trust and transparency) rules out. $immuneEventIds carries the
     * user's actual lock choices so that flag reflects what was truly
     * decided, not a guess inferred from a concession happening to be zero.
     *
     * @param  array<int, array{event_id: int, concession_amount: float, rationale: string}>  $concessions
     * @param  array<int>  $immuneEventIds
     */
    public function commit(EventGroup $group, array $concessions, string $strategy, bool $aiUsed, ?string $fallbackReason, bool $manuallyAmended = false, array $immuneEventIds = []): ContentionResolution
    {
        $globalDeficit = $this->contention->globalDeficit($group);

        return DB::transaction(function () use ($group, $concessions, $strategy, $aiUsed, $fallbackReason, $manuallyAmended, $globalDeficit, $immuneEventIds) {
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
                    'immune' => false,
                    'before_total_budget' => $before,
                    'after_total_budget' => round($newBudget, 2),
                    'concession_amount' => $concessionAmount,
                    'rationale' => $row['rationale'] ?? '',
                ];
            }

            foreach ($immuneEventIds as $immuneId) {
                if (in_array($immuneId, $participatingIds, true)) {
                    continue;
                }

                $event = $group->events()->find($immuneId);
                if (! $event) {
                    continue;
                }

                $participatingIds[] = $event->id;
                $concessionRecords[] = [
                    'event_id' => $event->id,
                    'event_name' => $event->event_name,
                    'immune' => true,
                    'before_total_budget' => (float) $event->total_budget,
                    'after_total_budget' => (float) $event->total_budget,
                    'concession_amount' => 0.0,
                    'rationale' => 'Locked immune by the user — protected from any concession.',
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

        // Every category but the last takes its proportional share, rounded;
        // the last absorbs whatever's left over. Rounding each share
        // independently can leave the cuts a cent short (or over) of
        // $reductionNeeded, drifting the category total away from the
        // event's new budget — assigning the remainder last keeps them
        // summing to exactly $reductionNeeded (capped by each category's own
        // headroom, so a category is still never cut below its spend floor).
        $flexible = $flexible->values();
        $lastIndex = $flexible->count() - 1;
        $allocated = 0.0;

        $flexible->each(function (VendorCategory $category, int $index) use (&$allocated, $flexibleHeadroom, $reductionNeeded, $lastIndex) {
            $headroom = (float) $category->allocated_amount - (float) ($category->spent_amount ?? 0);

            if ($index === $lastIndex) {
                $cut = min($headroom, max(0, round($reductionNeeded - $allocated, 2)));
            } else {
                $share = $headroom / $flexibleHeadroom;
                $cut = min($headroom, round($reductionNeeded * $share, 2));
            }

            $allocated += $cut;
            $category->update(['allocated_amount' => round((float) $category->allocated_amount - $cut, 2)]);
        });
    }
}
