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
        $this->token = $token ?? config('services.onlyfans.token');
    }

    public function fetchProfile(string $username): array
    {
        $headers = [
            'Accept'     => 'application/json',
            'User-Agent' => 'FansAPI-Worker/1.0',
        ];

        if (!empty($this->token)) {
            $headers['Authorization'] = "Bearer {$this->token}";
        }

        $response = Http::withHeaders($headers)
            ->connectTimeout(3)
            ->timeout(5)
            ->get("{$this->baseUrl}/users/{$username}");

        if ($response->status() === 429) {
            $rawRetryAfter = $response->header('Retry-After');
            $retryAfter = is_numeric($rawRetryAfter) ? (int) $rawRetryAfter : null;

            Log::warning("Upstream rate limited for {$username}", [
                'retry_after_header' => $retryAfter,
            ]);

            throw new TransientUpstreamException(
                "HTTP 429 Rate Limit Exceeded",
                retryAfter: $retryAfter
            );
        }

        if ($response->serverError()) {
            // Keep secrets out of logs: Never attach raw response body
            throw new TransientUpstreamException("HTTP {$response->status()} Upstream Server Error");
        }

        if ($response->clientError()) {
            // Keep secrets out of logs: Never attach raw response body
            throw new PermanentUpstreamException("HTTP {$response->status()} Client Error");
        }

        $json = $response->json();
        if (!is_array($json)) {
            throw new TransientUpstreamException("Empty or malformed JSON body received");
        }

        return $json;
    }
}
