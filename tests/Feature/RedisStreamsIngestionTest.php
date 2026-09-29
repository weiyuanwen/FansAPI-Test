<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Profile;
use App\Services\RedisStreamIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;

class RedisStreamsIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_is_appended_to_redis_stream(): void
    {
        Redis::shouldReceive('xadd')
            ->once()
            ->with(
                'stream:profile:updates',
                '*',
                \Mockery::on(function ($payload) {
                    return $payload['username'] === 'madison420ivy'
                        && $payload['likes'] === '120000'
                        && $payload['revision'] === '10';
                })
            )
            ->andReturn('1727587200000-0');

        $service = new RedisStreamIngestionService();
        $messageId = $service->appendUpdate('madison420ivy', 120000, 10, '2026-09-29T04:00:00Z');

        $this->assertEquals('1727587200000-0', $messageId);
    }

    public function test_stream_consumer_processes_micro_batch_and_acknowledges(): void
    {
        Redis::shouldReceive('xgroup')
            ->once()
            ->with('CREATE', 'stream:profile:updates', 'group:profile:persisters', '0', 'MKSTREAM')
            ->andReturn(true);

        // Mock 2 stream entries ready for consumption
        Redis::shouldReceive('xreadgroup')
            ->once()
            ->with(
                'group:profile:persisters',
                'worker-node-1',
                ['stream:profile:updates' => '>'],
                100
            )
            ->andReturn([
                'stream:profile:updates' => [
                    '1727587200001-0' => [
                        'username'  => 'madison420ivy',
                        'likes'     => '121000',
                        'revision'  => '11',
                        'timestamp' => '2026-09-29T04:01:00Z',
                    ],
                    '1727587200002-0' => [
                        'username'  => 'gem_superstar',
                        'likes'     => '450000',
                        'revision'  => '5',
                        'timestamp' => '2026-09-29T04:01:00Z',
                    ],
                ],
            ]);

        // Expect XACK acknowledging both message IDs
        Redis::shouldReceive('xack')
            ->once()
            ->with(
                'stream:profile:updates',
                'group:profile:persisters',
                ['1727587200001-0', '1727587200002-0']
            )
            ->andReturn(2);

        $service = new RedisStreamIngestionService();
        $processedCount = $service->processMicroBatch('worker-node-1', 100);

        $this->assertEquals(2, $processedCount);
    }

    public function test_stream_pending_queue_count_inspection(): void
    {
        Redis::shouldReceive('xpending')
            ->once()
            ->with('stream:profile:updates', 'group:profile:persisters')
            ->andReturn([0, '0-0', '0-0', []]);

        $service = new RedisStreamIngestionService();
        $pendingCount = $service->getPendingCount();

        $this->assertEquals(0, $pendingCount);
    }

    public function test_stream_consumer_handles_laravel_prefixed_stream_keys(): void
    {
        Redis::shouldReceive('xgroup')
            ->once()
            ->with('CREATE', 'stream:profile:updates', 'group:profile:persisters', '0', 'MKSTREAM')
            ->andReturn(true);

        // Mock stream entries returned with Laravel database prefix
        Redis::shouldReceive('xreadgroup')
            ->once()
            ->andReturn([
                'laravel_database_stream:profile:updates' => [
                    '1727587200003-0' => [
                        'username'  => 'prefixed_user',
                        'likes'     => '50000',
                        'revision'  => '2',
                        'timestamp' => '2026-09-29T04:05:00Z',
                    ],
                ],
            ]);

        Redis::shouldReceive('xack')
            ->once()
            ->with(
                'stream:profile:updates',
                'group:profile:persisters',
                ['1727587200003-0']
            )
            ->andReturn(1);

        $service = new RedisStreamIngestionService();
        $processed = $service->processMicroBatch('worker-node-prefixed', 100);

        $this->assertEquals(1, $processed, 'Must process entry even when key has Laravel database prefix');
    }
}
