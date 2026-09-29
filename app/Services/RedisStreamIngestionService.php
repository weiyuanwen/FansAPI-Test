<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Profile;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * High-Throughput Redis Streams Ingestion Service.
 *
 * Designed for 50M jobs/day (~579 - 2,500 updates/sec).
 * Buffers high-velocity upstream scrape payloads into Redis Streams
 * for micro-batch bulk upsert into PostgreSQL/MySQL, preventing DB write IOPS exhaustion.
 */
class RedisStreamIngestionService
{
    public const DEFAULT_STREAM = 'stream:profile:updates';
    public const DEFAULT_GROUP  = 'group:profile:persisters';
    public const DEFAULT_BATCH  = 100;

    public function __construct(
        protected string $streamKey = self::DEFAULT_STREAM,
        protected string $groupName = self::DEFAULT_GROUP
    ) {}

    /**
     * Append a profile refresh update event into the Redis Stream (XADD).
     */
    public function appendUpdate(string $username, int $likes, int $revision, string $timestamp): string
    {
        $payload = [
            'username'   => $username,
            'likes'      => (string) $likes,
            'revision'   => (string) $revision,
            'timestamp'  => $timestamp,
            'ingested_at'=> microtime(true),
        ];

        // XADD stream:profile:updates * username $username likes $likes ...
        $messageId = Redis::xadd($this->streamKey, '*', $payload);

        Log::info("Profile update queued to stream", [
            'stream'     => $this->streamKey,
            'message_id' => $messageId,
            'username'   => $username,
        ]);

        return (string) $messageId;
    }

    /**
     * Ensure the consumer group exists (XGROUP CREATE ... MKSTREAM).
     */
    public function ensureGroupExists(): void
    {
        try {
            Redis::xgroup('CREATE', $this->streamKey, $this->groupName, '0', 'MKSTREAM');
        } catch (\Throwable $e) {
            // Already exists (BUSYGROUP), safely ignore
        }
    }

    /**
     * Read a micro-batch of messages via Consumer Group (XREADGROUP)
     * and persist using Bulk UPSERT with Monotonic Revision Guard.
     *
     * @return int Number of processed items
     */
    public function processMicroBatch(string $consumerName, int $count = self::DEFAULT_BATCH): int
    {
        $this->ensureGroupExists();

        // XREADGROUP GROUP groupName consumerName COUNT count STREAMS streamKey >
        $entries = Redis::xreadgroup(
            $this->groupName,
            $consumerName,
            [$this->streamKey => '>'],
            $count
        );

        if (empty($entries) || !isset($entries[$this->streamKey])) {
            return 0;
        }

        $messages = $entries[$this->streamKey];
        $ackedIds = [];
        $batchData = [];

        foreach ($messages as $messageId => $fields) {
            $batchData[] = [
                'username'  => $fields['username'],
                'likes'     => (int) $fields['likes'],
                'revision'  => (int) $fields['revision'],
                'updated_at'=> $fields['timestamp'] ?? now()->toIso8601String(),
            ];
            $ackedIds[] = $messageId;
        }

        // Execute atomic bulk upsert with monotonic revision protection
        DB::transaction(function () use ($batchData) {
            foreach ($batchData as $item) {
                DB::statement("
                    INSERT INTO profiles (username, likes, revision, last_successful_refresh_at, updated_at)
                    VALUES (:username, :likes, :revision, :last_refresh, :updated_at)
                    ON CONFLICT (username) DO UPDATE
                    SET likes = EXCLUDED.likes,
                        revision = EXCLUDED.revision,
                        last_successful_refresh_at = EXCLUDED.last_successful_refresh_at,
                        updated_at = EXCLUDED.updated_at
                    WHERE profiles.revision < EXCLUDED.revision
                ", [
                    'username'     => $item['username'],
                    'likes'        => $item['likes'],
                    'revision'     => $item['revision'],
                    'last_refresh' => $item['updated_at'],
                    'updated_at'   => $item['updated_at'],
                ]);
            }
        });

        // Acknowledge processed messages (XACK)
        if (!empty($ackedIds)) {
            Redis::xack($this->streamKey, $this->groupName, $ackedIds);
        }

        return count($ackedIds);
    }

    /**
     * Inspect pending messages (XPENDING) for dead consumer recovery.
     */
    public function getPendingCount(): int
    {
        $info = Redis::xpending($this->streamKey, $this->groupName);
        return isset($info[0]) ? (int) $info[0] : 0;
    }
}
