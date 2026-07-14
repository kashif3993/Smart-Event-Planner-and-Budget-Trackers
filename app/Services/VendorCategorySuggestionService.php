<?php

namespace App\Services;

use App\Models\Event;

class VendorCategorySuggestionService
{
    /**
     * Suggested vendor categories per event type, as a percentage of total budget.
     * Each list sums to 100.
     *
     * @var array<string, array<int, array{name: string, percent: int}>>
     */
    protected const SUGGESTIONS = [
        'Wedding' => [
            ['name' => 'Venue', 'percent' => 25],
            ['name' => 'Catering', 'percent' => 30],
            ['name' => 'Photography & Videography', 'percent' => 15],
            ['name' => 'Decor & Flowers', 'percent' => 12],
            ['name' => 'Entertainment', 'percent' => 8],
            ['name' => 'Attire & Beauty', 'percent' => 6],
            ['name' => 'Transport', 'percent' => 4],
        ],
        'Birthday Party' => [
            ['name' => 'Venue', 'percent' => 20],
            ['name' => 'Catering', 'percent' => 30],
            ['name' => 'Decor', 'percent' => 15],
            ['name' => 'Entertainment', 'percent' => 20],
            ['name' => 'Photography', 'percent' => 10],
            ['name' => 'Favors & Gifts', 'percent' => 5],
        ],
        'Corporate Event' => [
            ['name' => 'Venue', 'percent' => 25],
            ['name' => 'Catering', 'percent' => 20],
            ['name' => 'AV & Technology', 'percent' => 20],
            ['name' => 'Marketing & Signage', 'percent' => 15],
            ['name' => 'Speaker / Talent Fees', 'percent' => 10],
            ['name' => 'Transport & Logistics', 'percent' => 10],
        ],
        'Baby Shower' => [
            ['name' => 'Venue', 'percent' => 15],
            ['name' => 'Catering', 'percent' => 30],
            ['name' => 'Decor', 'percent' => 20],
            ['name' => 'Favors & Gifts', 'percent' => 15],
            ['name' => 'Entertainment & Games', 'percent' => 10],
            ['name' => 'Photography', 'percent' => 10],
        ],
        'Graduation' => [
            ['name' => 'Venue', 'percent' => 20],
            ['name' => 'Catering', 'percent' => 30],
            ['name' => 'Decor', 'percent' => 15],
            ['name' => 'Photography', 'percent' => 15],
            ['name' => 'Entertainment', 'percent' => 10],
            ['name' => 'Invitations & Printing', 'percent' => 10],
        ],
    ];

    /**
     * Fallback set for "Custom" or any event type not listed above.
     *
     * @var array<int, array{name: string, percent: int}>
     */
    protected const DEFAULT_SUGGESTIONS = [
        ['name' => 'Venue', 'percent' => 25],
        ['name' => 'Catering', 'percent' => 25],
        ['name' => 'Decor', 'percent' => 15],
        ['name' => 'Entertainment', 'percent' => 15],
        ['name' => 'Photography', 'percent' => 10],
        ['name' => 'Miscellaneous', 'percent' => 10],
    ];

    /**
     * Create the suggested vendor categories for a newly created event, with
     * allocated_amount pre-computed from the event's total_budget.
     */
    public function suggestFor(Event $event): void
    {
        $suggestions = self::SUGGESTIONS[$event->event_type] ?? self::DEFAULT_SUGGESTIONS;
        $totalBudget = (float) $event->total_budget;

        foreach ($suggestions as $index => $suggestion) {
            $event->vendorCategories()->create([
                'category_name' => $suggestion['name'],
                'suggested_percentage' => $suggestion['percent'],
                'allocated_amount' => round($totalBudget * $suggestion['percent'] / 100, 2),
                'ai_slash_priority' => $index + 1,
            ]);
        }
    }
}
