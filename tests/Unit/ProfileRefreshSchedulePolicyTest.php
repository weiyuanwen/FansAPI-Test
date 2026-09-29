<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use App\Models\Profile;

class ProfileRefreshSchedulePolicyTest extends TestCase
{
    #[Test]
    #[DataProvider('likesCadenceProvider')]
    public function it_allocates_exact_hours_per_fansapi_spec(int $likes, int $expectedHours, string $description): void
    {
        $profile = new Profile();
        $profile->likes = $likes;

        $interval = $profile->calculateIntervalForLikes($likes);

        $this->assertSame(
            $expectedHours,
            $interval,
            "Failed asserting {$description}. Likes: {$likes}, Expected: {$expectedHours}h, Got: {$interval}h."
        );
    }

    public static function likesCadenceProvider(): array
    {
        return [
            'strictly above 100k (e.g. 100,001)' => [100001, 24, 'strictly above 100,000 threshold must be 24h'],
            'high tier superstar (e.g. 500,000)' => [500000, 24, 'high tier creator must be 24h'],
            'boundary EXACT 100,000 likes'       => [100000, 72, 'exactly 100,000 belongs to the 72h group'],
            'just below 100,000 (e.g. 99,999)'  => [99999, 72, 'below 100,000 belongs to 72h group'],
            'medium creator (e.g. 10,000)'       => [10000, 72, 'medium creator belongs to 72h group'],
            'brand new creator with 0 likes'     => [0, 72, '0 likes belongs to 72h group'],
        ];
    }
}
