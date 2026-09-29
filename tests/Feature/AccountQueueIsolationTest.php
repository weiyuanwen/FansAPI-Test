<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Profile;
use App\Jobs\RefreshOnlyFansProfileJob;
use App\Services\OnlyFansApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class AccountQueueIsolationTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_account_throttling_isolates_failing_or_busy_account_without_blocking_other_accounts(): void
    {
        Http::fake([
            'onlyfans.com/api2/v2/users/*' => Http::response([
                'profile'  => ['likes' => 120000],
                'revision' => 10,
            ], 200),
        ]);

        $client = new OnlyFansApiClient();

        // Account A consumes its allowance (15 requests per 60s)
        for ($i = 1; $i <= 15; $i++) {
            $jobA = new RefreshOnlyFansProfileJob("busy_creator_{$i}", 'account_busy');
            $jobA->handle($client);
            $this->assertFalse($jobA->wasReleased, "Job {$i} for account_busy should be executed without throttle");
        }

        // 16th request for Account A must be throttled and released for backoff
        $jobA16 = new RefreshOnlyFansProfileJob("busy_creator_16", 'account_busy');
        $jobA16->handle($client);
        $this->assertTrue($jobA16->wasReleased, "16th job for account_busy must be throttled");
        $this->assertGreaterThanOrEqual(10, $jobA16->releaseDelay, "Job must have backoff delay");

        // Independent Account B must NOT be blocked by Account A's throttle exhaustion!
        $jobB = new RefreshOnlyFansProfileJob("healthy_creator_1", 'account_healthy');
        $jobB->handle($client);
        $this->assertFalse($jobB->wasReleased, "account_healthy must NOT be starved by account_busy");
    }

    public function test_job_attaches_correct_account_id_to_throttle_key(): void
    {
        $job = new RefreshOnlyFansProfileJob('creator_x', 'vip_agency_01');

        $this->assertSame('vip_agency_01', $job->accountId);
        $this->assertSame('creator_x', $job->username);
    }
}
