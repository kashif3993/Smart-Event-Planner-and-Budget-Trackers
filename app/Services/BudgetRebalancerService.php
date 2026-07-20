<?php

namespace App\Services;

use App\Exceptions\AiBudgetRebalancerNotConfiguredException;
use App\Models\Event;
use App\Models\VendorCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class BudgetRebalancerService
{
    protected const TOOL_NAME = 'suggest_slash_priorities';

    /**
     * Build a rebalance proposal for the given event.
     *
     * Over-budget categories get bumped up to match what they've actually
     * spent (so they stop showing as over budget), and that same amount is
     * pulled back out of the remaining Mutable categories according to the
     * chosen strategy. The allocated total across all categories never
     * changes — it's only redistributed, matching the event's budget cap.
     *
     * @param  array<int, int>  $lockedCategoryIds  category ids the sandbox is temporarily locking, on top of any persisted is_locked flag
     * @return array<int, array<string, mixed>>
     */
    public function buildPreview(Event $event, array $lockedCategoryIds, string $strategy): array
    {
        $categories = $this->categoriesWithSpend($event);

        $overBudget = $categories->filter(fn (array $c) => $c['spent'] > $c['allocated_amount']);

        $mutable = $categories->reject(function (array $c) use ($overBudget, $lockedCategoryIds) {
            return $overBudget->has($c['id'])
                || $c['is_locked']
                || $c['has_paid_expense']
                || in_array($c['id'], $lockedCategoryIds, true);
        });

        $deficit = (float) $overBudget->sum(fn (array $c) => $c['spent'] - $c['allocated_amount']);

        $reductions = match ($strategy) {
            'targeted' => $this->targetedReductions($mutable, $deficit),
            'ai' => $this->aiOptimizedReductions($event, $mutable, $deficit),
            default => $this->proportionalReductions($mutable, $deficit),
        };

        $totalBudget = (float) $event->total_budget;

        return $categories->map(function (array $c) use ($overBudget, $reductions, $totalBudget) {
            $newAllocated = $c['allocated_amount'];

            if ($overBudget->has($c['id'])) {
                $newAllocated = $c['spent'];
            } elseif (isset($reductions[$c['id']])) {
                $newAllocated = max($c['spent'], $c['allocated_amount'] - $reductions[$c['id']]);
            }

            return [
                'id' => $c['id'],
                'category_name' => $c['category_name'],
                'allocated_amount' => round($c['allocated_amount'], 2),
                'suggested_allocated_amount' => round($newAllocated, 2),
                'suggested_budget_percentage' => $totalBudget > 0 ? round(($newAllocated / $totalBudget) * 100, 2) : 0,
                'spent' => round($c['spent'], 2),
                'is_over_budget' => $overBudget->has($c['id']),
                'is_locked' => $c['is_locked'],
                'is_immutable' => $overBudget->has($c['id']) || $c['is_locked'] || $c['has_paid_expense'],
            ];
        })->values()->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function categoriesWithSpend(Event $event): Collection
    {
        return $event->vendorCategories()
            ->withSum('expenses as spent_amount', 'actual_cost')
            ->withCount(['expenses as paid_expense_count' => fn ($q) => $q->where('payment_status', 'Paid')])
            ->get()
            ->map(fn (VendorCategory $category) => [
                'id' => $category->id,
                'category_name' => $category->category_name,
                'allocated_amount' => (float) $category->allocated_amount,
                'spent' => (float) ($category->spent_amount ?? 0),
                'is_locked' => (bool) $category->is_locked,
                'has_paid_expense' => $category->paid_expense_count > 0,
                'ai_slash_priority' => $category->ai_slash_priority,
            ])
            ->keyBy('id');
    }

    /**
     * Proportional strategy: the deficit is split across Mutable categories
     * based on their current share of the mutable total's allocation.
     *
     * @return array<int, float>
     */
    protected function proportionalReductions(Collection $mutable, float $deficit): array
    {
        $totalAllocated = (float) $mutable->sum('allocated_amount');
        if ($totalAllocated <= 0 || $deficit <= 0) {
            return [];
        }

        $reductions = [];
        foreach ($mutable as $c) {
            $share = $c['allocated_amount'] / $totalAllocated;
            $headroom = $c['allocated_amount'] - $c['spent'];
            $reductions[$c['id']] = min($deficit * $share, $headroom);
        }

        return $reductions;
    }

    /**
     * Targeted strategy: instead of spreading the cut thin across every
     * category, drain it from whichever Mutable categories have the most
     * unspent headroom first, only spilling into the next one once the
     * biggest is tapped out.
     *
     * @return array<int, float>
     */
    protected function targetedReductions(Collection $mutable, float $deficit): array
    {
        if ($deficit <= 0) {
            return [];
        }

        $reductions = [];
        $remaining = $deficit;

        $sorted = $mutable->sortByDesc(fn (array $c) => $c['allocated_amount'] - $c['spent']);

        foreach ($sorted as $c) {
            if ($remaining <= 0) {
                break;
            }

            $headroom = $c['allocated_amount'] - $c['spent'];
            $take = min($headroom, $remaining);

            if ($take > 0) {
                $reductions[$c['id']] = $take;
                $remaining -= $take;
            }
        }

        return $reductions;
    }

    /**
     * AI-Optimized strategy: ask the LLM to rank each Mutable category by how
     * safe it is to cut (1 = slash first, 5 = protect), then weight the
     * deficit split so lower-ranked categories (e.g. Decor, Entertainment)
     * absorb more of the cut than higher-ranked ones (e.g. Catering, Venue).
     *
     * @return array<int, float>
     */
    protected function aiOptimizedReductions(Event $event, Collection $mutable, float $deficit): array
    {
        if ($mutable->isEmpty() || $deficit <= 0) {
            return [];
        }

        $priorities = $this->fetchAiSlashPriorities($event, $mutable);

        $weights = [];
        foreach ($mutable as $c) {
            $priority = $priorities[$c['id']] ?? ($c['ai_slash_priority'] ?: 3);
            $priority = max(1, min(5, (int) $priority));
            $weights[$c['id']] = 6 - $priority;
        }

        $totalWeight = array_sum($weights);
        if ($totalWeight <= 0) {
            return $this->proportionalReductions($mutable, $deficit);
        }

        $reductions = [];
        foreach ($mutable as $c) {
            $share = $weights[$c['id']] / $totalWeight;
            $headroom = $c['allocated_amount'] - $c['spent'];
            $reductions[$c['id']] = min($deficit * $share, $headroom);
        }

        return $reductions;
    }

    /**
     * @return array<int, int> category id => priority (1 = cut first, 5 = protect)
     */
    protected function fetchAiSlashPriorities(Event $event, Collection $mutable): array
    {
        $baseUrl = config('services.ai_task_generator.url');
        $key = config('services.ai_task_generator.key');
        $model = config('services.ai_task_generator.model');

        if (! $baseUrl || ! $key) {
            throw new AiBudgetRebalancerNotConfiguredException();
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
                    ['role' => 'user', 'parts' => [['text' => $this->buildPrompt($event, $mutable)]]],
                ],
                'tools' => [
                    ['functionDeclarations' => [$this->slashPriorityTool()]],
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

        if (! is_array($args) || ! is_array($args['priorities'] ?? null)) {
            return [];
        }

        $byName = $mutable->keyBy('category_name');
        $priorities = [];

        foreach ($args['priorities'] as $entry) {
            $name = $entry['category_name'] ?? null;
            $priority = $entry['priority'] ?? null;

            if (! is_string($name) || ! is_int($priority) || ! $byName->has($name)) {
                continue;
            }

            $priorities[$byName[$name]['id']] = $priority;
        }

        return $priorities;
    }

    protected function buildPrompt(Event $event, Collection $mutable): string
    {
        $type = $event->event_type === 'Custom' ? $event->custom_event_type : $event->event_type;

        $categoryLines = $mutable
            ->map(fn (array $c) => "- {$c['category_name']}: allocated {$event->currencySymbol()}".number_format($c['allocated_amount'], 2)
                .', spent '.$event->currencySymbol().number_format($c['spent'], 2))
            ->implode("\n");

        return <<<PROMPT
        An event budget went over in one category and the remaining, unspent categories below
        need to be trimmed to cover the shortfall. Rank each category by how safe it is to cut:
        1 means slash this first (a nice-to-have, e.g. Decor or Entertainment), 5 means protect
        this as much as possible (essential, e.g. Catering or Venue).

        Event type: {$type}
        Guest count: {$event->guest_count}

        Categories:
        {$categoryLines}
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function slashPriorityTool(): array
    {
        return [
            'name' => self::TOOL_NAME,
            'description' => 'Records how safe each vendor category is to cut when rebalancing an over-budget event.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'priorities' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'category_name' => ['type' => 'string'],
                                'priority' => ['type' => 'integer', 'description' => '1 = cut first, 5 = protect'],
                            ],
                            'required' => ['category_name', 'priority'],
                        ],
                    ],
                ],
                'required' => ['priorities'],
            ],
        ];
    }
}
