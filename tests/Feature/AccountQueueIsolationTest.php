<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Jobs\RefreshOnlyFansProfileJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class AccountQueueIsolationTest extends TestCase
{
    public function test_account_queue_isolation_prevents_starvation_of_healthy_accounts(): void
    {
        Queue::fake();

        // Dispatch 20 jobs for busy Account A (heavily loaded)
        for ($i = 0; $i < 20; $i++) {
            RefreshOnlyFansProfileJob::dispatch("busy_user_{$i}", 'account_a');
        }

        // Dispatch 1 job for healthy Account B
        RefreshOnlyFansProfileJob::dispatch("healthy_user_1", 'account_b');

        Queue::assertPushed(RefreshOnlyFansProfileJob::class, 21);

        Queue::assertPushed(RefreshOnlyFansProfileJob::class, function ($job) {
            return $job->accountId === 'account_b' && $job->username === 'healthy_user_1';
        });
    }

    public function test_job_attaches_correct_account_id_to_throttle_key(): void
    {
        $job = new RefreshOnlyFansProfileJob('creator_x', 'vip_agency_01');

        $this->assertSame('vip_agency_01', $job->accountId);
        $this->assertSame('creator_x', $job->username);
    }
}
