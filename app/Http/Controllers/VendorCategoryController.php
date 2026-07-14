<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVendorCategoryRequest;
use App\Http\Requests\UpdateVendorCategoryRequest;
use App\Models\Event;
use App\Models\VendorCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VendorCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $userEvents = Auth::user()->events()->orderBy('event_date')->get();

        $selectedEvent = null;
        if ($userEvents->isNotEmpty()) {
            $selectedEventId = $request->integer('event') ?: $userEvents->first()->id;
            $selectedEvent = $userEvents->firstWhere('id', $selectedEventId) ?? $userEvents->first();
        }

        $categories = collect();
        if ($selectedEvent) {
            $categories = $selectedEvent->vendorCategories()
                ->withSum('expenses as spent_amount', 'actual_cost')
                ->orderBy('category_name')
                ->get()
                ->map(function (VendorCategory $category) {
                    $spent = (float) ($category->spent_amount ?? 0);
                    $allocated = (float) $category->allocated_amount;

                    $category->spent = $spent;
                    $category->remaining = $allocated - $spent;
                    $category->utilization = $allocated > 0 ? round(($spent / $allocated) * 100, 1) : 0;
                    $category->is_over_budget = $spent > $allocated;
                    $category->is_almost_depleted = ! $category->is_over_budget && $category->utilization >= 90;
                    $category->suggested_display = $this->formatPercent((float) $category->suggested_percentage);

                    return $category;
                });
        }

        $totalAllocated = (float) $categories->sum('allocated_amount');
        $totalSpent = (float) $categories->sum('spent');
        $totalSuggestedPercentage = (float) $categories->sum('suggested_percentage');
        $totalSuggestedDisplay = $this->formatPercent($totalSuggestedPercentage);

        return view('vendor-categories.index', compact(
            'userEvents',
            'selectedEvent',
            'categories',
            'totalAllocated',
            'totalSpent',
            'totalSuggestedPercentage',
            'totalSuggestedDisplay'
        ));
    }

    public function store(StoreVendorCategoryRequest $request): RedirectResponse
    {
        $event = Event::findOrFail($request->validated('event_id'));
        abort_unless($event->user_id === Auth::id(), 403);

        $data = $request->safe()->except('event_id');
        $data['is_locked'] = $request->boolean('is_locked');

        $event->vendorCategories()->create($data);

        return redirect()->route('vendor-categories.index', ['event' => $event->id])
            ->with('success', 'Vendor category added.');
    }

    public function update(UpdateVendorCategoryRequest $request, VendorCategory $vendor_category): RedirectResponse
    {
        $this->authorizeCategory($vendor_category);

        $data = $request->validated();
        $data['is_locked'] = $request->boolean('is_locked');

        $vendor_category->update($data);

        return redirect()->route('vendor-categories.index', ['event' => $vendor_category->event_id])
            ->with('success', 'Vendor category updated.');
    }

    public function destroy(VendorCategory $vendor_category): RedirectResponse
    {
        $this->authorizeCategory($vendor_category);

        $eventId = $vendor_category->event_id;
        $vendor_category->delete();

        return redirect()->route('vendor-categories.index', ['event' => $eventId])
            ->with('success', 'Vendor category deleted.');
    }

    public function toggleLock(VendorCategory $vendorCategory): JsonResponse
    {
        $this->authorizeCategory($vendorCategory);

        $vendorCategory->is_locked = ! $vendorCategory->is_locked;
        $vendorCategory->save();

        return response()->json(['is_locked' => $vendorCategory->is_locked]);
    }

    protected function authorizeCategory(VendorCategory $vendorCategory): void
    {
        abort_unless($vendorCategory->event->user_id === Auth::id(), 403);
    }

    protected function formatPercent(float $value): string
    {
        return $value == floor($value)
            ? number_format($value, 0)
            : rtrim(rtrim(number_format($value, 2), '0'), '.');
    }
}
