<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\VendorCategory;
use Illuminate\Support\Collection;

class GroupRollupService
{
    /**
     * Per-event contribution rows for the overview (EG-15), plus the combined
     * totals (EG-14) and a group-level health status (EG-16).
     *
     * @return array{
     *   combined_budget: float, combined_spent: float, combined_remaining: float,
     *   combined_percent: int, health: string, rows: Collection, category_breakdown: Collection
     * }
     */
    public function overview(EventGroup $group): array
    {
        $events = $group->events()->orderBy('event_date')->get();

        $rows = $events->map(fn (Event $e) => (object) [
            'event' => $e,
            'budget' => (float) $e->total_budget,
            'spent' => (float) $e->budget_spent,
            'remaining' => (float) $e->total_budget - (float) $e->budget_spent,
            'percent' => $e->budget_percent,
            'status' => Event::budgetStatusLabel($e->budget_percent),
        ]);

        $combinedBudget = $group->isPooled() && $group->pooled_budget_cap !== null
            ? (float) $group->pooled_budget_cap
            : (float) $rows->sum('budget');

        $combinedSpent = (float) $rows->sum('spent');
        $combinedRemaining = $combinedBudget - $combinedSpent;
        $combinedPercent = $combinedBudget > 0 ? (int) min(100, round(($combinedSpent / $combinedBudget) * 100)) : 0;

        return [
            'combined_budget' => $combinedBudget,
            'combined_spent' => $combinedSpent,
            'combined_remaining' => $combinedRemaining,
            'combined_percent' => $combinedPercent,
            'health' => Event::budgetStatusLabel($combinedPercent),
            'rows' => $rows,
            'category_breakdown' => $this->categoryBreakdown($events),
        ];
    }

    /**
     * EG-17 — combined spend by category across every sub-event, so a category
     * overspending in aggregate is visible even when no single event breached.
     */
    protected function categoryBreakdown(Collection $events): Collection
    {
        $categories = VendorCategory::whereIn('event_id', $events->pluck('id'))
            ->withSum('expenses as spent_amount', 'actual_cost')
            ->get();

        return $categories->groupBy('category_name')->map(function (Collection $rows, string $name) {
            return (object) [
                'category_name' => $name,
                'allocated' => (float) $rows->sum('allocated_amount'),
                'spent' => (float) $rows->sum('spent_amount'),
            ];
        })->sortByDesc('spent')->values();
    }

    /**
     * EG-3 / EG-55 — the group's date range is derived from its members and
     * recalculated on every membership or event-date change.
     */
    public function recalculateDates(EventGroup $group): void
    {
        $dates = $group->events()->pluck('event_date');

        $group->update([
            'start_date' => $dates->min(),
            'end_date' => $dates->max(),
        ]);
    }
}
