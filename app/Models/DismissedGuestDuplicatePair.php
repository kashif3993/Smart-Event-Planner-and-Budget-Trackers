<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DismissedGuestDuplicatePair extends Model
{
    protected $fillable = [
        'event_group_id',
        'guest_id_a',
        'guest_id_b',
    ];

    public function eventGroup(): BelongsTo
    {
        return $this->belongsTo(EventGroup::class);
    }
}
