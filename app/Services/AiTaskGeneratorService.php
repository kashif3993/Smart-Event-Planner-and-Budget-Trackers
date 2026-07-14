<?php

namespace App\Services;

use App\Exceptions\AiTaskGeneratorNotConfiguredException;
use App\Models\Event;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class AiTaskGeneratorService
{
    protected const ALLOWED_PHASES = ['Pre-Planning', 'Preparation', 'Day-Of'];

    protected const ALLOWED_PRIORITIES = ['Low', 'Medium', 'High'];

    protected const TOOL_NAME = 'build_task_checklist';

    /**
     * Lead-time thresholds (days until the event) shared with anything else that
     * needs to reflect how compressed the planning window is — e.g. the Timeline
     * page uses these same cutoffs to explain why fewer/no Pre-Planning
     * milestones show up for a near-term event.
     */
    public const URGENT_LEAD_DAYS = 14;

    public const CONDENSED_LEAD_DAYS = 60;

    /**
     * Ask Claude (Anthropic Messages API) for a task checklist for the given event.
     * Uses forced tool-use so the response is a structured object, not free-form prose.
     *
     * @return array{tasks: array<int, array<string, mixed>>, insight: ?string}
     */
    public function generateTasks(Event $event): array
    {
        $baseUrl = config('services.ai_task_generator.url');
        $key = config('services.ai_task_generator.key');
        $model = config('services.ai_task_generator.model');

        if (! $baseUrl || ! $key) {
            throw new AiTaskGeneratorNotConfiguredException();
        }

        $url = rtrim($baseUrl, '/') . "/models/{$model}:generateContent";

        $response = Http::withHeaders([
                'x-goog-api-key' => $key,
            ])
            ->timeout(45)
            ->acceptJson()
            ->retry(2, 2000, function ($exception) {
                return $exception instanceof \Illuminate\Http\Client\RequestException
                    && in_array($exception->response->status(), [429, 503], true);
            }, throw: false)
            ->post($url, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $this->buildPrompt($event)]
                        ]
                    ]
                ],
                'tools' => [
                    ['functionDeclarations' => [$this->taskChecklistTool()]]
                ],
                'toolConfig' => [
                    'functionCallingConfig' => [
                        'mode' => 'ANY',
                        'allowedFunctionNames' => [self::TOOL_NAME]
                    ]
                ]
            ])
            ->throw()
            ->json();

        $parts = $response['candidates'][0]['content']['parts'] ?? [];
        $toolUse = collect($parts)->first(fn ($part) => isset($part['functionCall']) && $part['functionCall']['name'] === self::TOOL_NAME);
        $input = $toolUse['functionCall']['args'] ?? null;

        if (! is_array($input)) {
            return ['tasks' => [], 'insight' => null];
        }

        return [
            'tasks' => $this->normalizeTasks($input['tasks'] ?? []),
            'insight' => isset($input['insight']) && is_string($input['insight']) && $input['insight'] !== ''
                ? $input['insight']
                : null,
        ];
    }

    /**
     * Create the given normalized tasks against the event, resolving each
     * task's depends_on_index (a position within this same batch) into a real
     * dependency_task_id once every task has an ID to point at.
     *
     * @param  array<int, array<string, mixed>>  $tasks
     * @return Collection<int, Task>
     */
    public function createTasks(Event $event, array $tasks): Collection
    {
        $created = collect($tasks)->map(function (array $taskData) use ($event) {
            $dependsOnIndex = $taskData['depends_on_index'] ?? null;
            unset($taskData['depends_on_index']);

            return [
                'task' => $event->tasks()->create($taskData),
                'depends_on_index' => $dependsOnIndex,
            ];
        });

        foreach ($created as $index => $entry) {
            if ($entry['depends_on_index'] === null || ! $created->has($entry['depends_on_index'])) {
                continue;
            }

            $entry['task']->update([
                'dependency_task_id' => $created->get($entry['depends_on_index'])['task']->id,
            ]);
        }

        return $created->pluck('task');
    }

    protected function buildPrompt(Event $event): string
    {
        $type = $event->event_type === 'Custom' ? $event->custom_event_type : $event->event_type;

        $details = [
            'Event name' => $event->event_name,
            'Event type' => $type,
            'Event date' => optional($event->event_date)->toDateString(),
            'Guest count' => $event->max_guests
                ? "{$event->guest_count} confirmed of {$event->max_guests} capacity"
                : $event->guest_count,
            'Venue' => trim(($event->venue_name ?? '').' '.($event->location ? '('.$event->location.')' : '')) ?: 'Not set',
            'Budget' => "{$event->currencySymbol()}{$event->total_budget} total, {$event->currencySymbol()}{$event->budget_spent} spent so far",
            'Description' => $event->description ?: 'None provided',
        ];

        $lines = collect($details)->map(fn ($value, $label) => "- {$label}: {$value}")->implode("\n");

        return <<<PROMPT
        You are an expert event planner. Build a task checklist for the event below.

        {$lines}

        {$this->leadTimeGuidance($event)}

        Group tasks across the three planning phases (Pre-Planning, Preparation, Day-Of) as directed
        above. Each task needs a realistic due date on or before the event date, with earlier-phase
        tasks given earlier due dates than later-phase tasks. Assign a priority (Low, Medium, High) to
        each task reflecting how urgent it is.

        List tasks in the order they should logically happen. If a task can't reasonably start until an
        earlier task in this same list is done (e.g. "Send invitations" can't happen before "Finalize
        guest list"), set that task's depends_on_index to the 0-based position of the task it depends
        on. Only set it for genuine blocking dependencies, not just related tasks — most tasks should
        have no dependency at all.

        Also include one short, specific planning insight (a risk to watch or a money/time-saving
        suggestion) based on the event details above.
        PROMPT;
    }

    /**
     * How many tasks to ask for, and which phases to weight, based on how much lead
     * time is left before the event — a same-week event shouldn't get a 12-item
     * checklist full of Pre-Planning research that can no longer be actioned.
     */
    protected function leadTimeGuidance(Event $event): string
    {
        if (! $event->event_date) {
            return 'The event date is not set — assume a typical multi-month planning window '
                .'and generate 10-14 tasks spread across all three phases.';
        }

        $daysUntilEvent = (int) max(0, now()->startOfDay()->diffInDays($event->event_date, false));

        if ($daysUntilEvent <= self::URGENT_LEAD_DAYS) {
            return "Only {$daysUntilEvent} day(s) remain before the event. Generate 4-6 urgent tasks "
                ."focused on Preparation and Day-Of logistics. Skip long-lead-time Pre-Planning research "
                .'tasks that can no longer realistically be actioned in time.';
        }

        if ($daysUntilEvent <= self::CONDENSED_LEAD_DAYS) {
            return "{$daysUntilEvent} days remain before the event. Generate 8-10 tasks: a few "
                .'high-priority Pre-Planning items, then weight the rest toward Preparation and Day-Of.';
        }

        return "{$daysUntilEvent} days remain before the event — there's ample lead time. Generate "
            .'10-14 tasks spread across all three phases, prioritizing early-stage planning and '
            .'booking tasks first.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function taskChecklistTool(): array
    {
        return [
            'name' => self::TOOL_NAME,
            'description' => 'Records a structured event-planning task checklist.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'tasks' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'task_name' => ['type' => 'string'],
                                'phase' => ['type' => 'string', 'enum' => self::ALLOWED_PHASES],
                                'due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                                'priority' => ['type' => 'string', 'enum' => self::ALLOWED_PRIORITIES],
                                'depends_on_index' => [
                                    'type' => 'integer',
                                    'description' => 'Optional. 0-based index of an earlier task in '
                                        .'this same array that must be done first. Omit if this task '
                                        .'has no blocking dependency.',
                                ],
                                'notes' => ['type' => 'string'],
                            ],
                            'required' => ['task_name', 'phase', 'priority'],
                        ],
                    ],
                    'insight' => ['type' => 'string'],
                ],
                'required' => ['tasks'],
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $rawTasks
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeTasks(array $rawTasks): array
    {
        $tasks = [];

        foreach ($rawTasks as $raw) {
            if (! is_array($raw) || empty($raw['task_name'])) {
                continue;
            }

            $dueDate = $raw['due_date'] ?? null;
            if ($dueDate !== null && strtotime((string) $dueDate) === false) {
                $dueDate = null;
            }

            // Only accept a dependency that points to an earlier item already
            // accepted into this batch — rejects self-references, forward
            // references, and anything pointing at a task that got skipped above.
            $dependsOnIndex = $raw['depends_on_index'] ?? null;
            $ownIndex = count($tasks);
            if (! is_int($dependsOnIndex) || $dependsOnIndex < 0 || $dependsOnIndex >= $ownIndex) {
                $dependsOnIndex = null;
            }

            $tasks[] = [
                'task_name' => (string) $raw['task_name'],
                'phase' => in_array($raw['phase'] ?? null, self::ALLOWED_PHASES, true) ? $raw['phase'] : 'Pre-Planning',
                'due_date' => $dueDate,
                'priority' => in_array($raw['priority'] ?? null, self::ALLOWED_PRIORITIES, true) ? $raw['priority'] : 'Medium',
                'depends_on_index' => $dependsOnIndex,
                'notes' => isset($raw['notes']) ? (string) $raw['notes'] : null,
                'source' => 'AI',
                'status' => 'Pending',
            ];
        }

        return $tasks;
    }
}
