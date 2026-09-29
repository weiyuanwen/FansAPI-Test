<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use App\Models\Profile;

class FansApiMetricsService
{
    public const KEY_SUCCESS = 'metrics:fansapi:success_count';
    public const KEY_ATTEMPTS = 'metrics:fansapi:attempts_count';
    public const KEY_FAILURES = 'metrics:fansapi:failure_count';

    public static function recordAttempt(string $username): void
    {
        try {
            Redis::incr(self::KEY_ATTEMPTS);
        } catch (\Throwable) {
        }
    }

    public static function recordSuccess(string $username, int $likes, int $revision): void
    {
        try {
            Redis::incr(self::KEY_SUCCESS);
        } catch (\Throwable) {
        }
    }

    public static function recordFailure(string $username, string $reason): void
    {
        try {
            Redis::incr(self::KEY_FAILURES);
        } catch (\Throwable) {
        }
    }

    /**
     * Compute production observability metrics required by FansAPI spec:
     * 1. Successful refreshes count
     * 2. Total attempts
     * 3. Attempts per successful refresh ratio
     * 4. Oldest waiting job age in seconds
     */
    public static function getMetricsSummary(): array
    {
        $successes = 0;
        $attempts = 0;
        $failures = 0;

        try {
            $successes = (int) (Redis::get(self::KEY_SUCCESS) ?: 0);
            $attempts = (int) (Redis::get(self::KEY_ATTEMPTS) ?: 0);
            $failures = (int) (Redis::get(self::KEY_FAILURES) ?: 0);
        } catch (\Throwable) {
            // Fallback to database aggregate if Redis is unavailable
            $successes = Profile::whereNotNull('last_successful_refresh_at')->count();
            $attempts = (int) Profile::sum('attempt_count');
            $failures = Profile::whereNotNull('last_failed_at')->count();
        }

        $attemptsPerSuccess = $successes > 0 
            ? round($attempts / $successes, 2) 
            : ($attempts > 0 ? (float) $attempts : 1.0);

        // Compute oldest waiting job age from database or queue
        $oldestDueProfile = Profile::dueForRefresh()->orderBy('next_refresh_at', 'asc')->first();
        $oldestWaitingAgeSec = 0;
        if ($oldestDueProfile && $oldestDueProfile->next_refresh_at) {
            $diff = now()->diffInSeconds($oldestDueProfile->next_refresh_at, false);
            $oldestWaitingAgeSec = $diff < 0 ? abs($diff) : 0;
        }

        return [
            'successful_refreshes'          => $successes,
            'total_attempts'                => $attempts,
            'failed_attempts'               => $failures,
            'attempts_per_successful_refresh' => $attemptsPerSuccess,
            'oldest_waiting_job_age_sec'    => $oldestWaitingAgeSec,
        ];
    }
}
