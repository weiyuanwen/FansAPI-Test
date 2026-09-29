<?php

namespace App\DTOs;

use App\Exceptions\MalformedUpstreamPayloadException;
use App\Exceptions\InvalidLikesValueException;

/**
 * Immutable DTO representing a validated OnlyFans upstream profile payload.
 * Supports both legacy root format and modern nested "profile" format.
 */
readonly class OnlyFansProfilePayload
{
    public function __construct(
        public string $username,
        public int $likes,
        public int $revision,
        public ?string $displayName = null,
        public ?string $avatarUrl = null,
        public array $raw = []
    ) {}

    /**
     * Parses and strictly validates upstream payload.
     *
     * @throws MalformedUpstreamPayloadException When required keys (revision, likes) are missing or structural types are invalid.
     * @throws InvalidLikesValueException When likes value is negative, non-numeric, or boolean.
     */
    public static function fromResponse(string $username, array $data): self
    {
        // 1. Validate Revision presence and type
        if (!array_key_exists('revision', $data)) {
            throw new MalformedUpstreamPayloadException("Missing 'revision' in upstream response for {$username}.");
        }

        $rawRevision = $data['revision'];
        if (is_bool($rawRevision) || !is_numeric($rawRevision)) {
            throw new MalformedUpstreamPayloadException("Upstream 'revision' must be numeric for {$username}.");
        }

        $revision = (int) $rawRevision;
        if ($revision < 0) {
            throw new MalformedUpstreamPayloadException("Upstream 'revision' cannot be negative ({$revision}) for {$username}.");
        }

        // 2. Extract Likes:
        // If modern nested "profile" format is provided, it is authoritative.
        // We do NOT fall back to legacy root if "profile" object is present with null/missing likes.
        $likesRaw = null;
        $profileData = [];

        if (array_key_exists('profile', $data)) {
            if (!is_array($data['profile'])) {
                throw new MalformedUpstreamPayloadException("Upstream 'profile' field must be an array for {$username}.");
            }
            $profileData = $data['profile'];
            if (!array_key_exists('likes', $profileData) || $profileData['likes'] === null) {
                throw new MalformedUpstreamPayloadException("Authoritative 'profile.likes' is missing or null in upstream response for {$username}.");
            }
            $likesRaw = $profileData['likes'];
        } elseif (array_key_exists('likes', $data)) {
            // Legacy root format
            if ($data['likes'] === null) {
                throw new MalformedUpstreamPayloadException("Legacy root 'likes' field is null in upstream response for {$username}.");
            }
            $likesRaw = $data['likes'];
            $profileData = $data;
        } else {
            throw new MalformedUpstreamPayloadException("Neither 'profile.likes' nor 'likes' found in upstream response for {$username}.");
        }

        // 3. Strict Likes Validation
        // Booleans in PHP (true/false) evaluate to 1/0 with is_numeric in some contexts or cast poorly; reject explicitly
        if (is_bool($likesRaw) || !is_numeric($likesRaw) || is_array($likesRaw)) {
            $type = gettype($likesRaw);
            throw new InvalidLikesValueException("Likes value must be a numeric integer, received type: {$type}.");
        }

        $likes = (int) $likesRaw;

        if ($likes < 0) {
            throw new InvalidLikesValueException("Likes value cannot be negative: {$likes}.");
        }

        return new self(
            username: $username,
            likes: $likes,
            revision: $revision,
            displayName: $profileData['name'] ?? $profileData['displayName'] ?? null,
            avatarUrl: $profileData['avatar'] ?? $profileData['avatarUrl'] ?? null,
            raw: $data
        );
    }

    /**
     * Determines whether this profile qualifies for high-cadence refresh (24 hours).
     * Delegates directly to Profile model's single source of truth.
     * Rule:
     * - > 100,000 likes => 24 hours
     * - <= 100,000 likes (including exactly 100,000) => 72 hours
     */
    public function getNextRefreshIntervalHours(): int
    {
        return self::calculateRefreshInterval($this->likes);
    }

    public static function calculateRefreshInterval(int $likes): int
    {
        return \App\Models\Profile::calculateIntervalForLikes($likes);
    }
}
