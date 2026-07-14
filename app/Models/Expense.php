<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'event_id',
        'category_id',
        'vendor_item_name',
        'estimated_cost',
        'actual_cost',
        'payment_status',
        'date_logged',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_logged'    => 'date',
            'estimated_cost' => 'decimal:2',
            'actual_cost'    => 'decimal:2',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VendorCategory::class, 'category_id');
    }
}
