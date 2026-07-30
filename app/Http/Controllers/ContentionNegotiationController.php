<?php

namespace App\Http\Controllers;

use App\Exceptions\AiContentionNegotiationNotConfiguredException;
use App\Models\EventGroup;
use App\Services\ContentionDetectionService;
use App\Services\MultiAgentNegotiationService;
use App\Services\StrictHierarchyScoringService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ContentionNegotiationController extends Controller
{
    /**
     * CR-46…CR-52 — Multi-Agent Negotiation. CR-51: on any AI failure, falls
     * back to Strict Hierarchy automatically and says so plainly rather than
     * surfacing a raw error or a partial/broken result.
     */
    public function negotiate(
        EventGroup $group,
        ContentionDetectionService $contention,
        MultiAgentNegotiationService $negotiationService,
        StrictHierarchyScoringService $scoring
    ): JsonResponse {
        $this->authorizeGroup($group);

        if (! $group->isPooled() || ! $contention->isContended($group)) {
            return response()->json(['success' => false, 'message' => 'This group is not currently in contention.'], 422);
        }

        $immuneIds = array_map('intval', request()->input('immune_event_ids', []));
        $snapshot = $contention->snapshot($group);
        $contendingEventIds = $snapshot['events']->pluck('id');
        $contendingEvents = $group->events->whereIn('id', $contendingEventIds)->values();
        $deficit = $snapshot['global_deficit'];

        try {
            $result = $negotiationService->negotiate($group, $contendingEvents, $immuneIds, $deficit);

            return response()->json([
                'success' => true,
                'ai_used' => true,
                'residual' => $result['residual'],
                'concessions' => collect($result['concessions'])->map(fn ($c) => [
                    'event_id' => $c['event']->id,
                    'concession_amount' => $c['concession'],
                    'rationale' => $c['rationale'],
                ])->values(),
            ]);
        } catch (AiContentionNegotiationNotConfiguredException $e) {
            return $this->fallback($contendingEvents, $immuneIds, $deficit, $scoring, $e->getMessage());
        } catch (RequestException $e) {
            report($e);
            $apiMessage = $e->response->json()['error']['message'] ?? 'Multi-Agent Negotiation failed (API error).';

            return $this->fallback($contendingEvents, $immuneIds, $deficit, $scoring, 'Google API Error: '.$apiMessage);
        } catch (\Throwable $e) {
            report($e);

            return $this->fallback($contendingEvents, $immuneIds, $deficit, $scoring, 'Could not complete Multi-Agent Negotiation.');
        }
    }

    protected function fallback($contendingEvents, array $immuneIds, float $deficit, StrictHierarchyScoringService $scoring, string $reason): JsonResponse
    {
        $result = $scoring->cascadeConcessions($contendingEvents, $deficit, $immuneIds);

        return response()->json([
            'success' => false,
            'fell_back' => true,
            'message' => $reason.' Falling back to Strict Hierarchy — this is an automated, deterministic result you can review below.',
            'residual' => $result['residual'],
            'concessions' => collect($result['concessions'])->map(fn ($c) => [
                'event_id' => $c['event']->id,
                'concession_amount' => $c['concession'],
                'rationale' => "Fallback (Strict Hierarchy): rank #{$c['rank']}, score ".round($c['score'], 2).'.',
            ])->values(),
        ], 200);
    }

    protected function authorizeGroup(EventGroup $group): void
    {
        abort_unless($group->user_id === Auth::id(), 403);
    }
}
