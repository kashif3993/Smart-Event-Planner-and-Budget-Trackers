<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $totalBudget = fake()->randomFloat(2, 50000, 500000);

        return [
            'user_id' => User::factory(),
            'event_group_id' => null,
            'event_name' => fake()->sentence(3),
            'event_type' => fake()->randomElement([
                'Wedding', 'Birthday Party', 'Corporate Event', 'Baby Shower', 'Graduation', 'Custom',
            ]),
            'custom_event_type' => null,
            'event_date' => fake()->dateTimeBetween('+1 week', '+6 months')->format('Y-m-d'),
            'event_time' => '18:00:00',
            'guest_count' => fake()->numberBetween(10, 200),
            'max_guests' => fake()->numberBetween(200, 400),
            'venue_name' => fake()->company(),
            'location' => fake()->city(),
            'venue_image' => null,
            'total_budget' => $totalBudget,
            'budget_spent' => 0,
            'currency' => fake()->randomElement(['PKR', 'USD']),
            'description' => fake()->sentence(),
            'status' => 'Planning',
            'ai_insight' => null,
        ];
    }
}
