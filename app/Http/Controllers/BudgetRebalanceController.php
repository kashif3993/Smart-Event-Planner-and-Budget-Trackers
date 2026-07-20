<?php

namespace App\Http\Controllers;

use App\Exceptions\AiBudgetRebalancerNotConfiguredException;
use App\Models\Event;
use App\Services\BudgetRebalancerService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BudgetRebalanceController extends Controller
{
    /**
     * The "Rebalancer Sandbox" preview — computes proposed allocations for
     * the given strategy without writing anything to the database.
     */
    public function preview(Request $request, Event $event, BudgetRebalancerService $service): JsonResponse
    {
        $this->authorizeEvent($event);

        $validated = $request->validate([
            'locked_category_ids' => ['array'],
            'locked_category_ids.*' => ['integer'],
            'strategy' => ['required', 'string', 'in:proportional,targeted,ai'],
        ]);

        try {
            $categories = $service->buildPreview(
                $event,
                $validated['locked_category_ids'] ?? [],
                $validated['strategy']
            );
        } catch (AiBudgetRebalancerNotConfiguredException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (RequestException $e) {
            report($e);
            $response = $e->response->json();
            $apiMessage = $response['error']['message'] ?? 'AI rebalance suggestion failed (API Error).';

            return response()->json(['success' => false, 'message' => 'Google API Error: '.$apiMessage], 502);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Could not build a rebalance proposal. Please try again.'], 502);
        }

        return response()->json(['success' => true, 'categories' => $categories]);
    }

    /**
     * Permanently overwrites the event's budget allocation with the
     * user-committed distribution plan from the sandbox.
     */
    public function commit(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($event);

        $validated = $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*.id' => ['required', 'integer'],
            'categories.*.allocated_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $totalBudget = (float) $event->total_budget;

        foreach ($validated['categories'] as $row) {
            $category = $event->vendorCategories()->find($row['id']);

            if (! $category) {
                continue;
            }

            $category->allocated_amount = $row['allocated_amount'];
            $category->suggested_percentage = $totalBudget > 0
                ? round(($row['allocated_amount'] / $totalBudget) * 100, 2)
                : 0;
            $category->save();
        }

        return response()->json(['success' => true, 'message' => 'Budget rebalanced successfully.']);
    }

    protected function authorizeEvent(Event $event): void
    {
        abort_unless($event->user_id === Auth::id(), 403);
    }
}
