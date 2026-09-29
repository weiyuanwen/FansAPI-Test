<?php

use App\DTOs\OnlyFansProfilePayload;
use App\Exceptions\MalformedUpstreamPayloadException;
use App\Exceptions\InvalidLikesValueException;

describe('OnlyFansProfilePayload Unit Tests (Pest)', function () {

    describe('Response Format Detection', function () {
        it('parses legacy root format with 120,000 likes', function () {
            $payload = [
                'likes'    => 120000,
                'revision' => 10,
                'name'     => 'Madison Ivy',
            ];

            $dto = OnlyFansProfilePayload::fromResponse('madison420ivy', $payload);

            expect($dto->username)->toBe('madison420ivy')
                ->and($dto->likes)->toBe(120000)
                ->and($dto->revision)->toBe(10)
                ->and($dto->displayName)->toBe('Madison Ivy');
        });

        it('parses modern nested profile format with 121,000 likes', function () {
            $payload = [
                'profile'  => ['likes' => 121000, 'name' => 'Madison Ivy VIP'],
                'revision' => 11,
            ];

            $dto = OnlyFansProfilePayload::fromResponse('madison420ivy', $payload);

            expect($dto->likes)->toBe(121000)
                ->and($dto->revision)->toBe(11)
                ->and($dto->displayName)->toBe('Madison Ivy VIP');
        });

        it('prefers nested profile likes over root when both exist', function () {
            $payload = [
                'likes'    => 500,
                'profile'  => ['likes' => 121000],
                'revision' => 11,
            ];

            $dto = OnlyFansProfilePayload::fromResponse('creator', $payload);

            expect($dto->likes)->toBe(121000);
        });
    });

    describe('Zero Likes and Missing Fields', function () {
        it('accepts explicit numeric zero likes as valid', function () {
            $dto = OnlyFansProfilePayload::fromResponse('new_user', [
                'profile'  => ['likes' => 0],
                'revision' => 1,
            ]);

            expect($dto->likes)->toBe(0)
                ->and($dto->revision)->toBe(1);
        });

        it('throws MalformedUpstreamPayloadException when likes is omitted', function () {
            expect(fn () => OnlyFansProfilePayload::fromResponse('ghost', [
                'profile'  => ['name' => 'No Likes'],
                'revision' => 10,
            ]))->toThrow(MalformedUpstreamPayloadException::class);
        });

        it('throws MalformedUpstreamPayloadException on empty payload', function () {
            expect(fn () => OnlyFansProfilePayload::fromResponse('empty', []))
                ->toThrow(MalformedUpstreamPayloadException::class);
        });
    });

    describe('Invalid Likes Validation', function () {
        it('throws InvalidLikesValueException on negative likes', function ($badLikes) {
            expect(fn () => OnlyFansProfilePayload::fromResponse('bad', [
                'likes'    => $badLikes,
                'revision' => 10,
            ]))->toThrow(InvalidLikesValueException::class);
        })->with([-1, -50000]);

        it('throws InvalidLikesValueException on non-numeric or boolean likes', function ($badValue) {
            expect(fn () => OnlyFansProfilePayload::fromResponse('bad', [
                'likes'    => $badValue,
                'revision' => 10,
            ]))->toThrow(InvalidLikesValueException::class);
        })->with([true, false, 'many_likes', '', [['count' => 10]]]);
    });

    describe('Refresh Cadence Rules (24h vs 72h)', function () {
        it('schedules profiles > 100,000 likes for 24 hours', function ($likes) {
            expect(OnlyFansProfilePayload::calculateRefreshInterval($likes))->toBe(24);
        })->with([100001, 120000, 500000, 1000000]);

        it('schedules profiles <= 100,000 likes (including exactly 100k) for 72 hours', function ($likes) {
            expect(OnlyFansProfilePayload::calculateRefreshInterval($likes))->toBe(72);
        })->with([100000, 99999, 50000, 100, 0]);
    });
});
