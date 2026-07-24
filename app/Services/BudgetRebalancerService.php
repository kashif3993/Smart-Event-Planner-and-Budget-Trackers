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
     * The only piece of the "Rebalancer Sandbox" that genuinely needs the
     * server: ranking each Mutable category by how safe it is to cut, via an
     * LLM call. Everything else (Proportional, Targeted, and applying these
     * priorities once fetched) happens entirely in frontend local state per
     * the PRD's "no API calls" constraint on the interactive sandbox — see
     * rebalancer.js. The frontend fetches this once per modal session and
     * reuses it for every subsequent lock toggle / strategy switch.
     *
     * @return array<int, array{id: int, category_name: string, priority: int}>
     */
    public function fetchMutableCategoryPriorities(Event $event): array
    {
        $categories = $this->categoriesWithSpend($event);

        $mutable = $categories->reject(fn (array $c) => $c['spent'] > $c['allocated_amount'] || $c['has_paid_expense']);

        if ($mutable->isEmpty()) {
            return [];
        }

        $priorities = $this->fetchAiSlashPriorities($event, $mutable);

        return $mutable->map(fn (array $c) => [
            'id' => $c['id'],
            'category_name' => $c['category_name'],
            'priority' => max(1, min(5, (int) ($priorities[$c['id']] ?? ($c['ai_slash_priority'] ?: 3)))),
        ])->values()->all();
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
