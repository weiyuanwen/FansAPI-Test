<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Enterprise Redis Leaky Bucket Rate Limiter.
 *
 * Implements smooth traffic shaping to prevent HTTP 429 Too Many Requests
 * when scraping OnlyFans endpoints across rotating IP egress gateways.
 *
 * Mathematical Model:
 *   water = max(0, water - (now - last_leak) * leak_rate)
 *   if water + 1 <= capacity:
 *       allow, water += 1
 *   else:
 *       throttle, wait_time = (water + 1 - capacity) / leak_rate
 */
class RedisLeakyBucketRateLimiter
{
    /**
     * Lua script for atomic, thread-safe evaluation of the leaky bucket.
     */
    protected const LUA_LEAKY_BUCKET = <<<'LUA'
local key = KEYS[1]
local capacity = tonumber(ARGV[1])
local leak_rate = tonumber(ARGV[2])
local now = tonumber(ARGV[3])
local ttl = math.ceil(capacity / leak_rate) * 2

local data = redis.call('HMGET', key, 'water', 'last_leak')
local water = tonumber(data[1]) or 0
local last_leak = tonumber(data[2]) or now

-- Leak water based on elapsed time
local elapsed = math.max(0, now - last_leak)
water = math.max(0, water - (elapsed * leak_rate))

if (water + 1) <= capacity then
    water = water + 1
    redis.call('HMSET', key, 'water', water, 'last_leak', now)
    redis.call('EXPIRE', key, ttl)
    return {1, math.floor(capacity - water), 0} -- {allowed, remaining, wait_time_sec}
else
    local wait_time = (water + 1 - capacity) / leak_rate
    return {0, 0, tostring(wait_time)} -- Return as string to avoid Redis RESP integer truncation of floats
end
LUA;

    /**
     * Attempt to acquire a token from the Leaky Bucket.
     *
     * @param string $bucketId Unique bucket key (e.g. "account:madison420ivy" or "proxy:192.168.1.1")
     * @param int $capacity Burst allowance (maximum bucket volume)
     * @param float $leakRatePerSecond Continuous outflow rate in requests/second
     * @return array{allowed: bool, remaining: int, retry_after_sec: float}
     */
    public function acquire(
        string $bucketId,
        int $capacity = 10,
        float $leakRatePerSecond = 5.0,
        ?float $currentMicrotime = null
    ): array {
        $key = "leaky_bucket:{$bucketId}";
        $now = $currentMicrotime ?? microtime(true);

        $result = Redis::eval(
            self::LUA_LEAKY_BUCKET,
            1,
            $key,
            $capacity,
            $leakRatePerSecond,
            $now
        );

        $allowed = (int) ($result[0] ?? 0) === 1;
        $remaining = (int) ($result[1] ?? 0);
        $retryAfter = (float) ($result[2] ?? 0);

        return [
            'allowed'         => $allowed,
            'remaining'       => $remaining,
            'retry_after_sec' => round($retryAfter, 3),
        ];
    }

    /**
     * Clear / reset a specific bucket state.
     */
    public function reset(string $bucketId): void
    {
        Redis::del("leaky_bucket:{$bucketId}");
    }
}
