<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Profile;
use App\Jobs\RefreshOnlyFansProfileJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

class IncidentReproductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_upstream_v11_schema_drift_does_not_wipe_likes_to_zero(): void
    {
        // 1. Seed existing valid profile at revision 10 with 120,000 likes
        $profile = Profile::create([
            'username'                   => 'madison420ivy',
            'likes'                      => 120000,
            'revision'                   => 10,
            'last_successful_refresh_at' => now()->subDay(),
        ]);

        // 2. Mock upstream response with nested 'profile' and revision 11 (likes: 121,000)
        Http::fake([
            'onlyfans.com/api2/v2/users/madison420ivy' => Http::response([
                'profile'  => ['likes' => 121000, 'name' => 'Madison Ivy'],
                'revision' => 11,
            ], 200),
        ]);

        // 3. Dispatch the robust job
        $job = new RefreshOnlyFansProfileJob('madison420ivy');
        app()->call([$job, 'handle']);

        // 4. Assert data was updated to 121,000 and revision 11 (NOT wiped to 0!)
        $profile->refresh();
        $this->assertSame(121000, $profile->likes);
        $this->assertSame(11, $profile->revision);
        $this->assertNotNull($profile->last_successful_refresh_at);
        $this->assertNull($profile->last_failed_at);
    }

    public function test_stale_older_revision_arriving_late_is_discarded(): void
    {
        // Profile is already updated to revision 11 with 121,000 likes
        $profile = Profile::create([
            'username' => 'madison420ivy',
            'likes'    => 121000,
            'revision' => 11,
        ]);

        // Delayed response arrives with Revision 10 and 120,000 likes
        Http::fake([
            'onlyfans.com/api2/v2/users/madison420ivy' => Http::response([
                'likes'    => 120000,
                'revision' => 10,
            ], 200),
        ]);

        $job = new RefreshOnlyFansProfileJob('madison420ivy');
        app()->call([$job, 'handle']);

        $profile->refresh();
        // MUST still be revision 11 and 121,000 likes
        $this->assertSame(121000, $profile->likes);
        $this->assertSame(11, $profile->revision);
    }

    public function test_failed_http_500_preserves_valid_profile_data(): void
    {
        $profile = Profile::create([
            'username' => 'madison420ivy',
            'likes'    => 120000,
            'revision' => 10,
        ]);

        Http::fake([
            'onlyfans.com/api2/v2/users/madison420ivy' => Http::response('', 500),
        ]);

        $job = new RefreshOnlyFansProfileJob('madison420ivy');
        app()->call([$job, 'handle']);

        $profile->refresh();
        $this->assertSame(120000, $profile->likes); // Untouched!
        $this->assertNotNull($profile->last_failed_at);
        $this->assertStringContainsString('500', (string) $profile->last_failure_reason);
    }
}
