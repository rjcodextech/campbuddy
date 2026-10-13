<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventManager;
use App\Models\NativePushToken;
use App\Models\OrganizerPushToken;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes to the native app through Firebase Cloud Messaging (HTTP v1), the
 * counterpart of EventPush (Web Push). Does nothing — and costs nothing —
 * until a Firebase service-account file is configured (FCM_CREDENTIALS_PATH),
 * so the website and the Web Push paths behave exactly as before.
 *
 * Authentication is Google's service-account flow done by hand (a signed JWT
 * exchanged for a short-lived access token, cached), so no extra package is
 * needed. A token FCM reports as gone is deleted.
 */
class NativePush
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_CACHE_KEY = 'fcm.access_token';

    private const CONCURRENCY = 20;

    /** Whether FCM is set up and switched on. */
    public static function enabled(): bool
    {
        return config('mobile_app.features.native_push') && self::credentials() !== null;
    }

    /**
     * One push to every app install following the event.
     *
     * @param  array{title: string, body: string, url: string}  $payload
     * @return int installs the push was handed to
     */
    public static function toEvent(Event $event, array $payload, string $kind, int $ttl): int
    {
        if (! self::enabled() || isset($payload['needs'])) {
            // "needs" payloads are shown only when the phone holds something (a profile to bring) —
            // the app cannot check that before a push is shown, so they stay Web-Push-only.
            return 0;
        }

        $sent = 0;
        NativePushToken::where('event_id', $event->id)->orderBy('id')->chunkById(500, function ($tokens) use ($payload, $kind, $ttl, &$sent) {
            $sent += self::toTokens($tokens, $payload, $kind, $ttl)['sent'];
        });

        return $sent;
    }

    /**
     * An alert for the people running the event: every admin, and the active
     * event managers assigned to it, who signed in on the app. `$path` is the
     * page inside the panel, relative to "/admin/events/{id}/" or "/manager/events/{id}/".
     *
     * @param  array{title: string, body: string}  $payload
     * @return int phones the alert was handed to
     */
    public static function toOrganizers(Event $event, array $payload, string $kind, string $path = 'reports', int $ttl = 86400): int
    {
        if (! self::enabled()) {
            return 0;
        }

        $managerIds = EventManager::where('is_active', true)->whereHas('events', fn ($q) => $q->whereKey($event->id))->pluck('id');
        $admins = OrganizerPushToken::where('role', 'admin')->whereIn('user_id', User::pluck('id'))->get();
        $managers = OrganizerPushToken::where('role', 'manager')->whereIn('user_id', $managerIds)->get();

        return self::toTokens($admins, $payload + ['url' => "/admin/events/{$event->id}/{$path}"], $kind, $ttl)['sent']
            + self::toTokens($managers, $payload + ['url' => "/manager/events/{$event->id}/{$path}"], $kind, $ttl)['sent'];
    }

    /**
     * @param  Collection<int, NativePushToken|OrganizerPushToken>  $tokens
     * @param  array{title: string, body: string, url: string}  $payload
     * @return array{sent: int, expired: int}
     */
    public static function toTokens(Collection $tokens, array $payload, string $kind, int $ttl): array
    {
        $result = ['sent' => 0, 'expired' => 0];
        $credentials = self::credentials();

        if ($tokens->isEmpty() || ! config('mobile_app.features.native_push') || $credentials === null) {
            return $result;
        }

        try {
            $bearer = self::accessToken($credentials);
        } catch (Throwable $e) {
            Log::warning('FCM auth failed', ['error' => $e->getMessage()]);

            return $result;
        }

        $url = 'https://fcm.googleapis.com/v1/projects/'.rawurlencode($credentials['project_id']).'/messages:send';
        $list = $tokens->values();

        $requests = function () use ($list, $payload, $kind, $ttl, $url, $bearer) {
            foreach ($list as $token) {
                yield new Request('POST', $url, [
                    'Authorization' => 'Bearer '.$bearer,
                    'Content-Type' => 'application/json',
                ], json_encode(self::message($token->token, $payload, $kind, $ttl)));
            }
        };

        $pool = new Pool(self::http(), $requests(), [
            'concurrency' => self::CONCURRENCY,
            'options' => ['http_errors' => false, 'timeout' => 15],
            'fulfilled' => function ($response, $index) use ($list, &$result) {
                $status = $response->getStatusCode();

                if ($status === 200) {
                    $result['sent']++;
                } elseif ($status === 404 || ($status === 400 && str_contains((string) $response->getBody(), 'INVALID_ARGUMENT'))) {
                    NativePushToken::where('token', $list[$index]->token)->delete();
                    OrganizerPushToken::where('token', $list[$index]->token)->delete();
                    $result['expired']++;
                } elseif ($status === 401) {
                    Cache::forget(self::TOKEN_CACHE_KEY);
                } else {
                    Log::warning('FCM push refused', ['status' => $status, 'kind' => $kind]);
                }
            },
            'rejected' => function ($reason) use ($kind) {
                Log::warning('FCM push failed', ['kind' => $kind, 'error' => $reason instanceof Throwable ? $reason->getMessage() : 'unknown']);
            },
        ]);
        $pool->promise()->wait();

        return $result;
    }

    /** The FCM v1 message for one install. */
    public static function message(string $token, array $payload, string $kind, int $ttl): array
    {
        return ['message' => [
            'token' => $token,
            'notification' => ['title' => $payload['title'], 'body' => $payload['body']],
            // The app opens this path (same-site only — native-bridge.js checks).
            'data' => ['url' => $payload['url'], 'kind' => $kind],
            'android' => [
                'priority' => 'HIGH',
                'ttl' => $ttl.'s',
                'collapse_key' => $kind,
                'notification' => ['channel_id' => 'announcements', 'icon' => 'ic_stat_campbuddy', 'color' => '#60121c'],
            ],
            'apns' => [
                'headers' => ['apns-expiration' => (string) (time() + $ttl), 'apns-collapse-id' => substr($kind, 0, 64)],
                'payload' => ['aps' => ['sound' => 'default']],
            ],
        ]];
    }

    /** @return array{project_id: string, client_email: string, private_key: string}|null */
    private static function credentials(): ?array
    {
        $path = config('services.fcm.credentials_path');

        if (! $path || ! is_readable($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            return null;
        }

        $project = config('services.fcm.project_id') ?: ($json['project_id'] ?? null);

        return $project ? ['project_id' => $project, 'client_email' => $json['client_email'], 'private_key' => $json['private_key']] : null;
    }

    private static function accessToken(array $credentials): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, 3000, function () use ($credentials) {
            $now = time();
            $b64 = fn (string $raw) => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
            $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Could not sign the FCM token request.');
            }

            $response = self::http()->post('https://oauth2.googleapis.com/token', [
                'form_params' => ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned.'.'.$b64($signature)],
                'timeout' => 15,
            ]);
            $token = json_decode((string) $response->getBody(), true)['access_token'] ?? null;

            if (! $token) {
                throw new \RuntimeException('FCM answered without an access token.');
            }

            return $token;
        });
    }

    private static function http(): Client
    {
        return app()->bound(Client::class) ? app(Client::class) : new Client;
    }
}
