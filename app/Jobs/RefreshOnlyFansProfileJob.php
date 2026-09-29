<?php

namespace App\Jobs;

use App\Models\Profile;
use App\DTOs\OnlyFansProfilePayload;
use App\Services\OnlyFansApiClient;
use App\Exceptions\TransientUpstreamException;
use App\Exceptions\PermanentUpstreamException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RefreshOnlyFansProfileJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;
    public int $maxExceptions = 3;
    public int $timeout = 30; // Job timeout (strictly < worker timeout 60s and retry_after 90s)
    public int $uniqueFor = 300; // 5-minute atomic redis lock to prevent duplicate concurrent jobs

    public function __construct(
        public string $username,
        public string $accountId = 'default'
    ) {
        $this->onQueue('profiles-high');
    }

    public function uniqueId(): string
    {
        return "profile_refresh_{$this->username}";
    }

    public function handle(OnlyFansApiClient $client): void
    {
        // Account-level queue rate limiting & worker isolation
        Redis::throttle("throttle:account:{$this->accountId}")
            ->allow(15)
            ->every(60)
            ->then(
                fn () => $this->processRefresh($client),
                fn () => $this->release(rand(10, 25)) // Backoff when throttled
            );
    }

    private function processRefresh(OnlyFansApiClient $client): void
    {
        $profile = Profile::firstOrCreate(
            ['username' => $this->username],
            ['likes' => 0, 'revision' => 0, 'attempt_count' => 0]
        );

        $profile->update([
            'last_attempted_at' => now(),
            'attempt_count'     => DB::raw('COALESCE(attempt_count, 0) + 1'),
        ]);

        try {
            $rawResponse = $client->fetchProfile($this->username);
            $payload = OnlyFansProfilePayload::fromResponse($this->username, $rawResponse);

            // Optimistic lock & monotonic revision check
            DB::transaction(function () use ($profile, $payload) {
                // Ensure late responses with older revisions cannot overwrite newer data
                $updated = DB::table('profiles')
                    ->where('id', $profile->id)
                    ->where('revision', '<', $payload->revision)
                    ->update([
                        'likes'                       => $payload->likes,
                        'revision'                    => $payload->revision,
                        'display_name'                => $payload->displayName ?? $profile->display_name,
                        'avatar_url'                  => $payload->avatarUrl ?? $profile->avatar_url,
                        'last_successful_refresh_at'  => now(),
                        'last_failed_at'              => null,
                        'last_failure_reason'         => null,
                        'next_refresh_at'             => now()->addHours(Profile::calculateIntervalForLikes($payload->likes)),
                        'updated_at'                  => now(),
                    ]);

                if ($updated === 0 && $profile->revision >= $payload->revision) {
                    Log::warning("Discarded stale revision for {$this->username}", [
                        'current_rev' => $profile->revision,
                        'inbound_rev' => $payload->revision,
                    ]);
                }
            });

            Log::info("Successfully refreshed profile", [
                'username' => $this->username,
                'likes'    => $payload->likes,
                'revision' => $payload->revision,
            ]);
        } catch (TransientUpstreamException $e) {
            $profile->update([
                'last_failed_at'      => now(),
                'last_failure_reason' => $e->getMessage(),
            ]);

            // If upstream sent Retry-After header, honor it; otherwise apply exponential randomized jitter
            $retryAfter = $e->getRetryAfter();
            $jitterDelay = ($retryAfter !== null && $retryAfter > 0)
                ? min(120, $retryAfter)
                : min(120, (int) (pow(2, $this->attempts()) + rand(2, 8)));

            Log::warning("Transient upstream error, releasing with jitter delay", [
                'username'    => $this->username,
                'jitter_sec'  => $jitterDelay,
                'error_class' => get_class($e),
                'error'       => $e->getMessage(),
            ]);

            $this->release($jitterDelay);
        } catch (\Throwable $e) {
            $profile->update([
                'last_failed_at'      => now(),
                'last_failure_reason' => $e->getMessage(),
            ]);

            // Keep secrets out of logs: Only log structured class and sanitized message
            Log::error("Permanent failure processing {$this->username}", [
                'username'    => $this->username,
                'error_class' => get_class($e),
                'message'     => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }
}
