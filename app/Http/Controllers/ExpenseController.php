<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Event;
use App\Models\VendorCategory;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // All event IDs belonging to this user
        $userEventIds = $user->events()->pluck('id');

        // Start query — expenses scoped to user's events
        $query = Expense::whereIn('event_id', $userEventIds)->with(['event', 'category']);

        // Filters
        if ($request->filled('category')) {
            $query->whereHas('category', fn($q) => $q->where('category_name', $request->category));
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

        $expenses = $query->orderBy('date_logged', 'desc')->paginate(10);

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
            'vsLastMonth'
        ));
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
            $event->vendorCategories()->select('id', 'category_name')->get()
        );
    }
}
