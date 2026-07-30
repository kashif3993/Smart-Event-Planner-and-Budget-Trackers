<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventGroupLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_group_with_a_future_event_cannot_be_archived(): void
    {
        $user = User::factory()->create();
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR', 'event_date' => now()->addDays(5)]);
        $e2 = Event::factory()->for($user)->create(['currency' => 'PKR', 'event_date' => now()->addDays(10)]);

        $this->actingAs($user)->post(route('event-groups.store'), [
            'name' => 'Future Group', 'group_type' => 'Custom', 'custom_group_type' => 'X',
            'event_ids' => [$e1->id, $e2->id],
        ]);
        $group = EventGroup::where('name', 'Future Group')->firstOrFail();

        $response = $this->actingAs($user)->patch(route('event-groups.archive', $group));

        $response->assertRedirect();
        $this->assertEquals('Active', $group->fresh()->status);
    }

    public function test_a_group_with_only_past_events_can_be_archived(): void
    {
        $user = User::factory()->create();
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR', 'event_date' => now()->subDays(5)]);
        $e2 = Event::factory()->for($user)->create(['currency' => 'PKR', 'event_date' => now()->subDays(10)]);

        $this->actingAs($user)->post(route('event-groups.store'), [
            'name' => 'Past Group', 'group_type' => 'Custom', 'custom_group_type' => 'X',
            'event_ids' => [$e1->id, $e2->id],
        ]);
        $group = EventGroup::where('name', 'Past Group')->firstOrFail();

        $this->actingAs($user)->patch(route('event-groups.archive', $group));

        $this->assertEquals('Archived', $group->fresh()->status);
        $this->assertNotNull($group->fresh()->archived_at);
    }

    public function test_a_pooled_group_in_contention_cannot_be_dissolved(): void
    {
        $user = User::factory()->create();
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 4000, 'budget_spent' => 5000]);
        $e2 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 4000, 'budget_spent' => 5500]);

        $group = EventGroup::create([
            'user_id' => $user->id, 'name' => 'Contended', 'group_type' => 'Custom', 'custom_group_type' => 'X',
            'currency' => 'PKR', 'budget_mode' => 'Pooled', 'pooled_budget_cap' => 6000,
        ]);
        $e1->update(['event_group_id' => $group->id]);
        $e2->update(['event_group_id' => $group->id]);

        $response = $this->actingAs($user)->delete(route('event-groups.destroy', $group));

        $response->assertRedirect();
        $this->assertDatabaseHas('event_groups', ['id' => $group->id]);
    }

    public function test_detaching_down_to_one_event_flags_add_or_dissolve(): void
    {
        $user = User::factory()->create();
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR']);
        $e2 = Event::factory()->for($user)->create(['currency' => 'PKR']);

        $this->actingAs($user)->post(route('event-groups.store'), [
            'name' => 'Shrinking Group', 'group_type' => 'Custom', 'custom_group_type' => 'X',
            'event_ids' => [$e1->id, $e2->id],
        ]);
        $group = EventGroup::where('name', 'Shrinking Group')->firstOrFail();

        $response = $this->actingAs($user)->delete(route('event-groups.events.detach', [$group, $e1]));

        $response->assertSessionHas('success');
        $this->assertStringContainsString('one event left', session('success'));
        $this->assertEquals(1, $group->events()->count());
    }
}
