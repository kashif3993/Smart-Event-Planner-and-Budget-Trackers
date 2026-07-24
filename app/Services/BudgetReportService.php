<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\VendorCategory;

class BudgetReportService
{
    /**
     * Build the Budget & Spend Report: budget vs. money already spent vs.
     * money still expected to go out on items that are logged but not yet
     * fully paid, either across all of the user's events or for one event.
     *
     * Grouped by currency (rather than a single blended total) so events in
     * different currencies never get summed together into a meaningless number.
     *
     * "Spent" is scoped to $period (all time / this month / this week — see
     * periodBounds()), but "Expected" always looks at everything outstanding
     * regardless of period: what you still owe doesn't shrink just because
     * you narrowed the date window, and "Budget" is a fixed total that has no
     * time dimension at all.
     *
     * @return array<int, object>
     */
    public function build($userEvents, string|int $scope, string $period = 'all'): array
    {
        $reportEvents = $scope === 'all'
            ? $userEvents
            : $userEvents->where('id', $scope)->take(1);

        if ($reportEvents->isEmpty()) {
            return [];
        }

        $reportEventIds = $reportEvents->pluck('id');
        $allExpenses = Expense::whereIn('event_id', $reportEventIds)->with('category')->get();

        $periodBounds = $this->periodBounds($period);
        $periodExpenses = $periodBounds
            ? $allExpenses->filter(fn ($e) => $e->date_logged && $e->date_logged->between($periodBounds[0], $periodBounds[1]))
            : $allExpenses;

        $expectedFor = fn ($expenses) => (float) $expenses
            ->where('payment_status', '!=', 'Paid')
            ->sum(fn ($e) => max((float) $e->estimated_cost - (float) $e->actual_cost, 0));

        $statusFor = function (float $budget, float $spent, float $expected) {
            if ($budget <= 0) return 'no-budget';
            if ($spent > $budget) return 'over';
            if ($spent + $expected > $budget) return 'at-risk';
            if ($budget > 0 && ($spent / $budget) * 100 >= 90) return 'watch';
            return 'on-track';
        };

        $buildRow = function (string $label, ?string $sublabel, float $rowBudget, $rowAllExpenses, $rowPeriodExpenses) use ($expectedFor, $statusFor) {
            $rowSpent = (float) $rowPeriodExpenses->sum('actual_cost');
            $rowExpected = $expectedFor($rowAllExpenses);

            return (object) [
                'label' => $label,
                'sublabel' => $sublabel,
                'budget' => $rowBudget,
                'spent' => $rowSpent,
                'expected' => $rowExpected,
                'remaining' => $rowBudget - $rowSpent - $rowExpected,
                'utilization' => $rowBudget > 0 ? min(($rowSpent / $rowBudget) * 100, 999) : 0,
                'status' => $statusFor($rowBudget, $rowSpent, $rowExpected),
            ];
        };

        $singleEvent = $scope !== 'all' ? $reportEvents->first() : null;

        return $reportEvents->groupBy('currency')
            ->map(function ($eventsInCurrency) use ($allExpenses, $periodExpenses, $expectedFor, $statusFor, $buildRow, $singleEvent) {
                $currency = $eventsInCurrency->first()->currency;
                $symbol = $eventsInCurrency->first()->currencySymbol();
                $eventIdsInGroup = $eventsInCurrency->pluck('id');

                $allInGroup = $allExpenses->whereIn('event_id', $eventIdsInGroup);
                $periodInGroup = $periodExpenses->whereIn('event_id', $eventIdsInGroup);

                $budget = (float) $eventsInCurrency->sum('total_budget');
                $spent = (float) $periodInGroup->sum('actual_cost');
                $expected = $expectedFor($allInGroup);
                $remaining = $budget - $spent - $expected;
                $projectedTotal = $spent + $expected;
                $utilization = $budget > 0 ? min(($spent / $budget) * 100, 999) : 0;
                $projectedUtilization = $budget > 0 ? min(($projectedTotal / $budget) * 100, 999) : 0;

                // Category breakdown: single-event scope uses that event's own
                // categories; all-events scope aggregates categories by name
                // across every event (so two events' "Catering" categories
                // roll up into one row instead of showing separately).
                if ($singleEvent) {
                    $categoryRows = $singleEvent->vendorCategories->map(fn ($category) => $buildRow(
                        $category->category_name,
                        $category->vendor_name,
                        (float) $category->allocated_amount,
                        $allInGroup->where('category_id', $category->id),
                        $periodInGroup->where('category_id', $category->id)
                    ))->sortByDesc('spent')->values();
                } else {
                    $categoriesInGroup = VendorCategory::whereIn('event_id', $eventIdsInGroup)->get();
                    $categoryRows = $categoriesInGroup->groupBy('category_name')
                        ->map(function ($catsForName, $name) use ($allInGroup, $periodInGroup, $buildRow) {
                            $catIds = $catsForName->pluck('id');

                            return $buildRow(
                                $name,
                                null,
                                (float) $catsForName->sum('allocated_amount'),
                                $allInGroup->whereIn('category_id', $catIds),
                                $periodInGroup->whereIn('category_id', $catIds)
                            );
                        })->sortByDesc('spent')->values();
                }

                // Event breakdown: only meaningful when looking at all events at once.
                $eventRows = $singleEvent ? collect() : $eventsInCurrency->map(fn ($event) => $buildRow(
                    $event->event_name,
                    null,
                    (float) $event->total_budget,
                    $allInGroup->where('event_id', $event->id),
                    $periodInGroup->where('event_id', $event->id)
                ))->sortByDesc('spent')->values();

                // Itemized line-by-line list: only meaningful for a single
                // selected event, scoped to the same period as "Spent" above.
                $expenseItems = $singleEvent ? $periodInGroup->sortByDesc('date_logged')->values() : collect();

                $alerts = $categoryRows->concat($eventRows)
                    ->whereIn('status', ['over', 'at-risk'])
                    ->map(fn ($row) => (object) [
                        'label' => $row->label,
                        'status' => $row->status,
                        'overBy' => $row->status === 'over'
                            ? $row->spent - $row->budget
                            : ($row->spent + $row->expected) - $row->budget,
                    ])->values();

                return (object) [
                    'currency' => $currency,
                    'symbol' => $symbol,
                    'eventCount' => $eventsInCurrency->count(),
                    'budget' => $budget,
                    'spent' => $spent,
                    'expected' => $expected,
                    'remaining' => $remaining,
                    'projectedTotal' => $projectedTotal,
                    'utilization' => $utilization,
                    'projectedUtilization' => $projectedUtilization,
                    'status' => $statusFor($budget, $spent, $expected),
                    'detailLabel' => $singleEvent ? 'Category' : 'Event',
                    'rows' => $singleEvent ? $categoryRows : $eventRows,
                    'categoryRows' => $categoryRows,
                    'expenseItems' => $expenseItems,
                    'alerts' => $alerts,
                ];
            })->values()->all();
    }

    /**
     * Date bounds for the report's "Spent" period selector.
     * null means no bound — all time, till date.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}|null
     */
    public function periodBounds(string $period): ?array
    {
        return match ($period) {
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => null,
        };
    }
}
