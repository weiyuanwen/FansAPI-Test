<?php

namespace App\Jobs;

use App\DTOs\OnlyFansProfilePayload;
use App\Exceptions\TransientUpstreamException;
use App\Models\Profile;
use App\Services\OnlyFansApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RefreshOnlyFansProfileJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30; // Job-level timeout
    public int $tries = 4;
    public int $maxExceptions = 3;
    public int $uniqueFor = 300; // 5-minute atomic unique lock

    public function __construct(
        public string $username,
        public string $accountId = 'default'
    ) {}

    public function uniqueId(): string
    {
        return "profile_refresh_{$this->username}";
    }

    public function handle(OnlyFansApiClient $client): void
    {
        Log::info("Starting profile refresh", [
            'username' => $this->username,
            'account'  => $this->accountId,
            'attempt'  => $this->attempts(),
        ]);

        // Account-level throttling to prevent capacity starvation
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
            ['likes' => 0, 'revision' => 0]
        );

        $profile->update([
            'last_attempted_at' => now(),
            'attempt_count'     => DB::raw('attempt_count + 1'),
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
                        'next_refresh_at'             => now()->addHours($profile->calculateIntervalForLikes($payload->likes)),
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

            // Exponential randomized jitter: 2^attempt + rand(2, 8)
            $jitterDelay = min(120, (int) (pow(2, $this->attempts()) + rand(2, 8)));
            Log::warning("Transient upstream error, releasing with jitter delay", [
                'username'    => $this->username,
                'jitter_sec'  => $jitterDelay,
                'error'       => $e->getMessage(),
            ]);

            $this->release($jitterDelay);
        } catch (\Throwable $e) {
            $profile->update([
                'last_failed_at'      => now(),
                'last_failure_reason' => $e->getMessage(),
            ]);
            Log::error("Permanent failure processing {$this->username}", ['exception' => $e]);
            $this->fail($e);
        }
    }
}
