<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_id',
        'type',
        'description',
        'icon',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * The user who performed this activity.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The event this activity is related to (optional).
     */
    public function event()
    {
        return $this->belongsTo(Event::class)->withDefault();
    }

    /**
     * Scope: latest activities first.
     */
    public function scopeRecent($query, int $limit = 20)
    {
        return $query->latest()->limit($limit);
    }

    /**
     * Scope: activities for the logged-in user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
