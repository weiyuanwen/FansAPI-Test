<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\RedisLeakyBucketRateLimiter;
use Illuminate\Support\Facades\Redis;

class RedisLeakyBucketRateLimiterTest extends TestCase
{
    public function test_leaky_bucket_allows_requests_within_burst_capacity(): void
    {
        // Mock Redis eval returning {1, 9, "0"} -> allowed = true, remaining = 9, wait_time = 0
        Redis::shouldReceive('eval')
            ->once()
            ->with(
                \Mockery::type('string'),
                1,
                'leaky_bucket:account:madison420ivy',
                10,
                5.0,
                \Mockery::type('float')
            )
            ->andReturn([1, 9, 0]);

        $limiter = new RedisLeakyBucketRateLimiter();
        $decision = $limiter->acquire('account:madison420ivy', 10, 5.0);

        $this->assertTrue($decision['allowed']);
        $this->assertEquals(9, $decision['remaining']);
        $this->assertEquals(0.0, $decision['retry_after_sec']);
    }

    public function test_leaky_bucket_rejects_and_provides_wait_time_when_capacity_exceeded(): void
    {
        // Redis returns string representation for Lua floats to preserve fractional precision
        Redis::shouldReceive('eval')
            ->once()
            ->with(
                \Mockery::type('string'),
                1,
                'leaky_bucket:account:busy_creator',
                10,
                5.0,
                \Mockery::type('float')
            )
            ->andReturn([0, 0, '0.400']);

        $limiter = new RedisLeakyBucketRateLimiter();
        $decision = $limiter->acquire('account:busy_creator', 10, 5.0);

        $this->assertFalse($decision['allowed']);
        $this->assertEquals(0, $decision['remaining']);
        $this->assertEquals(0.4, $decision['retry_after_sec']);
    }

    public function test_leaky_bucket_isolates_by_account_or_proxy_key(): void
    {
        // Account A is full (rejected)
        Redis::shouldReceive('eval')
            ->once()
            ->with(\Mockery::type('string'), 1, 'leaky_bucket:account:A', 5, 2.0, \Mockery::type('float'))
            ->andReturn([0, 0, '1.500']);

        // Account B is fresh (allowed)
        Redis::shouldReceive('eval')
            ->once()
            ->with(\Mockery::type('string'), 1, 'leaky_bucket:account:B', 5, 2.0, \Mockery::type('float'))
            ->andReturn([1, 4, 0]);

        $limiter = new RedisLeakyBucketRateLimiter();

        $decisionA = $limiter->acquire('account:A', 5, 2.0);
        $decisionB = $limiter->acquire('account:B', 5, 2.0);

        $this->assertFalse($decisionA['allowed']);
        $this->assertTrue($decisionB['allowed']);
    }

    public function test_leaky_bucket_real_redis_evaluation_preserves_float_retry_after(): void
    {
        try {
            Redis::ping();
        } catch (\Throwable) {
            $this->markTestSkipped('Redis server not available for integration test');
        }

        $limiter = new RedisLeakyBucketRateLimiter();
        $bucketId = 'test_real_bucket_' . uniqid();

        // Capacity 2, rate 1.0 req/sec
        $d1 = $limiter->acquire($bucketId, 2, 1.0);
        $d2 = $limiter->acquire($bucketId, 2, 1.0);
        $d3 = $limiter->acquire($bucketId, 2, 1.0);

        $this->assertTrue($d1['allowed']);
        $this->assertTrue($d2['allowed']);
        $this->assertFalse($d3['allowed'], 'Third request must exceed capacity 2');
        $this->assertGreaterThan(0.0, $d3['retry_after_sec'], 'retry_after_sec must be non-zero float (proves no integer RESP truncation)');
    }
}
