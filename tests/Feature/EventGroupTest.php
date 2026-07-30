<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_group_from_two_existing_events(): void
    {
        $user = User::factory()->create();
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR']);
        $e2 = Event::factory()->for($user)->create(['currency' => 'PKR']);

        $response = $this->actingAs($user)->post(route('event-groups.store'), [
            'name' => 'Wedding Weekend',
            'group_type' => 'Wedding',
            'event_ids' => [$e1->id, $e2->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('event_groups', ['name' => 'Wedding Weekend']);
        $this->assertEquals($e1->fresh()->event_group_id, $e2->fresh()->event_group_id);
        $this->assertNotNull($e1->fresh()->event_group_id);
    }

    public function test_group_requires_at_least_two_events(): void
    {
        $user = User::factory()->create();
        $e1 = Event::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('event-groups.store'), [
            'name' => 'Solo',
            'group_type' => 'Custom',
            'custom_group_type' => 'Solo',
            'event_ids' => [$e1->id],
        ]);

        $response->assertSessionHasErrors('event_ids');
        $this->assertDatabaseMissing('event_groups', ['name' => 'Solo']);
    }

    public function test_events_with_different_currencies_cannot_be_grouped(): void
    {
        $user = User::factory()->create();
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR']);
        $e2 = Event::factory()->for($user)->create(['currency' => 'USD']);

        $response = $this->actingAs($user)->post(route('event-groups.store'), [
            'name' => 'Mixed Currency',
            'group_type' => 'Custom',
            'custom_group_type' => 'Mixed',
            'event_ids' => [$e1->id, $e2->id],
        ]);

        $response->assertSessionHasErrors('event_ids');
    }

    public function test_an_event_already_in_a_group_cannot_join_another(): void
    {
        $user = User::factory()->create();
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR']);
        $e2 = Event::factory()->for($user)->create(['currency' => 'PKR']);
        $e3 = Event::factory()->for($user)->create(['currency' => 'PKR']);

        $this->actingAs($user)->post(route('event-groups.store'), [
            'name' => 'Group A',
            'group_type' => 'Custom',
            'custom_group_type' => 'A',
            'event_ids' => [$e1->id, $e2->id],
        ]);

        $response = $this->actingAs($user)->post(route('event-groups.store'), [
            'name' => 'Group B',
            'group_type' => 'Custom',
            'custom_group_type' => 'B',
            'event_ids' => [$e1->id, $e3->id],
        ]);

        $response->assertSessionHasErrors('event_ids');
    }

    public function test_group_overview_page_loads_with_combined_totals(): void
    {
        $user = User::factory()->create();
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 10000, 'budget_spent' => 2000]);
        $e2 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 5000, 'budget_spent' => 1000]);

        $this->actingAs($user)->post(route('event-groups.store'), [
            'name' => 'Combo',
            'group_type' => 'Custom',
            'custom_group_type' => 'Combo',
            'event_ids' => [$e1->id, $e2->id],
        ]);

        $group = \App\Models\EventGroup::where('name', 'Combo')->firstOrFail();

        $response = $this->actingAs($user)->get(route('event-groups.show', $group));

        $response->assertOk();
        $response->assertSee('15,000');
        $response->assertSee('3,000');
    }

    public function test_removing_an_event_returns_it_to_standalone_status_with_data_intact(): void
    {
        $user = User::factory()->create();
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 10000]);
        $e2 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 5000]);

        $this->actingAs($user)->post(route('event-groups.store'), [
            'name' => 'Combo',
            'group_type' => 'Custom',
            'custom_group_type' => 'Combo',
            'event_ids' => [$e1->id, $e2->id],
        ]);

        $group = \App\Models\EventGroup::where('name', 'Combo')->firstOrFail();

        $this->actingAs($user)->delete(route('event-groups.events.detach', [$group, $e1]));

        $e1->refresh();
        $this->assertNull($e1->event_group_id);
        $this->assertEquals(10000, $e1->total_budget);
    }

    public function test_dissolving_a_group_returns_every_event_to_standalone_with_no_data_loss(): void
    {
        $user = User::factory()->create();
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR']);
        $e2 = Event::factory()->for($user)->create(['currency' => 'PKR']);

        $this->actingAs($user)->post(route('event-groups.store'), [
            'name' => 'Combo',
            'group_type' => 'Custom',
            'custom_group_type' => 'Combo',
            'event_ids' => [$e1->id, $e2->id],
        ]);

        $group = \App\Models\EventGroup::where('name', 'Combo')->firstOrFail();

        $this->actingAs($user)->delete(route('event-groups.destroy', $group));

        $this->assertDatabaseMissing('event_groups', ['id' => $group->id]);
        $this->assertNull($e1->fresh()->event_group_id);
        $this->assertNull($e2->fresh()->event_group_id);
    }

    public function test_a_group_not_owned_by_the_user_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $e1 = Event::factory()->for($owner)->create(['currency' => 'PKR']);
        $e2 = Event::factory()->for($owner)->create(['currency' => 'PKR']);

        $this->actingAs($owner)->post(route('event-groups.store'), [
            'name' => 'Private Group',
            'group_type' => 'Custom',
            'custom_group_type' => 'Private',
            'event_ids' => [$e1->id, $e2->id],
        ]);

        $group = \App\Models\EventGroup::where('name', 'Private Group')->firstOrFail();

        $this->actingAs($intruder)->get(route('event-groups.show', $group))->assertForbidden();
    }
}
