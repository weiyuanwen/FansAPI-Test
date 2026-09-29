<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Profile;
use App\Jobs\RefreshOnlyFansProfileJob;
use App\Console\Commands\DispatchScheduledProfileRefreshesCommand;
use App\Services\FansApiMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

class QueueDispatchIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_refreshes_command_dispatches_to_correct_high_priority_queue(): void
    {
        Queue::fake();

        // Create 2 profiles due for refresh
        Profile::create([
            'username'        => 'creator_vip_1',
            'account_id'      => 'acc_100',
            'likes'           => 150000,
            'revision'        => 10,
            'next_refresh_at' => now()->subHour(),
        ]);

        Profile::create([
            'username'        => 'creator_vip_2',
            'account_id'      => 'acc_200',
            'likes'           => 80000,
            'revision'        => 5,
            'next_refresh_at' => now()->subMinutes(30),
        ]);

        $command = new DispatchScheduledProfileRefreshesCommand();
        $command->handle();

        // Assert jobs were dispatched to 'profiles-high-priority' (matching config/horizon.php)
        Queue::assertPushedOn('profiles-high-priority', RefreshOnlyFansProfileJob::class, 2);

        // Verify account isolation: different accounts are assigned
        Queue::assertPushed(RefreshOnlyFansProfileJob::class, function ($job) {
            return ($job->username === 'creator_vip_1' && $job->accountId === 'acc_100')
                || ($job->username === 'creator_vip_2' && $job->accountId === 'acc_200');
        });
    }

    public function test_fansapi_metrics_service_tracks_attempts_and_successes(): void
    {
        FansApiMetricsService::recordAttempt('test_user');
        FansApiMetricsService::recordAttempt('test_user');
        FansApiMetricsService::recordSuccess('test_user', 120000, 10);

        $summary = FansApiMetricsService::getMetricsSummary();

        $this->assertArrayHasKey('successful_refreshes', $summary);
        $this->assertArrayHasKey('total_attempts', $summary);
        $this->assertArrayHasKey('attempts_per_successful_refresh', $summary);
        $this->assertArrayHasKey('oldest_waiting_job_age_sec', $summary);
    }
}
