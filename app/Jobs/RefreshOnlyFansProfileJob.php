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
    public bool $wasReleased = false;
    public int $releaseDelay = 0;

    public function __construct(
        public string $username,
        public string $accountId = 'default'
    ) {
        $this->onQueue('profiles-high-priority');
    }

    public function uniqueId(): string
    {
        return "profile_refresh_{$this->username}";
    }

    public function handle(OnlyFansApiClient $client): void
    {
        // Account-level queue rate limiting & worker isolation
        // block(0) prevents worker busy-waiting to respect Horizon 60s worker execution budget
        Redis::throttle("throttle:account:{$this->accountId}")
            ->block(0)
            ->allow(15)
            ->every(60)
            ->then(
                fn () => $this->processRefresh($client),
                function () {
                    $delay = rand(10, 25);
                    $this->wasReleased = true;
                    $this->releaseDelay = $delay;
                    Log::warning("Account throttled, backing off", [
                        'account' => $this->accountId,
                        'backoff' => $delay,
                    ]);
                    if ($this->job) {
                        $this->release($delay);
                    }
                }
            );
    }

    private function processRefresh(OnlyFansApiClient $client): void
    {
        $profile = Profile::firstOrCreate(
            ['username' => $this->username],
            ['account_id' => $this->accountId, 'likes' => 0, 'revision' => 0, 'attempt_count' => 0]
        );

        $profile->update(['last_attempted_at' => now()]);
        $profile->increment('attempt_count');

        \App\Services\FansApiMetricsService::recordAttempt($this->username);

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
                        'display_name'                => $payload->displayName ?? DB::raw('display_name'),
                        'avatar_url'                  => $payload->avatarUrl ?? DB::raw('avatar_url'),
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
            \App\Services\FansApiMetricsService::recordSuccess($this->username, $payload->likes, $payload->revision);
        } catch (TransientUpstreamException $e) {
            \App\Services\FansApiMetricsService::recordFailure($this->username, $e->getMessage());
            $profile->update([
                'last_failed_at'      => now(),
                'last_failure_reason' => substr($e->getMessage(), 0, 255),
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

            $this->wasReleased = true;
            $this->releaseDelay = $jitterDelay;
            if ($this->job) {
                $this->release($jitterDelay);
            }
        } catch (\App\Exceptions\PermanentUpstreamException | \App\Exceptions\MalformedUpstreamPayloadException | \App\Exceptions\InvalidLikesValueException $e) {
            \App\Services\FansApiMetricsService::recordFailure($this->username, $e->getMessage());
            $profile->update([
                'last_failed_at'      => now(),
                'last_failure_reason' => substr($e->getMessage(), 0, 255),
            ]);

            // Keep secrets out of logs: Only log structured class and sanitized message
            Log::error("Permanent payload failure processing {$this->username}", [
                'username'    => $this->username,
                'error_class' => get_class($e),
                'message'     => $e->getMessage(),
            ]);

            if ($this->job) {
                $this->fail($e);
            } else {
                throw $e;
            }
        } catch (\Throwable $e) {
            // Infrastructure or transient system exception (e.g. DB deadlock, network blip).
            // Do not fail permanently; allow Laravel queue retry mechanism to retry the job.
            \App\Services\FansApiMetricsService::recordFailure($this->username, $e->getMessage());
            $profile->update([
                'last_failed_at'      => now(),
                'last_failure_reason' => substr($e->getMessage(), 0, 255),
            ]);

            Log::warning("Transient system exception during {$this->username}, queue will retry", [
                'username'    => $this->username,
                'error_class' => get_class($e),
                'message'     => $e->getMessage(),
            ]);

            throw $e;
        }
        }
    }
}
