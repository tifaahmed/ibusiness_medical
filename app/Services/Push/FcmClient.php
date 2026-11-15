<?php

namespace App\Services\Push;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Firebase Cloud Messaging, HTTP v1. The access token comes from the service-account JSON (a signed JWT swapped
 * at Google's token endpoint; no extra package). Nothing is sent unless `services.fcm.project_id` and the
 * credentials file exist, so a half-configured install can never fire a real push.
 */
class FcmClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.fcm.project_id')) && is_file((string) config('services.fcm.credentials'));
    }

    /**
     * Sends one notification to many device tokens. Returns, per token: 'ok', 'removed' (FCM says the token is dead —
     * the app was uninstalled) or 'failed' (anything else; the reason is in the log).
     *
     * @param  list<string>  $tokens
     * @param  array<string, string>  $data
     * @return array<string, 'ok'|'removed'|'failed'>
     */
    public function sendMany(array $tokens, string $title, string $body, array $data = []): array
    {
        if ($tokens === []) {
            return [];
        }

        $bearer = $this->accessToken();
        $url = 'https://fcm.googleapis.com/v1/projects/'.config('services.fcm.project_id').'/messages:send';
        $results = [];

        foreach (array_chunk($tokens, 100) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $token) => $pool->as($token)->withToken($bearer)->timeout(15)->post($url, ['message' => $this->message($token, $title, $body, $data)]),
                $chunk,
            ));

            foreach ($chunk as $token) {
                $response = $responses[$token] ?? null;

                if ($response instanceof Throwable || $response === null) {
                    Log::warning('FCM request failed.', ['message' => $response instanceof Throwable ? $response->getMessage() : 'no response']);
                    $results[$token] = 'failed';

                    continue;
                }

                if ($response->successful()) {
                    $results[$token] = 'ok';
                } elseif ($response->status() === 404 || $response->json('error.details.0.errorCode') === 'UNREGISTERED') {
                    $results[$token] = 'removed';
                } else {
                    Log::error('FCM refused a notification.', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);
                    $results[$token] = 'failed';
                }
            }
        }

        return $results;
    }

    /** @return array<string, mixed> */
    private function message(string $token, string $title, string $body, array $data): array
    {
        return array_filter([
            'token' => $token,
            'notification' => ['title' => $title, 'body' => $body],
            'data' => $data === [] ? null : $data,
            'android' => ['priority' => 'HIGH', 'notification' => ['sound' => 'default']],
            'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
        ]);
    }

    private function accessToken(): string
    {
        return Cache::remember('fcm.access_token', 3000, function (): string {
            $credentials = json_decode((string) file_get_contents((string) config('services.fcm.credentials')), true);

            if (! is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
                throw new RuntimeException('The FCM service-account file is not valid.');
            }

            $now = time();
            $b64 = fn (string $raw): string => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
            $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Could not sign the FCM token request.');
            }

            $response = Http::asForm()->timeout(15)->post($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.$b64($signature),
            ]);

            if (! $response->successful() || ! is_string($response->json('access_token'))) {
                throw new RuntimeException('Google refused the FCM token request ('.$response->status().').');
            }

            return $response->json('access_token');
        });
    }
}
