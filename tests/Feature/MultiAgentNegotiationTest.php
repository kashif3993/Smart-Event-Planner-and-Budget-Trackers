<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MultiAgentNegotiationTest extends TestCase
{
    use RefreshDatabase;

    protected function contendedGroup(User $user): EventGroup
    {
        // Ceremony is already over budget (zero headroom — nothing left to concede).
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 4000, 'budget_spent' => 4200, 'event_type' => 'Wedding', 'event_name' => 'Ceremony']);
        // Rehearsal Dinner is only projecting (95% spent) — still has real headroom (500) to give.
        $e2 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 10000, 'budget_spent' => 9500, 'event_type' => 'Birthday Party', 'event_name' => 'Rehearsal Dinner']);

        $group = EventGroup::create([
            'user_id' => $user->id, 'name' => 'Wedding Weekend', 'group_type' => 'Wedding',
            'currency' => 'PKR', 'budget_mode' => 'Pooled', 'pooled_budget_cap' => 8000,
        ]);
        $e1->update(['event_group_id' => $group->id]);
        $e2->update(['event_group_id' => $group->id]);

        return $group->fresh('events');
    }

    protected function geminiFunctionCallResponse(array $concessions): array
    {
        return [
            'candidates' => [
                ['content' => ['parts' => [
                    ['functionCall' => ['name' => 'propose_concessions', 'args' => ['concessions' => $concessions]]],
                ]]],
            ],
        ];
    }

    public function test_successful_negotiation_returns_concessions_respecting_immunity_and_headroom(): void
    {
        config(['services.ai_task_generator.url' => 'https://fake.example/v1beta']);
        config(['services.ai_task_generator.key' => 'fake-key']);
        config(['services.ai_task_generator.model' => 'gemini-flash-latest']);

        Http::fake([
            'fake.example/*' => Http::response($this->geminiFunctionCallResponse([
                ['event_name' => 'Rehearsal Dinner', 'concession_amount' => 500, 'rationale' => 'Flexible catering, absorbs what it can.'],
            ]), 200),
        ]);

        $user = User::factory()->create();
        $group = $this->contendedGroup($user);
        $ceremony = $group->events->firstWhere('event_name', 'Ceremony');

        $response = $this->actingAs($user)->postJson(route('event-groups.contention.negotiate', $group), [
            'immune_event_ids' => [$ceremony->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('ai_used', true);

        $rehearsal = $group->events->firstWhere('event_name', 'Rehearsal Dinner');
        $concessions = collect($response->json('concessions'));
        $this->assertEquals(500, $concessions->firstWhere('event_id', $rehearsal->id)['concession_amount']);
        // The immune event is excluded from the eligible set entirely — it never
        // appears in the output, not even with a zero concession.
        $this->assertNull($concessions->firstWhere('event_id', $ceremony->id));
    }

    public function test_immune_event_never_receives_a_concession_even_if_the_model_tries(): void
    {
        config(['services.ai_task_generator.url' => 'https://fake.example/v1beta']);
        config(['services.ai_task_generator.key' => 'fake-key']);

        Http::fake([
            // Model misbehaves and tries to assign a concession to the immune event anyway.
            'fake.example/*' => Http::response($this->geminiFunctionCallResponse([
                ['event_name' => 'Ceremony', 'concession_amount' => 1500, 'rationale' => 'Ignoring immunity.'],
                ['event_name' => 'Rehearsal Dinner', 'concession_amount' => 3000, 'rationale' => 'Absorbs the shortfall.'],
            ]), 200),
        ]);

        $user = User::factory()->create();
        $group = $this->contendedGroup($user);
        $ceremony = $group->events->firstWhere('event_name', 'Ceremony');

        $response = $this->actingAs($user)->postJson(route('event-groups.contention.negotiate', $group), [
            'immune_event_ids' => [$ceremony->id],
        ]);

        $response->assertOk();
        $concessions = collect($response->json('concessions'));
        // The immune event should never appear in the eligible set at all — the
        // service filters by name against only the *eligible* (non-immune) events.
        $this->assertNull($concessions->firstWhere('event_id', $ceremony->id));
    }

    public function test_ai_failure_falls_back_to_strict_hierarchy_automatically(): void
    {
        config(['services.ai_task_generator.url' => 'https://fake.example/v1beta']);
        config(['services.ai_task_generator.key' => 'fake-key']);

        Http::fake([
            'fake.example/*' => Http::response(['error' => ['message' => 'Service unavailable']], 500),
        ]);

        $user = User::factory()->create();
        $group = $this->contendedGroup($user);

        $response = $this->actingAs($user)->postJson(route('event-groups.contention.negotiate', $group), [
            'immune_event_ids' => [],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('fell_back', true);
        $this->assertNotEmpty($response->json('concessions'));
    }

    public function test_missing_ai_configuration_falls_back_to_strict_hierarchy(): void
    {
        config(['services.ai_task_generator.key' => null]);

        $user = User::factory()->create();
        $group = $this->contendedGroup($user);

        $response = $this->actingAs($user)->postJson(route('event-groups.contention.negotiate', $group), [
            'immune_event_ids' => [],
        ]);

        $response->assertOk();
        $response->assertJsonPath('fell_back', true);
        $this->assertStringContainsString("isn't configured", $response->json('message'));
    }
}
