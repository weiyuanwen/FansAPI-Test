<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Profile extends Model
{
    use Searchable;

    protected $fillable = [
        'username',
        'display_name',
        'avatar_url',
        'likes',
        'revision',
        'last_attempted_at',
        'last_successful_refresh_at',
        'last_failed_at',
        'last_failure_reason',
        'attempt_count',
        'next_refresh_at',
    ];

    protected $casts = [
        'likes'                      => 'integer',
        'revision'                   => 'integer',
        'attempt_count'              => 'integer',
        'last_attempted_at'          => 'datetime',
        'last_successful_refresh_at' => 'datetime',
        'last_failed_at'             => 'datetime',
        'next_refresh_at'            => 'datetime',
    ];

    /**
     * Refresh profiles above 100,000 likes every 24 hours and all others every 72 hours.
     * Exactly 100,000 belongs to the 72-hour group.
     */
    public function calculateIntervalForLikes(int $likes): int
    {
        return $likes > 100000 ? 24 : 72;
    }

    public function toSearchableArray(): array
    {
        return [
            'id'           => (int) $this->id,
            'username'     => $this->username,
            'display_name' => $this->display_name,
            'likes'        => (int) $this->likes,
        ];
    }
}
