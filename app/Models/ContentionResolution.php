<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentionResolution extends Model
{
    protected $fillable = [
        'event_group_id',
        'user_id',
        'strategy',
        'pooled_budget_cap',
        'global_deficit',
        'ai_used',
        'fallback_reason',
        'participating_event_ids',
        'concessions',
    ];

    protected function casts(): array
    {
        return [
            'pooled_budget_cap' => 'decimal:2',
            'global_deficit' => 'decimal:2',
            'ai_used' => 'boolean',
            'participating_event_ids' => 'array',
            'concessions' => 'array',
        ];
    }

    public function eventGroup(): BelongsTo
    {
        return $this->belongsTo(EventGroup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
