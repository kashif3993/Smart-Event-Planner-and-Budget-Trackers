<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupBudgetModeTest extends TestCase
{
    use RefreshDatabase;

    protected function makeGroup(User $user, float $budget1 = 10000, float $budget2 = 5000): EventGroup
    {
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => $budget1]);
        $e2 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => $budget2]);

        $this->actingAs($user)->post(route('event-groups.store'), [
            'name' => 'Combo',
            'group_type' => 'Custom',
            'custom_group_type' => 'Combo',
            'event_ids' => [$e1->id, $e2->id],
        ]);

        return EventGroup::where('name', 'Combo')->firstOrFail();
    }

    public function test_switching_to_pooled_mode_sets_the_cap_and_leaves_event_data_untouched(): void
    {
        $user = User::factory()->create();
        $group = $this->makeGroup($user, 10000, 5000);

        $response = $this->actingAs($user)->put(route('event-groups.budgetMode.update', $group), [
            'budget_mode' => 'Pooled',
            'pooled_budget_cap' => 15000,
        ]);

        $response->assertRedirect();
        $group->refresh();
        $this->assertEquals('Pooled', $group->budget_mode);
        $this->assertEquals(15000, $group->pooled_budget_cap);

        // Underlying event data (total_budget) is untouched by the mode switch.
        $this->assertEquals(10000, $group->events()->orderBy('total_budget', 'desc')->first()->total_budget);
    }

    public function test_switching_to_distributed_requires_an_explicit_split_for_every_event(): void
    {
        $user = User::factory()->create();
        $group = $this->makeGroup($user, 10000, 5000);
        $group->update(['budget_mode' => 'Pooled', 'pooled_budget_cap' => 15000]);

        $response = $this->actingAs($user)->put(route('event-groups.budgetMode.update', $group), [
            'budget_mode' => 'Distributed',
        ]);

        $response->assertRedirect();
        $group->refresh();
        $this->assertEquals('Pooled', $group->budget_mode, 'mode should not change without a full split');
    }

    public function test_switching_to_distributed_with_a_full_split_applies_it(): void
    {
        $user = User::factory()->create();
        $group = $this->makeGroup($user, 10000, 5000);
        $group->update(['budget_mode' => 'Pooled', 'pooled_budget_cap' => 15000]);
        [$e1, $e2] = $group->events;

        $response = $this->actingAs($user)->put(route('event-groups.budgetMode.update', $group), [
            'budget_mode' => 'Distributed',
            'splits' => [
                $e1->id => 9000,
                $e2->id => 6000,
            ],
        ]);

        $response->assertRedirect();
        $group->refresh();
        $this->assertEquals('Distributed', $group->budget_mode);
        $this->assertNull($group->pooled_budget_cap);
        $this->assertEquals(9000, $e1->fresh()->total_budget);
        $this->assertEquals(6000, $e2->fresh()->total_budget);
    }

    public function test_pooled_cap_cannot_be_set_below_already_spent(): void
    {
        $user = User::factory()->create();
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 10000, 'budget_spent' => 9000]);
        $e2 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 5000, 'budget_spent' => 4000]);

        $this->actingAs($user)->post(route('event-groups.store'), [
            'name' => 'Combo',
            'group_type' => 'Custom',
            'custom_group_type' => 'Combo',
            'event_ids' => [$e1->id, $e2->id],
        ]);
        $group = EventGroup::where('name', 'Combo')->firstOrFail();

        $response = $this->actingAs($user)->put(route('event-groups.budgetMode.update', $group), [
            'budget_mode' => 'Pooled',
            'pooled_budget_cap' => 10000,
        ]);

        $group->refresh();
        $this->assertEquals('Distributed', $group->budget_mode, 'cap below combined spend (13000) should be rejected');
    }
}
