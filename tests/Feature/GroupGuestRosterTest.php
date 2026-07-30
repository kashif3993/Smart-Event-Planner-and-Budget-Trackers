<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupGuestRosterTest extends TestCase
{
    use RefreshDatabase;

    protected function makeGroup(User $user): EventGroup
    {
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR']);
        $e2 = Event::factory()->for($user)->create(['currency' => 'PKR']);

        $this->actingAs($user)->post(route('event-groups.store'), [
            'name' => 'Combo',
            'group_type' => 'Custom',
            'custom_group_type' => 'Combo',
            'event_ids' => [$e1->id, $e2->id],
        ]);

        return EventGroup::where('name', 'Combo')->firstOrFail();
    }

    public function test_adding_a_guest_attaches_them_to_selected_events(): void
    {
        $user = User::factory()->create();
        $group = $this->makeGroup($user);
        $event = $group->events->first();

        $this->actingAs($user)->post(route('event-groups.guests.store', $group), [
            'name' => 'Ayesha Khan',
            'event_ids' => [$event->id],
        ]);

        $this->assertDatabaseHas('guests', ['name' => 'Ayesha Khan']);
        $guest = Guest::where('name', 'Ayesha Khan')->firstOrFail();
        $this->assertEquals(1, $guest->events()->count());
    }

    public function test_same_name_guests_across_sub_events_surface_as_duplicate_candidates(): void
    {
        $user = User::factory()->create();
        $group = $this->makeGroup($user);
        [$e1, $e2] = $group->events;

        $this->actingAs($user)->post(route('event-groups.guests.store', $group), ['name' => 'Ali Raza', 'event_ids' => [$e1->id]]);
        $this->actingAs($user)->post(route('event-groups.guests.store', $group), ['name' => 'Ali Raza', 'event_ids' => [$e2->id]]);

        $response = $this->actingAs($user)->get(route('event-groups.guests', $group));

        $response->assertOk();
        $response->assertSee('Possible Duplicates');
    }

    public function test_merging_repoints_attendance_and_deletes_the_duplicate(): void
    {
        $user = User::factory()->create();
        $group = $this->makeGroup($user);
        [$e1, $e2] = $group->events;

        $g1 = Guest::factory()->for($user)->create(['name' => 'Bilal Ahmed']);
        $g1->events()->attach($e1->id);
        $g2 = Guest::factory()->for($user)->create(['name' => 'Bilal Ahmed']);
        $g2->events()->attach($e2->id);

        $this->actingAs($user)->post(route('event-groups.guests.merge', $group), [
            'survivor_id' => $g1->id,
            'duplicate_id' => $g2->id,
        ]);

        $this->assertDatabaseMissing('guests', ['id' => $g2->id]);
        $this->assertEquals(2, $g1->fresh()->events()->count());
    }

    public function test_dismissing_a_duplicate_pair_stops_it_resurfacing(): void
    {
        $user = User::factory()->create();
        $group = $this->makeGroup($user);
        [$e1, $e2] = $group->events;

        $g1 = Guest::factory()->for($user)->create(['name' => 'Same Name']);
        $g1->events()->attach($e1->id);
        $g2 = Guest::factory()->for($user)->create(['name' => 'Same Name']);
        $g2->events()->attach($e2->id);

        $this->actingAs($user)->post(route('event-groups.guests.dismissDuplicate', $group), [
            'guest_id_a' => $g1->id,
            'guest_id_b' => $g2->id,
        ]);

        $response = $this->actingAs($user)->get(route('event-groups.guests', $group));
        $response->assertDontSee('Possible Duplicates');
    }
}
