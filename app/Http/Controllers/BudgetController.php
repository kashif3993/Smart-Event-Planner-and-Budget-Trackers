<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\VendorCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function index(Request $request): View
    {
        $userEvents = Auth::user()->events()->orderBy('event_date')->get();

        $selectedEvent = null;
        if ($userEvents->isNotEmpty()) {
            $selectedEventId = $request->integer('event') ?: $userEvents->first()->id;
            $selectedEvent = $userEvents->firstWhere('id', $selectedEventId) ?? $userEvents->first();
        }

        if (! $selectedEvent) {
            return view('budget.index', [
                'userEvents' => $userEvents,
                'selectedEvent' => null,
            ]);
        }

        $days = (int) $request->integer('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        $categories = $this->categoriesWithSpend($selectedEvent);

        $totalBudget = (float) $selectedEvent->total_budget;
        $totalSpent = (float) $categories->sum('spent');
        $remainingBudget = $totalBudget - $totalSpent;
        $healthPercentRaw = $totalBudget > 0 ? ($totalSpent / $totalBudget) * 100 : 0;
        $healthPercent = min(round($healthPercentRaw), 100);

        $healthStatus = Event::budgetStatusLabel($healthPercentRaw);
        $healthChipClass = match ($healthStatus) {
            'Over Budget' => 'chip-danger',
            'On Track' => 'chip-warning',
            default => 'chip-success',
        };

        $topCategory = $categories->sortByDesc('allocated_amount')->first();

        // Spend velocity: this month vs last month, scoped to this event
        $lastMonthSpend = (float) $selectedEvent->expenses()
            ->whereMonth('date_logged', now()->subMonth()->month)
            ->whereYear('date_logged', now()->subMonth()->year)
            ->sum('actual_cost');
        $thisMonthSpend = (float) $selectedEvent->expenses()
            ->whereMonth('date_logged', now()->month)
            ->whereYear('date_logged', now()->year)
            ->sum('actual_cost');
        $velocityPercent = $lastMonthSpend > 0
            ? round((($thisMonthSpend - $lastMonthSpend) / $lastMonthSpend) * 100, 1)
            : ($thisMonthSpend > 0 ? 100 : 0);
        $isHighVelocity = $velocityPercent >= 15;

        // Spending trend: daily actual spend for the selected window
        $trendStart = now()->subDays($days - 1)->startOfDay();
        $trendExpenses = $selectedEvent->expenses()
            ->where('date_logged', '>=', $trendStart)
            ->get()
            ->groupBy(fn ($e) => optional($e->date_logged)->format('Y-m-d'));

        $trendLabels = [];
        $trendData = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $key = $day->format('Y-m-d');
            $trendLabels[] = $day->format('M d');
            $trendData[] = (float) ($trendExpenses->get($key)?->sum('actual_cost') ?? 0);
        }

        // Alerts: over-budget categories (critical) + near-limit categories (upcoming)
        $alerts = $categories
            ->filter(fn ($c) => $c->is_over_budget || $c->is_almost_depleted)
            ->sortByDesc(fn ($c) => $c->is_over_budget ? 1 : 0)
            ->map(function (VendorCategory $c) use ($selectedEvent) {
                if ($c->is_over_budget) {
                    return [
                        'level' => 'Critical',
                        'title' => $c->category_name.' Over-spend',
                        'message' => 'Spending in this category exceeded its allocation by '
                            .$selectedEvent->currencySymbol().number_format($c->spent - $c->allocated_amount, 0).'.',
                        'category_id' => $c->id,
                    ];
                }

                return [
                    'level' => 'Upcoming',
                    'title' => $c->category_name.' Limit',
                    'message' => 'This category is at '.number_format($c->utilization, 0).'% of its allocated budget.',
                    'category_id' => $c->id,
                ];
            })
            ->values();

        return view('budget.index', compact(
            'userEvents',
            'selectedEvent',
            'categories',
            'totalBudget',
            'totalSpent',
            'remainingBudget',
            'healthPercent',
            'healthStatus',
            'healthChipClass',
            'topCategory',
            'velocityPercent',
            'isHighVelocity',
            'trendLabels',
            'trendData',
            'days',
            'alerts'
        ));
    }

    public function export(Request $request)
    {
        $userEvents = Auth::user()->events()->pluck('id');
        $eventId = $request->integer('event');

        $event = Event::findOrFail($eventId);
        abort_unless($userEvents->contains($event->id), 403);

        $expenses = $event->expenses()->with('category')->orderBy('date_logged')->get();

        $filename = 'budget-report-'.Str::slug($event->event_name).'.csv';

        return response()->streamDownload(function () use ($expenses) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Vendor / Item', 'Category', 'Estimated Cost', 'Actual Cost', 'Status', 'Date Logged', 'Notes']);

            foreach ($expenses as $expense) {
                fputcsv($handle, [
                    $expense->vendor_item_name,
                    $expense->category->category_name ?? '—',
                    $expense->estimated_cost,
                    $expense->actual_cost,
                    $expense->payment_status,
                    optional($expense->date_logged)->format('Y-m-d'),
                    $expense->notes,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function categoriesWithSpend(Event $event)
    {
        return $event->vendorCategories()
            ->withSum('expenses as spent_amount', 'actual_cost')
            ->withCount(['expenses as paid_expense_count' => fn ($q) => $q->where('payment_status', 'Paid')])
            ->orderByDesc('allocated_amount')
            ->get()
            ->map(function (VendorCategory $category) {
                $spent = (float) ($category->spent_amount ?? 0);
                $allocated = (float) $category->allocated_amount;

                $category->spent = $spent;
                $category->remaining = $allocated - $spent;
                $category->utilization = $allocated > 0 ? round(($spent / $allocated) * 100, 1) : 0;
                $category->is_over_budget = $spent > $allocated;
                $category->is_almost_depleted = ! $category->is_over_budget && $category->utilization >= 90;
                $category->has_paid_expense = $category->paid_expense_count > 0;

                return $category;
            });
    }
}
