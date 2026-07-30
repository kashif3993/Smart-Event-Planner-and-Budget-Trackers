<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentionSandboxTest extends TestCase
{
    use RefreshDatabase;

    protected function contendedGroup(User $user): EventGroup
    {
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 4000, 'budget_spent' => 5000, 'event_type' => 'Wedding']);
        $e2 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 4000, 'budget_spent' => 5500, 'event_type' => 'Birthday Party']);

        $group = EventGroup::create([
            'user_id' => $user->id,
            'name' => 'Contended Group',
            'group_type' => 'Custom',
            'custom_group_type' => 'Test',
            'currency' => 'PKR',
            'budget_mode' => 'Pooled',
            'pooled_budget_cap' => 6000,
        ]);
        $e1->update(['event_group_id' => $group->id]);
        $e2->update(['event_group_id' => $group->id]);

        return $group->fresh('events');
    }

    public function test_snapshot_returns_contending_events_with_scoring_inputs(): void
    {
        $user = User::factory()->create();
        $group = $this->contendedGroup($user);

        $response = $this->actingAs($user)->getJson(route('event-groups.contention.snapshot', $group));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(2, 'events');
        $response->assertJsonStructure([
            'pooled_budget_cap', 'global_deficit', 'currency_symbol',
            'events' => [['id', 'name', 'type', 'days_remaining', 'weight', 'total_budget', 'budget_spent', 'headroom', 'categories']],
        ]);
    }

    public function test_snapshot_is_read_only_and_does_not_mutate_any_record(): void
    {
        $user = User::factory()->create();
        $group = $this->contendedGroup($user);
        $before = $group->events->map(fn ($e) => $e->only(['id', 'total_budget', 'budget_spent']))->toArray();

        $this->actingAs($user)->getJson(route('event-groups.contention.snapshot', $group));

        $after = $group->fresh('events')->events->map(fn ($e) => $e->only(['id', 'total_budget', 'budget_spent']))->toArray();
        $this->assertEquals($before, $after);
    }

    public function test_snapshot_rejects_a_group_not_currently_contended(): void
    {
        $user = User::factory()->create();
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 4000, 'budget_spent' => 1000]);
        $e2 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 4000, 'budget_spent' => 1000]);
        $group = EventGroup::create([
            'user_id' => $user->id, 'name' => 'Healthy', 'group_type' => 'Custom', 'custom_group_type' => 'Healthy',
            'currency' => 'PKR', 'budget_mode' => 'Pooled', 'pooled_budget_cap' => 10000,
        ]);
        $e1->update(['event_group_id' => $group->id]);
        $e2->update(['event_group_id' => $group->id]);

        $response = $this->actingAs($user)->getJson(route('event-groups.contention.snapshot', $group));

        $response->assertStatus(422);
    }

    public function test_snapshot_is_forbidden_for_a_non_owner(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $group = $this->contendedGroup($owner);

        $this->actingAs($intruder)->getJson(route('event-groups.contention.snapshot', $group))->assertForbidden();
    }

    public function test_group_overview_renders_the_sandbox_modal_when_contended(): void
    {
        $user = User::factory()->create();
        $group = $this->contendedGroup($user);

        $response = $this->actingAs($user)->get(route('event-groups.show', $group));

        $response->assertOk();
        $response->assertSee('Contention Resolution Sandbox');
        $response->assertSee('Strict Hierarchy');
    }
}
