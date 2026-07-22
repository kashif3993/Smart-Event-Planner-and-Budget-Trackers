<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Event;
use App\Models\VendorCategory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // All event IDs belonging to this user
        $userEventIds = $user->events()->pluck('id');

        $expenses = $this->filteredExpensesQuery($request, $userEventIds)
            ->orderBy('date_logged', 'desc')
            ->paginate(10);

        // Summary totals
        $allExpenses = Expense::whereIn('event_id', $userEventIds);
        $totalExpenses   = (clone $allExpenses)->sum('actual_cost');
        $pendingApproval = (clone $allExpenses)->where('payment_status', 'Pending')->sum('estimated_cost');
        $pendingCount    = (clone $allExpenses)->where('payment_status', 'Pending')->count();

        // Budget from user's events
        $totalBudget     = $user->events()->sum('total_budget');
        $remainingBudget = max($totalBudget - $totalExpenses, 0);
        $budgetPercentage = $totalBudget > 0 ? min(($totalExpenses / $totalBudget) * 100, 100) : 0;

        // Category breakdown for doughnut chart
        $categories = Expense::whereIn('expenses.event_id', $userEventIds)
            ->join('vendor_categories', 'expenses.category_id', '=', 'vendor_categories.id')
            ->selectRaw('vendor_categories.category_name as category, sum(expenses.actual_cost) as total')
            ->groupBy('vendor_categories.category_name')
            ->pluck('total', 'category')
            ->toArray();

        // Monthly spend: last 4 months actual + next 2 months projected
        $fourMonthsAgo  = now()->subMonths(3)->startOfMonth();
        $recentExpenses = Expense::whereIn('event_id', $userEventIds)
            ->where('date_logged', '>=', $fourMonthsAgo)
            ->get();

        $months        = [];
        $monthlyData   = [];
        $monthProjected = [];
        for ($i = 3; $i >= 0; $i--) {
            $monthStr      = now()->subMonths($i)->format('Y-m');
            $months[]      = now()->subMonths($i)->format('M');
            $monthlyData[] = $recentExpenses->filter(function ($e) use ($monthStr) {
                return $e->date_logged && $e->date_logged->format('Y-m') === $monthStr;
            })->sum('actual_cost');
            $monthProjected[] = false;
        }

        // Project the next 2 months from the average of months with actual spend
        $monthsWithSpend = array_filter($monthlyData, fn ($v) => $v > 0);
        $avgSpend = count($monthsWithSpend) > 0 ? array_sum($monthsWithSpend) / count($monthsWithSpend) : 0;
        for ($i = 1; $i <= 2; $i++) {
            $months[]        = now()->addMonths($i)->format('M');
            $monthlyData[]    = round($avgSpend, 2);
            $monthProjected[] = true;
        }

        // Events list for "Add Expense" modal dropdown
        $userEvents = $user->events()->orderBy('event_name')->get();

        // Vendor categories for the selected event (populated via JS)
        $vendorCategories = VendorCategory::whereIn('event_id', $userEventIds)->get();

        // vs last month text
        $lastMonthSpend = Expense::whereIn('event_id', $userEventIds)
            ->whereMonth('date_logged', now()->subMonth()->month)
            ->whereYear('date_logged', now()->subMonth()->year)
            ->sum('actual_cost');
        $thisMonthSpend = Expense::whereIn('event_id', $userEventIds)
            ->whereMonth('date_logged', now()->month)
            ->whereYear('date_logged', now()->year)
            ->sum('actual_cost');
        $vsLastMonth = $lastMonthSpend > 0
            ? round((($thisMonthSpend - $lastMonthSpend) / $lastMonthSpend) * 100, 1)
            : ($thisMonthSpend > 0 ? 100 : 0);

        // Budget & Spend Report — "all events combined" or a single selected event,
        // and how far back "Spent" looks (all time / this month / this week).
        $reportScope = $request->filled('report_scope') && $request->report_scope !== 'all'
            ? (int) $request->report_scope
            : 'all';
        $reportPeriod = in_array($request->input('report_period'), ['week', 'month'], true)
            ? $request->input('report_period')
            : 'all';

        $reportGroups = $this->buildBudgetReport($userEvents, $reportScope, $reportPeriod);

        return view('expenses.index', compact(
            'expenses',
            'totalExpenses',
            'pendingApproval',
            'pendingCount',
            'remainingBudget',
            'budgetPercentage',
            'categories',
            'months',
            'monthlyData',
            'monthProjected',
            'totalBudget',
            'userEvents',
            'vendorCategories',
            'vsLastMonth',
            'reportScope',
            'reportPeriod',
            'reportGroups'
        ));
    }

    public function exportPdf(Request $request)
    {
        $user = auth()->user();
        $userEventIds = $user->events()->pluck('id');
        $userEvents = $user->events()->orderBy('event_name')->get();

        $expenses = $this->filteredExpensesQuery($request, $userEventIds)
            ->orderBy('date_logged', 'desc')
            ->get();

        $reportScope = $request->filled('report_scope') && $request->report_scope !== 'all'
            ? (int) $request->report_scope
            : 'all';
        $reportPeriod = in_array($request->input('report_period'), ['week', 'month'], true)
            ? $request->input('report_period')
            : 'all';

        $reportGroups = $this->buildBudgetReport($userEvents, $reportScope, $reportPeriod);

        $scopeEvent = $reportScope !== 'all' ? $userEvents->firstWhere('id', $reportScope) : null;

        $appliedFilters = array_filter([
            'Event' => $request->filled('event') ? optional($userEvents->firstWhere('id', $request->event))->event_name : null,
            'Category' => $request->filled('category') ? $request->category : null,
            'Status' => $request->filled('status') ? $request->status : null,
            'Date Logged' => $request->filled('date') ? $request->date : null,
        ]);

        $pdf = Pdf::loadView('expenses.exports.pdf', [
            'expenses' => $expenses,
            'reportGroups' => $reportGroups,
            'scopeEvent' => $scopeEvent,
            'appliedFilters' => $appliedFilters,
            'reportPeriod' => $reportPeriod,
        ])->setPaper('a4');

        $fileName = 'expense-report-'.($scopeEvent ? Str::slug($scopeEvent->event_name).'-' : 'all-events-').now()->format('Y-m-d').'.pdf';

        return $pdf->download($fileName);
    }

    /**
     * Expenses scoped to the user's events, with the Expenses page's
     * event/category/status/date filters applied. Shared by the paginated
     * index() list and the unpaginated PDF export so both always agree.
     */
    protected function filteredExpensesQuery(Request $request, $userEventIds)
    {
        $query = Expense::whereIn('event_id', $userEventIds)->with(['event', 'category']);

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('category_name', $request->category));
        }

        if ($request->filled('status')) {
            $query->where('payment_status', $request->status);
        }

        if ($request->filled('date')) {
            $query->whereDate('date_logged', $request->date);
        }

        if ($request->filled('event')) {
            $query->where('event_id', $request->event);
        }

        return $query;
    }

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
    protected function buildBudgetReport($userEvents, string|int $scope, string $period = 'all'): array
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
    protected function periodBounds(string $period): ?array
    {
        return match ($period) {
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => null,
        };
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id'       => 'required|exists:events,id',
            'category_id'    => 'required|exists:vendor_categories,id',
            'vendor_item_name' => 'required|string|max:255',
            'estimated_cost' => 'required|numeric|min:0',
            'actual_cost'    => 'required|numeric|min:0',
            'payment_status' => 'required|in:Paid,Pending,Partially Paid',
            'date_logged'    => 'nullable|date',
            'notes'          => 'nullable|string',
        ]);

        // Make sure the event belongs to this user
        $event = Event::findOrFail($validated['event_id']);
        abort_unless($event->user_id === auth()->id(), 403);

        Expense::create($validated);

        return redirect()->back()->with('success', 'Expense added successfully.');
    }

    public function update(Request $request, Expense $expense)
    {
        abort_unless($expense->event->user_id === auth()->id(), 403);

        $validated = $request->validate([
            'event_id'        => 'required|exists:events,id',
            'category_id'     => 'required|exists:vendor_categories,id',
            'vendor_item_name'=> 'required|string|max:255',
            'estimated_cost'  => 'required|numeric|min:0',
            'actual_cost'     => 'required|numeric|min:0',
            'payment_status'  => 'required|in:Paid,Pending,Partially Paid',
            'date_logged'     => 'nullable|date',
            'notes'           => 'nullable|string',
        ]);

        $expense->update($validated);

        return redirect()->back()->with('success', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense)
    {
        abort_unless($expense->event->user_id === auth()->id(), 403);
        $expense->delete();

        return redirect()->back()->with('success', 'Expense deleted successfully.');
    }

    /**
     * Return vendor categories for a given event (called via AJAX in modal)
     */
    public function categoriesByEvent(Event $event)
    {
        abort_unless($event->user_id === auth()->id(), 403);

        return response()->json(
            $event->vendorCategories()->select('id', 'category_name', 'vendor_name')->get()
        );
    }
}
