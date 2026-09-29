<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use App\DTOs\OnlyFansProfilePayload;
use App\Exceptions\MalformedUpstreamPayloadException;
use App\Exceptions\InvalidLikesValueException;

class OnlyFansProfilePayloadTest extends TestCase
{
    // =========================================================================
    // 1. LEGACY VS NESTED RESPONSE FORMATS
    // =========================================================================

    #[Test]
    public function it_successfully_parses_legacy_root_format(): void
    {
        $payload = [
            'likes'       => 120000,
            'revision'    => 10,
            'name'        => 'Madison Ivy',
            'avatar'      => 'https://example.com/avatar.jpg',
        ];

        $dto = OnlyFansProfilePayload::fromResponse('madison420ivy', $payload);

        $this->assertSame('madison420ivy', $dto->username);
        $this->assertSame(120000, $dto->likes);
        $this->assertSame(10, $dto->revision);
        $this->assertSame('Madison Ivy', $dto->displayName);
        $this->assertSame('https://example.com/avatar.jpg', $dto->avatarUrl);
        $this->assertSame($payload, $dto->raw);
    }

    #[Test]
    public function it_successfully_parses_modern_nested_profile_format(): void
    {
        $payload = [
            'profile' => [
                'likes'       => 121000,
                'displayName' => 'Madison Ivy Verified',
                'avatarUrl'   => 'https://example.com/new_avatar.jpg',
            ],
            'revision' => 11,
        ];

        $dto = OnlyFansProfilePayload::fromResponse('madison420ivy', $payload);

        $this->assertSame('madison420ivy', $dto->username);
        $this->assertSame(121000, $dto->likes);
        $this->assertSame(11, $dto->revision);
        $this->assertSame('Madison Ivy Verified', $dto->displayName);
        $this->assertSame('https://example.com/new_avatar.jpg', $dto->avatarUrl);
    }

    #[Test]
    public function nested_profile_takes_precedence_over_root_attributes(): void
    {
        $payload = [
            'likes'    => 500, // Stale root
            'profile'  => [
                'likes' => 121000, // Fresh nested
                'name'  => 'Nested Creator',
            ],
            'revision' => 11,
        ];

        $dto = OnlyFansProfilePayload::fromResponse('creator', $payload);

        $this->assertSame(121000, $dto->likes);
        $this->assertSame('Nested Creator', $dto->displayName);
    }

    #[Test]
    public function it_coerces_valid_numeric_strings_to_integers(): void
    {
        $payload = [
            'profile'  => ['likes' => '121000'],
            'revision' => '11',
        ];

        $dto = OnlyFansProfilePayload::fromResponse('madison420ivy', $payload);

        $this->assertSame(121000, $dto->likes);
        $this->assertSame(11, $dto->revision);
    }

    // =========================================================================
    // 2. EXPLICIT ZERO VS MISSING/INVALID VALUES
    // =========================================================================

    #[Test]
    public function explicit_zero_likes_is_valid_in_root_format(): void
    {
        $payload = [
            'likes'    => 0,
            'revision' => 1,
        ];

        $dto = OnlyFansProfilePayload::fromResponse('brand_new_creator', $payload);

        $this->assertSame(0, $dto->likes);
        $this->assertSame(1, $dto->revision);
    }

    #[Test]
    public function explicit_zero_likes_is_valid_in_nested_format(): void
    {
        $payload = [
            'profile'  => ['likes' => 0],
            'revision' => 2,
        ];

        $dto = OnlyFansProfilePayload::fromResponse('brand_new_creator', $payload);

        $this->assertSame(0, $dto->likes);
        $this->assertSame(2, $dto->revision);
    }

    // =========================================================================
    // 3. MALFORMED STRUCTURAL PAYLOADS
    // =========================================================================

    #[Test]
    #[DataProvider('malformedPayloadProvider')]
    public function it_rejects_missing_or_structurally_malformed_payloads(array $payload, string $expectedMessageFragment): void
    {
        $this->expectException(MalformedUpstreamPayloadException::class);
        $this->expectExceptionMessageMatches("/{$expectedMessageFragment}/i");

        OnlyFansProfilePayload::fromResponse('test_user', $payload);
    }

