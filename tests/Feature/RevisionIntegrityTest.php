<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Profile;
use App\Jobs\RefreshOnlyFansProfileJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RevisionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_revision_strictly_greater_commits_and_advances_revision(): void
    {
        $profile = Profile::create([
            'username'                   => 'madison420ivy',
            'likes'                      => 100000,
            'revision'                   => 5,
            'last_successful_refresh_at' => now()->subDay(),
        ]);

        Http::fake([
            'onlyfans.com/api2/v2/users/madison420ivy' => Http::response([
                'profile'  => ['likes' => 125000, 'name' => 'Madison Ivy'],
                'revision' => 6,
            ], 200),
        ]);

        $job = new RefreshOnlyFansProfileJob('madison420ivy');
        $job->handle(new \App\Services\OnlyFansApiClient());

        $profile->refresh();
        $this->assertSame(125000, $profile->likes);
        $this->assertSame(6, $profile->revision);
    }

    public function test_inbound_revision_strictly_less_is_discarded_by_optimistic_lock(): void
    {
        $profile = Profile::create([
            'username' => 'madison420ivy',
            'likes'    => 130000,
            'revision' => 12,
        ]);

        // Older revision 11 arriving out of order
        Http::fake([
            'onlyfans.com/api2/v2/users/madison420ivy' => Http::response([
                'profile'  => ['likes' => 121000],
                'revision' => 11,
            ], 200),
        ]);

        $job = new RefreshOnlyFansProfileJob('madison420ivy');
        $job->handle(new \App\Services\OnlyFansApiClient());

        $profile->refresh();
        $this->assertSame(130000, $profile->likes, 'Database likes must not be regressed by older revision');
        $this->assertSame(12, $profile->revision, 'Database revision must remain at newer revision 12');
    }

    public function test_duplicate_same_revision_does_not_mutate_state(): void
    {
        $profile = Profile::create([
            'username' => 'madison420ivy',
            'likes'    => 120000,
            'revision' => 10,
        ]);

        // Duplicate revision 10 with conflicting payload
        Http::fake([
            'onlyfans.com/api2/v2/users/madison420ivy' => Http::response([
                'likes'    => 999999, // Should NOT be accepted because revision is equal (10 <= 10)
                'revision' => 10,
            ], 200),
        ]);

        $job = new RefreshOnlyFansProfileJob('madison420ivy');
        $job->handle(new \App\Services\OnlyFansApiClient());

        $profile->refresh();
        $this->assertSame(120000, $profile->likes);
        $this->assertSame(10, $profile->revision);
    }
}
