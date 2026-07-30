<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_group_id',
        'event_name',
        'event_type',
        'custom_event_type',
        'event_date',
        'event_time',
        'guest_count',
        'max_guests',
        'venue_name',
        'location',
        'venue_image',
        'total_budget',
        'budget_spent',
        'currency',
        'description',
        'status',
        'ai_insight',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'total_budget' => 'decimal:2',
            'budget_spent' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventGroup(): BelongsTo
    {
        return $this->belongsTo(EventGroup::class);
    }

    public function guests(): BelongsToMany
    {
        return $this->belongsToMany(Guest::class, 'event_guest')->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function vendorCategories(): HasMany
    {
        return $this->hasMany(VendorCategory::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function getDaysRemainingAttribute(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->event_date, false);
    }

    public function getBudgetRemainingAttribute(): float
    {
        return (float) $this->total_budget - (float) $this->budget_spent;
    }

    public function getBudgetPercentAttribute(): int
    {
        if ((float) $this->total_budget <= 0) {
            return 0;
        }

        return (int) min(100, round(((float) $this->budget_spent / (float) $this->total_budget) * 100));
    }

    public function getGuestPercentAttribute(): int
    {
        if (! $this->max_guests) {
            return 0;
        }

        return (int) min(100, round(($this->guest_count / $this->max_guests) * 100));
    }

    public static function abbreviateMoney(float $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        $amount = abs($amount);

        if ($amount >= 1_000_000) {
            return $sign.round($amount / 1_000_000, 1).'M';
        }

        if ($amount >= 1_000) {
            return $sign.round($amount / 1_000, 1).'k';
        }

        return $sign.number_format($amount, 0);
    }

    public function currencySymbol(): string
    {
        return $this->currency === 'USD' ? '$' : 'PKR ';
    }

    public static function budgetStatusLabel(float $percent): string
    {
        if ($percent > 100) {
            return 'Over Budget';
        }

        return $percent >= 90 ? 'On Track' : 'Under Budget';
    }
}
