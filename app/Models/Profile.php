<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Scout\Searchable;

class Profile extends Model
{
    use Searchable;

    protected $fillable = [
        'username',
        'account_id',
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
     * Delegates to DTO domain logic. Exactly 100,000 belongs to the 72-hour group.
     */
    public static function calculateIntervalForLikes(int $likes): int
    {
        return \App\DTOs\OnlyFansProfilePayload::calculateRefreshInterval($likes);
    }

    /**
     * Scope query to select profiles that are due or overdue for a background refresh.
     */
    public function scopeDueForRefresh(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('next_refresh_at')
              ->orWhere('next_refresh_at', '<=', now());
        });
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
