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
        ]);

        try {
            $committer->commit(
                $group,
                $data['concessions'],
                $data['strategy'],
                $data['strategy'] === 'Multi-Agent Negotiation',
                null,
                $data['manually_amended'] ?? false
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
