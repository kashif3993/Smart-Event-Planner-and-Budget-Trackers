<?php

namespace App\Http\Controllers;

use App\Models\EventGroup;
use App\Services\ContentionDetectionService;
use App\Services\ContentionResolutionCommitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContentionResolutionController extends Controller
{
    /**
     * CR-64…CR-70 — commit requires explicit approval, applies all
     * concessions together or none of them, and never leaves a partial
     * change behind on failure.
     */
    public function commit(EventGroup $group, ContentionDetectionService $contention, ContentionResolutionCommitService $committer): JsonResponse
    {
        $this->authorizeGroup($group);

        if (! $group->isPooled() || ! $contention->isContended($group)) {
            return response()->json(['success' => false, 'message' => 'This group is not currently in contention. Its position may already be resolved.'], 422);
        }

        $data = request()->validate([
            'strategy' => ['required', 'in:Strict Hierarchy,Multi-Agent Negotiation'],
            'concessions' => ['required', 'array', 'min:1'],
            'concessions.*.event_id' => ['required', 'integer'],
            'concessions.*.concession_amount' => ['required', 'numeric', 'min:0'],
            'concessions.*.rationale' => ['nullable', 'string'],
            'manually_amended' => ['boolean'],
            'immune_event_ids' => ['array'],
            'immune_event_ids.*' => ['integer'],
            'expected_global_deficit' => ['nullable', 'numeric'],
        ]);

        // FR-24 / section 8 "an expense is logged elsewhere while the sandbox
        // is open" — the sandbox snapshot is held client-side for the whole
        // session, so if the pool's real numbers moved since it was taken,
        // applying the old proposal as-is would silently commit a stale
        // reallocation. Refuse and point the user at a refresh instead.
        if (array_key_exists('expected_global_deficit', $data) && $data['expected_global_deficit'] !== null) {
            $currentDeficit = $contention->globalDeficit($group);

            if (abs($currentDeficit - (float) $data['expected_global_deficit']) > 0.01) {
                return response()->json([
                    'success' => false,
                    'stale' => true,
                    'message' => "This pool's numbers changed since you opened the sandbox (deficit is now {$group->currencySymbol()}".number_format($currentDeficit, 0).'). Refresh the sandbox and try again.',
                ], 409);
            }
        }

        try {
            $committer->commit(
                $group,
                $data['concessions'],
                $data['strategy'],
                $data['strategy'] === 'Multi-Agent Negotiation',
                null,
                $data['manually_amended'] ?? false,
                $data['immune_event_ids'] ?? []
            );
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Could not commit this allocation. No changes were made.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Allocation committed across every affected event.']);
    }

    /**
     * CR-71 — the user can review previously committed resolutions for a group.
     */
    public function index(EventGroup $group): View
    {
        $this->authorizeGroup($group);

        $resolutions = $group->resolutions;

        return view('event-groups.resolutions', compact('group', 'resolutions'));
    }

    protected function authorizeGroup(EventGroup $group): void
    {
        abort_unless($group->user_id === Auth::id(), 403);
    }
}