    public static function malformedPayloadProvider(): array
    {
        return [
            'empty array' => [
                [],
                'Missing \'revision\'',
            ],
            'missing revision key entirely' => [
                ['likes' => 1000],
                'Missing \'revision\'',
            ],
            'non-numeric string revision' => [
                ['likes' => 1000, 'revision' => 'invalid_rev'],
                'Upstream \'revision\' must be numeric',
            ],
            'negative revision' => [
                ['likes' => 1000, 'revision' => -1],
                'cannot be negative',
            ],
            'boolean true revision' => [
                ['likes' => 1000, 'revision' => true],
                'Upstream \'revision\' must be numeric',
            ],
            'missing likes in root without profile object' => [
                ['revision' => 10, 'name' => 'John'],
                'Neither \'profile.likes\' nor \'likes\' found',
            ],
            'empty profile object missing likes' => [
                ['profile' => ['name' => 'John'], 'revision' => 10],
                'Neither \'profile.likes\' nor \'likes\' found',
            ],
            'null likes in nested profile' => [
                ['profile' => ['likes' => null], 'revision' => 10],
                'Neither \'profile.likes\' nor \'likes\' found',
            ],
            'null likes in root' => [
                ['likes' => null, 'revision' => 10],
                'Neither \'profile.likes\' nor \'likes\' found',
            ],
        ];
    }

    // =========================================================================
    // 4. INVALID LIKES VALUES (NEGATIVE, BOOLEAN, TEXT, ARRAYS)
    // =========================================================================

    #[Test]
    #[DataProvider('invalidLikesValueProvider')]
    public function it_rejects_invalid_likes_values(mixed $invalidLikes, string $expectedMessageFragment): void
    {
        $this->expectException(InvalidLikesValueException::class);
        $this->expectExceptionMessageMatches("/{$expectedMessageFragment}/i");

        $payload = [
            'likes'    => $invalidLikes,
            'revision' => 10,
        ];

        OnlyFansProfilePayload::fromResponse('bad_data_user', $payload);
    }

    public static function invalidLikesValueProvider(): array
    {
        return [
            'negative integer -1' => [-1, 'cannot be negative'],
            'negative integer -50000' => [-50000, 'cannot be negative'],
            'boolean true' => [true, 'numeric integer'],
            'boolean false' => [false, 'numeric integer'],
            'non-numeric string' => ['many_likes', 'numeric integer'],
            'empty string' => ['', 'numeric integer'],
            'nested array as likes' => [['count' => 100], 'numeric integer'],
        ];
    }

    // =========================================================================
    // 5. REFRESH SCHEDULE CADENCE (24H VS 72H)
    // =========================================================================

    #[Test]
    #[DataProvider('cadenceThresholdProvider')]
    public function it_calculates_correct_refresh_interval_based_on_likes(int $likes, int $expectedHours, string $scenario): void
    {
        $interval = OnlyFansProfilePayload::calculateRefreshInterval($likes);

        $this->assertSame(
            $expectedHours,
            $interval,
            "Failed cadence assertion for scenario: {$scenario} (likes: {$likes})"
        );
    }

    public static function cadenceThresholdProvider(): array
    {
        return [
            'well above threshold: 500,000 likes' => [500000, 24, '> 100k tier'],
            'just above threshold: 100,001 likes' => [100001, 24, 'strictly > 100k tier'],
            'EXACT boundary threshold: 100,000 likes' => [100000, 72, 'exactly 100k belongs to 72h group'],
            'just below threshold: 99,999 likes' => [99999, 72, '<= 100k tier'],
            'low likes: 5,000 likes' => [5000, 72, '<= 100k tier'],
            'zero likes: 0 likes' => [0, 72, '<= 100k tier (0 likes belongs to 72h group)'],
        ];
    }

    #[Test]
    public function dto_instance_method_reflects_calculated_interval(): void
    {
        $highDto = OnlyFansProfilePayload::fromResponse('high_creator', [
            'profile'  => ['likes' => 120000],
            'revision' => 10,
        ]);
        $this->assertSame(24, $highDto->getNextRefreshIntervalHours());

        $standardDto = OnlyFansProfilePayload::fromResponse('standard_creator', [
            'profile'  => ['likes' => 100000],
            'revision' => 10,
        ]);
        $this->assertSame(72, $standardDto->getNextRefreshIntervalHours());
    }
}
