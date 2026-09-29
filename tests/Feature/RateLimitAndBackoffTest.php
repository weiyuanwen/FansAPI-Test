<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Profile;
use App\Jobs\RefreshOnlyFansProfileJob;
use App\Exceptions\TransientUpstreamException;
use App\Services\OnlyFansApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RateLimitAndBackoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_upstream_429_throws_transient_upstream_exception(): void
    {
        Http::fake([
            'onlyfans.com/api2/v2/users/madison420ivy' => Http::response(
                ['error' => 'Too Many Requests'],
                429,
                ['Retry-After' => '30']
            ),
        ]);

        $client = new OnlyFansApiClient();

        $this->expectException(TransientUpstreamException::class);
        $this->expectExceptionMessage('HTTP 429 Rate Limit Exceeded');

        $client->fetchProfile('madison420ivy');
    }

    public function test_job_releases_with_jitter_on_rate_limit(): void
    {
        $profile = Profile::create([
            'username'                   => 'madison420ivy',
            'likes'                      => 120000,
            'revision'                   => 10,
            'last_successful_refresh_at' => now()->subHours(2),
        ]);

        Http::fake([
            'onlyfans.com/api2/v2/users/madison420ivy' => Http::response('Too Many Requests', 429),
        ]);

        $job = $this->getMockBuilder(RefreshOnlyFansProfileJob::class)
            ->setConstructorArgs(['madison420ivy', 'account_tier_1'])
            ->onlyMethods(['release', 'attempts'])
            ->getMock();

        $job->method('attempts')->willReturn(2);

        // Expect release() called with jitter delay between 2^2 + 2 = 6 and 2^2 + 8 = 12 seconds
        $job->expects($this->once())
            ->method('release')
            ->with($this->callback(function (int $delay) {
                return $delay >= 6 && $delay <= 12;
            }));

        $client = new OnlyFansApiClient();
        $refMethod = new \ReflectionMethod(RefreshOnlyFansProfileJob::class, 'processRefresh');
        $refMethod->setAccessible(true);
        $refMethod->invoke($job, $client);

        $profile->refresh();
        $this->assertSame(120000, $profile->likes, 'Original likes must be preserved during 429 backoff');
        $this->assertNotNull($profile->last_failed_at);
        $this->assertStringContainsString('429', (string) $profile->last_failure_reason);
    }

    public function test_jitter_algorithm_caps_at_120_seconds(): void
    {
        Profile::create([
            'username' => 'madison420ivy',
            'likes'    => 120000,
            'revision' => 10,
        ]);

        Http::fake([
            'onlyfans.com/api2/v2/users/madison420ivy' => Http::response('Too Many Requests', 429),
        ]);

        $mockJob = $this->getMockBuilder(RefreshOnlyFansProfileJob::class)
            ->setConstructorArgs(['madison420ivy'])
            ->onlyMethods(['attempts'])
            ->getMock();
        $mockJob->method('attempts')->willReturn(10); // 2^10 = 1024 + rand(2,8) > 120

        $refMethod = new \ReflectionMethod(RefreshOnlyFansProfileJob::class, 'processRefresh');
        $refMethod->setAccessible(true);
        $refMethod->invoke($mockJob, new OnlyFansApiClient());

        $this->assertTrue($mockJob->wasReleased, 'Job must be released on 429');
        $this->assertSame(120, $mockJob->releaseDelay, 'Jitter delay must be capped at 120s max to prevent deadlock');
    }

    public function test_upstream_retry_after_header_is_honored_and_capped_at_120_seconds(): void
    {
        $profile = Profile::create([
            'username'                   => 'madison420ivy',
            'likes'                      => 120000,
            'revision'                   => 10,
            'last_successful_refresh_at' => now()->subHours(2),
        ]);

        Http::fake([
            'onlyfans.com/api2/v2/users/madison420ivy' => Http::response(
                'Too Many Requests',
                429,
                ['Retry-After' => '180'] // Upstream requested 180s, worker must cap at 120s max
            ),
        ]);

        $job = $this->getMockBuilder(RefreshOnlyFansProfileJob::class)
            ->setConstructorArgs(['madison420ivy', 'account_tier_1'])
            ->onlyMethods(['release', 'attempts'])
            ->getMock();

        $job->method('attempts')->willReturn(1);

        $job->expects($this->once())
            ->method('release')
            ->with(120);

        $client = new OnlyFansApiClient();
        $refMethod = new \ReflectionMethod(RefreshOnlyFansProfileJob::class, 'processRefresh');
        $refMethod->setAccessible(true);
        $refMethod->invoke($job, $client);
    }
}
