<?php

namespace App\Services;

use App\Exceptions\TransientUpstreamException;
use App\Exceptions\PermanentUpstreamException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OnlyFansApiClient
{
    public function __construct(
        private string $baseUrl = 'https://onlyfans.com/api2/v2',
        private ?string $token = null
    ) {
        $this->token = config('services.onlyfans.token');
    }

    public function fetchProfile(string $username): array
    {
        $response = Http::withHeaders([
            'Accept'     => 'application/json',
            'User-Agent' => 'FansAPI-Worker/1.0',
        ])
        ->connectTimeout(3)
        ->timeout(5)
        ->get("{$this->baseUrl}/users/{$username}");

        if ($response->status() === 429) {
            // Upstream 429 without Retry-After header
            $retryAfter = $response->header('Retry-After');
            Log::warning("Upstream rate limited for {$username}", ['retry_after_header' => $retryAfter]);
            throw new TransientUpstreamException("HTTP 429 Rate Limit Exceeded");
        }

        if ($response->serverError()) {
            throw new TransientUpstreamException("HTTP {$response->status()} Upstream Error: " . $response->body());
        }

        if ($response->clientError()) {
            throw new PermanentUpstreamException("HTTP {$response->status()} Client Error: " . $response->body());
        }

        $json = $response->json();
        if (!is_array($json)) {
            throw new TransientUpstreamException("Empty or malformed JSON body received");
        }

        return $json;
    }
}
