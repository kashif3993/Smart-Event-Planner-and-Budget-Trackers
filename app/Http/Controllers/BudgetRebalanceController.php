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
     * The only sandbox interaction that needs the server: ranking Mutable
     * categories by how safe they are to cut, via an LLM call. The frontend
     * fetches this once per sandbox session and caches it — every Proportional
     * / Targeted calculation and every lock-toggle reacts entirely from local
     * state (see rebalancer.js), with no further requests here.
     */
    public function aiPriorities(Event $event, BudgetRebalancerService $service): JsonResponse
    {
        $this->authorizeEvent($event);

        try {
            $priorities = $service->fetchMutableCategoryPriorities($event);
        } catch (AiBudgetRebalancerNotConfiguredException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (RequestException $e) {
            report($e);
            $response = $e->response->json();
            $apiMessage = $response['error']['message'] ?? 'AI rebalance suggestion failed (API Error).';

            return response()->json(['success' => false, 'message' => 'Google API Error: '.$apiMessage], 502);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Could not fetch AI priorities. Please try again.'], 502);
        }

        return response()->json(['success' => true, 'priorities' => $priorities]);
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
