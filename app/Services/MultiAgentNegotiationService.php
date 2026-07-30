<?php

namespace App\Services;

use App\Exceptions\AiContentionNegotiationNotConfiguredException;
use App\Models\Event;
use App\Models\EventGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class MultiAgentNegotiationService
{
    protected const TOOL_NAME = 'propose_concessions';

    /**
     * CR-46…CR-52 — each contending sub-event is represented as an advocate
     * arguing to retain its funding; the model settles on a compromise that
     * (ideally) fully absorbs the Global Deficit. Immunity is enforced twice:
     * once in the prompt, and again defensively on the parsed response, since
     * CR-49 makes it an absolute constraint, not a suggestion to the model.
     *
     * @param  Collection<int, Event>  $contendingEvents  every event in contention (immune or not — CR-46 shows the model the full picture)
     * @param  array<int>  $immuneIds
     * @return array{concessions: array<int, array{event: Event, concession: float, rationale: string}>, residual: float}
     */
    public function negotiate(EventGroup $group, Collection $contendingEvents, array $immuneIds, float $deficit): array
    {
        $eligible = $contendingEvents->reject(fn (Event $e) => in_array($e->id, $immuneIds, true));

        if ($eligible->isEmpty()) {
            return ['concessions' => [], 'residual' => round($deficit, 2)];
        }

        $args = $this->callGemini($group, $contendingEvents, $immuneIds, $deficit);

        $byName = $eligible->keyBy('event_name');
        $concessions = [];
        $absorbed = 0.0;

        foreach ($args['concessions'] as $entry) {
            $name = $entry['event_name'] ?? null;
            $amount = $entry['concession_amount'] ?? null;
            $rationale = $entry['rationale'] ?? null;

            if (! is_string($name) || ! is_numeric($amount) || ! is_string($rationale) || ! $byName->has($name)) {
                continue;
            }

            $event = $byName[$name];
            // CR-49/CR-50 — hard-enforced regardless of what the model returned:
            // never a concession for an immune event, never below zero, never
            // above available headroom.
            $headroom = max(0, (float) $event->total_budget - (float) $event->budget_spent);
            $clamped = round(max(0, min((float) $amount, $headroom)), 2);

            $concessions[] = ['event' => $event, 'concession' => $clamped, 'rationale' => $rationale];
            $absorbed += $clamped;
        }

        if (empty($concessions)) {
            throw new \RuntimeException('The negotiation returned no usable concessions.');
        }

        return [
            'concessions' => $concessions,
            'residual' => round(max(0, $deficit - $absorbed), 2),
        ];
    }

    /**
     * @return array{concessions: array<int, array<string, mixed>>}
     */
    protected function callGemini(EventGroup $group, Collection $contendingEvents, array $immuneIds, float $deficit): array
    {
        $baseUrl = config('services.ai_task_generator.url');
        $key = config('services.ai_task_generator.key');
        $model = config('services.ai_task_generator.model');

        if (! $baseUrl || ! $key) {
            throw new AiContentionNegotiationNotConfiguredException();
        }

        $url = rtrim($baseUrl, '/')."/models/{$model}:generateContent";

        $response = Http::withHeaders(['x-goog-api-key' => $key])
            ->timeout(45)
            ->acceptJson()
            ->retry(2, 2000, function ($exception) {
                return $exception instanceof \Illuminate\Http\Client\RequestException
                    && in_array($exception->response->status(), [429, 503], true);
            }, throw: false)
            ->post($url, [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $this->buildPrompt($group, $contendingEvents, $immuneIds, $deficit)]]],
                ],
                'tools' => [
                    ['functionDeclarations' => [$this->concessionTool()]],
                ],
                'toolConfig' => [
                    'functionCallingConfig' => [
                        'mode' => 'ANY',
                        'allowedFunctionNames' => [self::TOOL_NAME],
                    ],
                ],
            ])
            ->throw()
            ->json();

        $parts = $response['candidates'][0]['content']['parts'] ?? [];
        $toolUse = collect($parts)->first(fn ($part) => isset($part['functionCall']) && $part['functionCall']['name'] === self::TOOL_NAME);
        $args = $toolUse['functionCall']['args'] ?? null;

        if (! is_array($args) || ! is_array($args['concessions'] ?? null)) {
            throw new \RuntimeException('The negotiation returned an unusable result.');
        }

        return $args;
    }

    protected function buildPrompt(EventGroup $group, Collection $contendingEvents, array $immuneIds, float $deficit): string
    {
        $lines = $contendingEvents->map(function (Event $e) use ($immuneIds) {
            $headroom = max(0, (float) $e->total_budget - (float) $e->budget_spent);
            $locked = in_array($e->id, $immuneIds, true) ? ' — IMMUNE, cannot concede any amount' : '';

            return "- \"{$e->event_name}\" ({$e->event_type}): budget {$e->currencySymbol()}".number_format($e->total_budget, 2).
                ', spent '.$e->currencySymbol().number_format($e->budget_spent, 2).
                ", {$e->days_remaining} days remaining, available headroom {$e->currencySymbol()}".number_format($headroom, 2).$locked;
        })->implode("\n");

        $deficitFormatted = $group->currencySymbol().number_format($deficit, 2);

        return <<<PROMPT
        Several events inside one shared budget pool ("{$group->name}") are now competing for the same
        money — the pool is short by {$deficitFormatted}. Roleplay each non-immune event below as an
        advocate arguing to retain its own funding, then settle on a fair compromise across all of them.

        Rules:
        - Immune events must receive a concession of exactly 0 — they are off the table entirely.
        - No event's concession may exceed its own "available headroom" figure below.
        - Concessions should together absorb as much of the deficit as possible; spreading the deficit
          across multiple events is preferred over exhausting a single one, unless the facts clearly favor it.
        - Give a short, plain-language rationale for every non-immune event explaining why it conceded
          what it did (or why it was largely protected), referencing its type and how soon it happens.

        Events in contention:
        {$lines}

        Global deficit to absorb: {$deficitFormatted}
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function concessionTool(): array
    {
        return [
            'name' => self::TOOL_NAME,
            'description' => 'Records the negotiated budget concession for every eligible event in a pooled-budget contention.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'concessions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'event_name' => ['type' => 'string'],
                                'concession_amount' => ['type' => 'number', 'description' => 'Amount this event gives up, in the group\'s currency. 0 for immune events.'],
                                'rationale' => ['type' => 'string', 'description' => 'Plain-language reason for this event\'s concession or protection.'],
                            ],
                            'required' => ['event_name', 'concession_amount', 'rationale'],
                        ],
                    ],
                ],
                'required' => ['concessions'],
            ],
        ];
    }
}
