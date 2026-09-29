<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Profile;
use App\Jobs\RefreshOnlyFansProfileJob;
use App\Services\OnlyFansApiClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IdempotentJobReplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_implements_should_be_unique_contract(): void
    {
        $job = new RefreshOnlyFansProfileJob('madison420ivy');

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
    }

    public function test_unique_job_id_prevents_duplicate_dispatches_for_same_user(): void
    {
        $job = new RefreshOnlyFansProfileJob('madison420ivy');

        $this->assertSame('profile_refresh_madison420ivy', $job->uniqueId());
        $this->assertSame(300, $job->uniqueFor, 'Atomic Redis lock must be held for 300 seconds (5 minutes)');
    }

    public function test_different_users_generate_distinct_unique_locks(): void
    {
        $job1 = new RefreshOnlyFansProfileJob('madison420ivy');
        $job2 = new RefreshOnlyFansProfileJob('creator_two');

        $this->assertNotSame($job1->uniqueId(), $job2->uniqueId());
    }

    /**
     * Replay a job after its database write to check what happens
     * if a worker dies before acknowledging it in the queue broker.
     */
    public function test_job_replay_after_database_write_is_safe_and_idempotent(): void
    {
        $profile = Profile::create([
            'username'                   => 'madison420ivy',
            'likes'                      => 120000,
            'revision'                   => 10,
            'attempt_count'              => 1,
            'last_successful_refresh_at' => now()->subDay(),
        ]);

        $mockClient = \Mockery::mock(OnlyFansApiClient::class);
        $mockClient->shouldReceive('fetchProfile')
            ->twice()
            ->with('madison420ivy')
            ->andReturn([
                'profile'  => ['likes' => 121000, 'displayName' => 'Madison Ivy'],
                'revision' => 11,
            ]);

        // Run 1: Initial successful execution
        $jobAttempt1 = new RefreshOnlyFansProfileJob('madison420ivy');
        $jobAttempt1->handle($mockClient);

        $profile->refresh();
        $this->assertSame(121000, $profile->likes);
        $this->assertSame(11, $profile->revision);
        $this->assertSame(2, $profile->attempt_count);

        // Run 2: Replay job (simulating worker death before queue ACK)
        $jobReplay = new RefreshOnlyFansProfileJob('madison420ivy');
        $jobReplay->handle($mockClient);

        // Assert: Replay is completely idempotent; data remains intact and no duplicate rows created
        $profile->refresh();
        $this->assertSame(121000, $profile->likes);
        $this->assertSame(11, $profile->revision);
        $this->assertSame(1, Profile::where('username', 'madison420ivy')->count(), 'Duplicate replay must never create duplicate profile rows');
    }
}
