<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Jobs\RefreshOnlyFansProfileJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Queue;

class IdempotentJobReplayTest extends TestCase
{
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
}
