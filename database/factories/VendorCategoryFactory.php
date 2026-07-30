<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\VendorCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorCategory>
 */
class VendorCategoryFactory extends Factory
{
    protected $model = VendorCategory::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'category_name' => fake()->randomElement(['Venue', 'Catering', 'Decor', 'Entertainment', 'Photography']),
            'vendor_name' => fake()->company(),
            'suggested_percentage' => fake()->randomFloat(2, 5, 30),
            'allocated_amount' => fake()->randomFloat(2, 5000, 100000),
            'notes' => null,
            'is_locked' => false,
            'ai_slash_priority' => null,
        ];
    }
}
