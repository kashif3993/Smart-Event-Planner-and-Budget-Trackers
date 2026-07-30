<?php

namespace Database\Factories;

use App\Models\EventGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventGroup>
 */
class EventGroupFactory extends Factory
{
    protected $model = EventGroup::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->sentence(2),
            'description' => fake()->sentence(),
            'group_type' => fake()->randomElement([
                'Wedding', 'Birthday Party', 'Corporate Event', 'Baby Shower', 'Graduation', 'Custom',
            ]),
            'custom_group_type' => null,
            'start_date' => null,
            'end_date' => null,
            'currency' => 'PKR',
            'budget_mode' => 'Distributed',
            'pooled_budget_cap' => null,
            'status' => 'Active',
            'archived_at' => null,
        ];
    }
}
