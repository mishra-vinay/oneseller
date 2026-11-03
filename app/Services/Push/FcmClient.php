<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Firebase Cloud Messaging HTTP v1. Authenticates with a Google service account
 * (a signed JWT exchanged for an access token), without extra packages.
 */
class FcmClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * Sends one message. Returns false when FCM says the token is no longer valid.
     *
     * @param  array<string, string>  $data
     */
    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        $credentials = $this->credentials() ?? throw new RuntimeException('FCM is not configured.');

        $response = Http::withToken($this->accessToken($credentials))
            ->acceptJson()
            ->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map('strval', $data),
                    'android' => ['priority' => 'high'],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                ],
            ]);

        if ($response->successful()) {
            return true;
        }

        $status = $response->json('error.details.0.errorCode') ?? $response->json('error.status');

        if (in_array($status, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true) || $response->status() === 404) {
            return false;
        }

        throw new RuntimeException('FCM send failed: '.$response->body());
    }

    /**
     * @param  array{client_email: string, private_key: string, token_uri?: string, project_id: string}  $credentials
     */
    private function accessToken(array $credentials): string
    {
        return Cache::remember('fcm.access_token.'.md5($credentials['client_email']), now()->addMinutes(50), function () use ($credentials) {
            $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $now = time();
            $segments = [
                $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->base64Url(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => $tokenUri,
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];

            if (! openssl_sign(implode('.', $segments), $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Could not sign the FCM service account JWT.');
            }

            $segments[] = $this->base64Url($signature);

            $response = Http::asForm()->timeout(10)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ]);

            return $response->json('access_token') ?? throw new RuntimeException('FCM token exchange failed: '.$response->body());
        });
    }

    /**
     * Service account JSON, from FCM_CREDENTIALS (file path) or FCM_CREDENTIALS_JSON (the JSON itself, optionally base64).
     *
     * @return array<string, string>|null
     */
    private function credentials(): ?array
    {
        $raw = config('services.fcm.credentials_json');

        if (! $raw && ($path = config('services.fcm.credentials')) && is_readable($path)) {
            $raw = file_get_contents($path);
        }

        if (! $raw) {
            return null;
        }

        $decoded = json_decode($raw, true) ?? json_decode((string) base64_decode($raw, true), true);

        return is_array($decoded) && isset($decoded['client_email'], $decoded['private_key'], $decoded['project_id']) ? $decoded : null;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
