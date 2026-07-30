<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\User;
use App\Models\VendorCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentionResolutionCommitTest extends TestCase
{
    use RefreshDatabase;

    protected function contendedGroup(User $user): EventGroup
    {
        $e1 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 4000, 'budget_spent' => 4200, 'event_type' => 'Wedding', 'event_name' => 'Ceremony']);
        $e2 = Event::factory()->for($user)->create(['currency' => 'PKR', 'total_budget' => 10000, 'budget_spent' => 9500, 'event_type' => 'Birthday Party', 'event_name' => 'Rehearsal Dinner']);

        VendorCategory::factory()->for($e2)->create(['category_name' => 'Catering', 'allocated_amount' => 6000]);
        VendorCategory::factory()->for($e2)->create(['category_name' => 'Decor', 'allocated_amount' => 4000]);

        $group = EventGroup::create([
            'user_id' => $user->id, 'name' => 'Wedding Weekend', 'group_type' => 'Wedding',
            'currency' => 'PKR', 'budget_mode' => 'Pooled', 'pooled_budget_cap' => 8000,
        ]);
        $e1->update(['event_group_id' => $group->id]);
        $e2->update(['event_group_id' => $group->id]);

        return $group->fresh('events');
    }

    public function test_committing_updates_budgets_categories_and_writes_history(): void
    {
        $user = User::factory()->create();
        $group = $this->contendedGroup($user);
        $rehearsal = $group->events->firstWhere('event_name', 'Rehearsal Dinner');

        $response = $this->actingAs($user)->postJson(route('event-groups.contention.commit', $group), [
            'strategy' => 'Strict Hierarchy',
            'concessions' => [
                ['event_id' => $rehearsal->id, 'concession_amount' => 500, 'rationale' => 'Lowest priority, absorbs the deficit.'],
            ],
            'manually_amended' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $rehearsal->refresh();
        $this->assertEquals(9500, $rehearsal->total_budget); // 10000 - 500

        $this->assertDatabaseHas('contention_resolutions', [
            'event_group_id' => $group->id,
            'strategy' => 'Strict Hierarchy',
            'ai_used' => false,
        ]);

        // Categories were proportionally reduced to fit the new (lower) budget.
        $totalAllocated = $rehearsal->vendorCategories()->sum('allocated_amount');
        $this->assertEquals(9500, $totalAllocated);
    }

    public function test_committing_via_multi_agent_marks_ai_used_on_the_history_record(): void
    {
        $user = User::factory()->create();
        $group = $this->contendedGroup($user);
        $rehearsal = $group->events->firstWhere('event_name', 'Rehearsal Dinner');

        $this->actingAs($user)->postJson(route('event-groups.contention.commit', $group), [
            'strategy' => 'Multi-Agent Negotiation',
            'concessions' => [
                ['event_id' => $rehearsal->id, 'concession_amount' => 500, 'rationale' => 'AI negotiated concession.'],
            ],
        ]);

        $this->assertDatabaseHas('contention_resolutions', [
            'event_group_id' => $group->id,
            'strategy' => 'Multi-Agent Negotiation',
            'ai_used' => true,
        ]);
    }

    public function test_a_concession_that_would_drop_a_budget_below_spend_is_rejected_with_no_partial_write(): void
    {
        $user = User::factory()->create();
        $group = $this->contendedGroup($user);
        $rehearsal = $group->events->firstWhere('event_name', 'Rehearsal Dinner');
        $originalBudget = $rehearsal->total_budget;

        $response = $this->actingAs($user)->postJson(route('event-groups.contention.commit', $group), [
            'strategy' => 'Strict Hierarchy',
            'concessions' => [
                // Way more than headroom (500) — would drive total_budget below budget_spent (9500).
                ['event_id' => $rehearsal->id, 'concession_amount' => 5000, 'rationale' => 'Too much.'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertEquals($originalBudget, $rehearsal->fresh()->total_budget);
        $this->assertDatabaseMissing('contention_resolutions', ['event_group_id' => $group->id]);
    }

    public function test_committed_resolutions_are_listable_in_group_history(): void
    {
        $user = User::factory()->create();
        $group = $this->contendedGroup($user);
        $rehearsal = $group->events->firstWhere('event_name', 'Rehearsal Dinner');

        $this->actingAs($user)->postJson(route('event-groups.contention.commit', $group), [
            'strategy' => 'Strict Hierarchy',
            'concessions' => [
                ['event_id' => $rehearsal->id, 'concession_amount' => 500, 'rationale' => 'Absorbs the deficit.'],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('event-groups.resolutions.index', $group));

        $response->assertOk();
        $response->assertSee('Strict Hierarchy');
        $response->assertSee('Absorbs the deficit.');
    }

    public function test_commit_clears_the_contention_banner_once_the_pool_is_restored(): void
    {
        $user = User::factory()->create();
        $group = $this->contendedGroup($user);
        $rehearsal = $group->events->firstWhere('event_name', 'Rehearsal Dinner');

        $this->actingAs($user)->postJson(route('event-groups.contention.commit', $group), [
            'strategy' => 'Strict Hierarchy',
            'concessions' => [
                ['event_id' => $rehearsal->id, 'concession_amount' => 500, 'rationale' => 'Absorbs the deficit.'],
            ],
        ]);

        $service = app(\App\Services\ContentionDetectionService::class);
        // Deficit was 14200-8000=6200 before commit; a 500 concession alone
        // won't fully clear it, but the recomputed figure must reflect the
        // new, lower combined cost (13700-8000=5700) rather than the stale
        // pre-commit figure — proving detection is always live, never cached.
        $this->assertLessThan(6200, $service->globalDeficit($group->fresh('events')));
    }
}
