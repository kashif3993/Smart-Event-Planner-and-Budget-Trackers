<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\User;
use App\Services\ContentionDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentionDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function pooledGroup(User $user, array $eventAttrs): EventGroup
    {
        $events = collect($eventAttrs)->map(fn ($attrs) => Event::factory()->for($user)->create([
            'currency' => 'PKR',
            ...$attrs,
        ]));

        $group = EventGroup::create([
            'user_id' => $user->id,
            'name' => 'Pooled Group',
            'group_type' => 'Custom',
            'custom_group_type' => 'Test',
            'currency' => 'PKR',
            'budget_mode' => 'Pooled',
            'pooled_budget_cap' => 10000,
        ]);

        $events->each(fn (Event $e) => $e->update(['event_group_id' => $group->id]));

        return $group->fresh('events');
    }

    public function test_cap_breach_with_two_events_over_is_contended(): void
    {
        $user = User::factory()->create();
        $group = $this->pooledGroup($user, [
            ['total_budget' => 4000, 'budget_spent' => 5000], // over
            ['total_budget' => 4000, 'budget_spent' => 5500], // over
        ]);

        $service = app(ContentionDetectionService::class);

        $this->assertTrue($service->isContended($group));
        $this->assertGreaterThan(0, $service->globalDeficit($group));
    }

    public function test_cap_breach_with_only_one_event_over_is_not_contended(): void
    {
        $user = User::factory()->create();
        $group = $this->pooledGroup($user, [
            ['total_budget' => 4000, 'budget_spent' => 9000], // way over, alone
            ['total_budget' => 4000, 'budget_spent' => 1000], // healthy
        ]);

        $service = app(ContentionDetectionService::class);

        $this->assertFalse($service->isContended($group));
        $this->assertNotNull($service->singleBreachingEvent($group));
    }

    public function test_pool_within_cap_is_not_contended(): void
    {
        $user = User::factory()->create();
        $group = $this->pooledGroup($user, [
            ['total_budget' => 4000, 'budget_spent' => 3000],
            ['total_budget' => 4000, 'budget_spent' => 3000],
        ]);

        $service = app(ContentionDetectionService::class);

        $this->assertFalse($service->isContended($group));
    }

    public function test_distributed_mode_group_never_contends(): void
    {
        $user = User::factory()->create();
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 4000, 'budget_spent' => 8000]);
        $e2 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 4000, 'budget_spent' => 8000]);

        $group = EventGroup::create([
            'user_id' => $user->id,
            'name' => 'Distributed Group',
            'group_type' => 'Custom',
            'custom_group_type' => 'Test',
            'currency' => 'PKR',
            'budget_mode' => 'Distributed',
        ]);
        $e1->update(['event_group_id' => $group->id]);
        $e2->update(['event_group_id' => $group->id]);

        $service = app(ContentionDetectionService::class);

        $this->assertFalse($service->isContended($group->fresh('events')));
    }

    public function test_contention_clears_automatically_once_spend_is_brought_back_under_cap(): void
    {
        $user = User::factory()->create();
        $group = $this->pooledGroup($user, [
            ['total_budget' => 4000, 'budget_spent' => 5000],
            ['total_budget' => 4000, 'budget_spent' => 5500],
        ]);

        $service = app(ContentionDetectionService::class);
        $this->assertTrue($service->isContended($group));

        $group->events->first()->update(['budget_spent' => 1000]);

        $this->assertFalse($service->isContended($group->fresh('events')));
    }

    public function test_dashboard_shows_a_critical_banner_when_a_group_is_contended(): void
    {
        $user = User::factory()->create();
        $this->pooledGroup($user, [
            ['total_budget' => 4000, 'budget_spent' => 5000],
            ['total_budget' => 4000, 'budget_spent' => 5500],
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Budget contention');
    }
}
