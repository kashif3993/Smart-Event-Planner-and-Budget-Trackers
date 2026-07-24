<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorCategory extends Model
{
    protected $fillable = [
        'event_id',
        'category_name',
        'vendor_name',
        'suggested_percentage',
        'allocated_amount',
        'notes',
        'is_locked',
        'ai_slash_priority',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'allocated_amount' => 'decimal:2',
            'suggested_percentage' => 'decimal:2',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }
}
