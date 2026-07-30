<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\User;
use App\Services\StrictHierarchyScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class StrictHierarchyScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StrictHierarchyScoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StrictHierarchyScoringService();
    }

    public function test_score_is_weight_divided_by_days_remaining(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(3)->toDateString(),
        ]);

        // Wedding weight 3 / 3 days remaining = 1.0
        $this->assertEqualsWithDelta(1.0, $this->service->score($event), 0.05);
    }

    public function test_runway_is_floored_at_one_day_for_past_or_today_events(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'event_type' => 'Wedding',
            'event_date' => now()->subDays(2)->toDateString(),
        ]);

        // Negative runway floors to 1 day, so score = weight / 1 = 3 (maximally protected, not undefined).
        $this->assertEquals(3.0, $this->service->score($event));
    }

    public function test_lower_score_ranks_first(): void
    {
        $user = User::factory()->create();
        $soon = Event::factory()->for($user)->create(['event_type' => 'Birthday Party', 'event_date' => now()->addDays(2)->toDateString()]);
        $far = Event::factory()->for($user)->create(['event_type' => 'Birthday Party', 'event_date' => now()->addDays(60)->toDateString()]);

        $ranked = $this->service->rank(new Collection([$soon, $far]));

        // Far-out birthday (low score, weight 1/60) concedes first (rank 1); the near one is protected.
        $this->assertEquals($far->id, $ranked->first()['event']->id);
        $this->assertEquals($soon->id, $ranked->last()['event']->id);
    }

    public function test_immune_events_are_excluded_entirely_from_ranking(): void
    {
        $user = User::factory()->create();
        $e1 = Event::factory()->for($user)->create(['event_type' => 'Custom', 'event_date' => now()->addDays(10)->toDateString()]);
        $e2 = Event::factory()->for($user)->create(['event_type' => 'Custom', 'event_date' => now()->addDays(20)->toDateString()]);

        $ranked = $this->service->rank(new Collection([$e1, $e2]), [$e1->id]);

        $this->assertCount(1, $ranked);
        $this->assertEquals($e2->id, $ranked->first()['event']->id);
    }

    public function test_ties_are_broken_by_later_event_date(): void
    {
        $user = User::factory()->create();
        $earlier = Event::factory()->for($user)->create(['event_type' => 'Custom', 'event_date' => now()->addDays(10)->toDateString()]);
        $later = Event::factory()->for($user)->create(['event_type' => 'Custom', 'event_date' => now()->addDays(10)->toDateString()]);

        $ranked = $this->service->rank(new Collection([$earlier, $later]));

        // Equal scores (same weight, same runway) — the later event_date concedes first.
        $this->assertEquals($ranked->first()['score'], $ranked->last()['score']);
    }

    public function test_cascade_moves_to_next_event_when_first_cannot_cover_full_deficit(): void
    {
        $user = User::factory()->create();
        // Lower score (far out) concedes first but has little headroom; remainder cascades.
        $first = Event::factory()->for($user)->create([
            'event_type' => 'Custom', 'event_date' => now()->addDays(100)->toDateString(),
            'total_budget' => 1000, 'budget_spent' => 900,
        ]);
        $second = Event::factory()->for($user)->create([
            'event_type' => 'Custom', 'event_date' => now()->addDays(50)->toDateString(),
            'total_budget' => 5000, 'budget_spent' => 1000,
        ]);

        $result = $this->service->cascadeConcessions(new Collection([$first, $second]), 500);

        $this->assertEquals(0, $result['residual']);
        $this->assertEquals(100, $result['concessions'][0]['concession']); // first's headroom is only 100
        $this->assertEquals(400, $result['concessions'][1]['concession']); // remainder cascades to second
    }

    public function test_residual_is_reported_when_no_one_can_cover_the_full_deficit(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'event_type' => 'Custom', 'event_date' => now()->addDays(10)->toDateString(),
            'total_budget' => 1000, 'budget_spent' => 900,
        ]);

        $result = $this->service->cascadeConcessions(new Collection([$event]), 5000);

        $this->assertEquals(4900, $result['residual']);
        $this->assertEquals(100, $result['concessions'][0]['concession']);
    }
}
