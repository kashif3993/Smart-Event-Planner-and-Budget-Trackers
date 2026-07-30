<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'group_type',
        'custom_group_type',
        'start_date',
        'end_date',
        'currency',
        'budget_mode',
        'pooled_budget_cap',
        'status',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'pooled_budget_cap' => 'decimal:2',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(ContentionResolution::class)->latest();
    }

    public function dismissedDuplicatePairs(): HasMany
    {
        return $this->hasMany(DismissedGuestDuplicatePair::class);
    }

    public function isPooled(): bool
    {
        return $this->budget_mode === 'Pooled';
    }

    public function isArchived(): bool
    {
        return $this->status === 'Archived';
    }

    public function combinedBudget(): float
    {
        if ($this->isPooled() && $this->pooled_budget_cap !== null) {
            return (float) $this->pooled_budget_cap;
        }

        return (float) $this->events->sum('total_budget');
    }

    public function combinedSpend(): float
    {
        return (float) $this->events->sum('budget_spent');
    }

    public function combinedRemaining(): float
    {
        return $this->combinedBudget() - $this->combinedSpend();
    }

    public function combinedBudgetPercent(): int
    {
        $budget = $this->combinedBudget();

        if ($budget <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->combinedSpend() / $budget) * 100));
    }

    public function healthStatus(): string
    {
        return Event::budgetStatusLabel($this->combinedBudgetPercent());
    }

    public function currencySymbol(): string
    {
        return $this->currency === 'USD' ? '$' : 'PKR ';
    }

    public function displayType(): string
    {
        return $this->group_type === 'Custom' && $this->custom_group_type
            ? $this->custom_group_type
            : $this->group_type;
    }
}
