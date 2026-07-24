<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\VendorCategory;
use App\Services\BudgetReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrganizationDashboardController extends Controller
{
    public function __construct(protected BudgetReportService $reportService)
    {
    }

    public function index(Request $request): View
    {
        $userEvents = Auth::user()->events()->orderBy('event_name')->get();

        $period = in_array($request->input('period'), ['week', 'month'], true)
            ? $request->input('period')
            : 'all';

        if ($userEvents->isEmpty()) {
            return view('organization.index', [
                'userEvents' => $userEvents,
                'period' => $period,
                'reportGroups' => [],
            ]);
        }

        $reportGroups = $this->reportService->build($userEvents, 'all', $period);

        $userEventIds = $userEvents->pluck('id');
        $allExpenses = Expense::whereIn('event_id', $userEventIds)->with(['category', 'event'])->get();

        $periodBounds = $this->reportService->periodBounds($period);
        $periodExpenses = $periodBounds
            ? $allExpenses->filter(fn ($e) => $e->date_logged && $e->date_logged->between($periodBounds[0], $periodBounds[1]))
            : $allExpenses;

        foreach ($reportGroups as $group) {
            $this->augmentGroup($group, $userEvents, $allExpenses, $periodExpenses);
        }

        return view('organization.index', compact('userEvents', 'period', 'reportGroups'));
    }

    /**
     * Add the KPI/vendor/outstanding-payments/cash-flow figures that
     * BudgetReportService::build() doesn't already compute, directly onto
     * each currency-group object it returned.
     */
    protected function augmentGroup(object $group, $userEvents, $allExpenses, $periodExpenses): void
    {
        $eventIdsInGroup = $userEvents->where('currency', $group->currency)->pluck('id');
        $allInGroup = $allExpenses->whereIn('event_id', $eventIdsInGroup);
        $periodInGroup = $periodExpenses->whereIn('event_id', $eventIdsInGroup);

        // Paid/Pending KPI figures: "Paid" mirrors "Spent" (period-scoped),
        // "Pending"/"Partially Paid" mirror "Expected" (always all-time —
        // an outstanding bill doesn't shrink because you narrowed the date window).
        $group->paidCount = $periodInGroup->where('payment_status', 'Paid')->count();
        $group->paidSum = (float) $periodInGroup->where('payment_status', 'Paid')->sum('actual_cost');
        $group->pendingCount = $allInGroup->where('payment_status', 'Pending')->count();
        $group->pendingSum = (float) $allInGroup->where('payment_status', 'Pending')->sum('estimated_cost');
        $group->partiallyPaidCount = $allInGroup->where('payment_status', 'Partially Paid')->count();
        $group->overBudgetEventCount = $group->rows->where('status', 'over')->count();

        // Per-event forecast for the Budget vs Actual vs Forecast bar chart.
        foreach ($group->rows as $row) {
            $row->forecast = $row->spent + $row->expected;
        }

        // Top Vendors: no first-class Vendor entity yet, so this is grouped by
        // VendorCategory.vendor_name (category-level, not per-expense) as the
        // closest real data available.
        $categoriesInGroup = VendorCategory::whereIn('event_id', $eventIdsInGroup)
            ->whereNotNull('vendor_name')
            ->where('vendor_name', '!=', '')
            ->get();

        $group->topVendors = $categoriesInGroup->groupBy('vendor_name')
            ->map(function ($cats, $vendorName) use ($allInGroup) {
                $vendorExpenses = $allInGroup->whereIn('category_id', $cats->pluck('id'));
                $pending = (float) $vendorExpenses->where('payment_status', '!=', 'Paid')
                    ->sum(fn ($e) => max((float) $e->estimated_cost - (float) $e->actual_cost, 0));

                return (object) [
                    'vendor' => $vendorName,
                    'totalPaid' => (float) $vendorExpenses->where('payment_status', 'Paid')->sum('actual_cost'),
                    'pendingAmount' => $pending,
                    'contracts' => $cats->count(),
                    'outstandingBalance' => $pending,
                ];
            })
            ->sortByDesc('totalPaid')
            ->values()
            ->take(8);

        // Outstanding Payments: no due-date field exists yet, so items are
        // bucketed by how recently they were logged rather than when they're due.
        $outstanding = $allInGroup->where('payment_status', '!=', 'Paid')->sortByDesc('date_logged');
        $today = now()->startOfDay();
        $weekEnd = now()->endOfWeek();
        $monthEnd = now()->endOfMonth();

        $group->outstandingToday = $outstanding
            ->filter(fn ($e) => $e->date_logged && $e->date_logged->isSameDay($today))
            ->values();
        $group->outstandingThisWeek = $outstanding
            ->filter(fn ($e) => $e->date_logged && $e->date_logged->greaterThan($today) && $e->date_logged->lessThanOrEqualTo($weekEnd))
            ->values();
        $group->outstandingThisMonth = $outstanding
            ->filter(fn ($e) => $e->date_logged && $e->date_logged->greaterThan($weekEnd) && $e->date_logged->lessThanOrEqualTo($monthEnd))
            ->values();

        // Cash flow: actual spend per month for the last 6 months, plus a flat
        // total-budget reference line, across every event in this currency group.
        $cashFlowLabels = [];
        $cashFlowActual = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthStr = $monthStart->format('Y-m');
            $cashFlowLabels[] = $monthStart->format('M Y');
            $cashFlowActual[] = (float) $allInGroup
                ->filter(fn ($e) => $e->date_logged && $e->date_logged->format('Y-m') === $monthStr)
                ->sum('actual_cost');
        }
        $group->cashFlowLabels = $cashFlowLabels;
        $group->cashFlowActual = $cashFlowActual;
        $group->cashFlowBudgetLine = array_fill(0, 6, $group->budget);
    }
}
