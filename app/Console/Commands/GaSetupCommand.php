<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Makes every CampBuddy event and parameter show up in Google Analytics.
 *
 * GA4 collects all event parameters, but reports only show the ones
 * registered as custom dimensions / metrics — a step that's easy to forget
 * for every new event. This registers everything in config/analytics.php
 * through the GA Admin API, and marks the key events. Idempotent: anything
 * already registered is left alone, so it's safe to re-run after each
 * deploy that adds events.
 *
 *   php artisan campbuddy:ga-setup --dry-run     # show what would change
 *   php artisan campbuddy:ga-setup               # apply it
 *
 * Needs GA_PROPERTY_ID and GA_CREDENTIALS_PATH (a service account JSON key
 * with Editor access to the property) — see spec §22.4.
 */
class GaSetupCommand extends Command
{
    protected $signature = 'campbuddy:ga-setup
        {--property= : GA4 property ID (default: GA_PROPERTY_ID)}
        {--credentials= : Service account JSON key file (default: GA_CREDENTIALS_PATH)}
        {--dry-run : List what would be created without changing anything}';

    protected $description = 'Register CampBuddy\'s analytics dimensions, metrics and key events in Google Analytics 4';

    private const API = 'https://analyticsadmin.googleapis.com/v1beta';

    private const LIMIT = 50;

    public function handle(): int
    {
        $property = (string) ($this->option('property') ?: config('analytics.property_id'));
        $dimensions = config('analytics.dimensions', []);
        $metrics = config('analytics.metrics', []);
        $keyEvents = config('analytics.key_events', []);

        if (! preg_match('/^\d+$/', $property)) {
            $this->error('Set GA_PROPERTY_ID (the numeric GA4 property ID, Admin → Property details) or pass --property.');

            return self::FAILURE;
        }

        if (count($dimensions) > self::LIMIT || count($metrics) > self::LIMIT) {
            $this->error('config/analytics.php lists more than GA4\'s '.self::LIMIT.' custom dimensions or metrics.');

            return self::FAILURE;
        }

        try {
            $api = $this->client();
            $base = self::API."/properties/{$property}";

            $haveDimensions = $this->existing($api, "{$base}/customDimensions", 'customDimensions', 'parameterName');
            $haveMetrics = $this->existing($api, "{$base}/customMetrics", 'customMetrics', 'parameterName');
            $haveKeyEvents = $this->existing($api, "{$base}/keyEvents", 'keyEvents', 'eventName');
        } catch (Throwable $e) {
            $this->error('Couldn\'t read the GA property: '.$e->getMessage());

            return self::FAILURE;
        }

        $plan = [
            'dimensions' => array_diff_key($dimensions, array_flip($haveDimensions)),
            'metrics' => array_diff_key($metrics, array_flip($haveMetrics)),
            'key events' => array_fill_keys(array_diff($keyEvents, $haveKeyEvents), ''),
        ];

        $this->info(sprintf(
            'Property %s: %d/%d dimensions, %d/%d metrics, %d/%d key events already set up.',
            $property,
            count($dimensions) - count($plan['dimensions']), count($dimensions),
            count($metrics) - count($plan['metrics']), count($metrics),
            count($keyEvents) - count($plan['key events']), count($keyEvents),
        ));

        if (array_sum(array_map('count', $plan)) === 0) {
            $this->info('Nothing to do — every CampBuddy event and parameter is reportable.');

            return self::SUCCESS;
        }

        foreach ($plan as $kind => $items) {
            foreach (array_keys($items) as $name) {
                $this->line("  + {$kind}: {$name}");
            }
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run — nothing changed.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($plan['dimensions'] as $param => $description) {
            $failed += $this->create($api, "{$base}/customDimensions", [
                'parameterName' => $param,
                'displayName' => $this->displayName($param),
                'description' => mb_substr($description, 0, 150),
                'scope' => 'EVENT',
            ], "dimension {$param}");
        }

        foreach ($plan['metrics'] as $param => $description) {
            $failed += $this->create($api, "{$base}/customMetrics", [
                'parameterName' => $param,
                'displayName' => $this->displayName($param),
                'description' => mb_substr($description, 0, 150),
                'scope' => 'EVENT',
                'measurementUnit' => 'STANDARD',
            ], "metric {$param}");
        }

        foreach (array_keys($plan['key events']) as $event) {
            $failed += $this->create($api, "{$base}/keyEvents", [
                'eventName' => $event,
                'countingMethod' => 'ONCE_PER_EVENT',
            ], "key event {$event}");
        }

        if ($failed > 0) {
            $this->error("{$failed} item(s) couldn't be created — see above.");

            return self::FAILURE;
        }

        $this->info('Done. New dimensions start filling in for events sent from now on (GA doesn\'t backfill).');

        return self::SUCCESS;
    }

    /** "session_title" → "Session title" (GA display names: letters, digits, spaces, underscores). */
    private function displayName(string $param): string
    {
        return ucfirst(str_replace('_', ' ', $param));
    }

    /**
     * @return array<int, string> the given field of every existing item, across all pages
     */
    private function existing(PendingRequest $api, string $url, string $key, string $field): array
    {
        $names = [];
        $pageToken = null;

        do {
            $response = $api->get($url, array_filter(['pageSize' => 200, 'pageToken' => $pageToken]))->throw()->json();
            foreach ($response[$key] ?? [] as $item) {
                $names[] = $item[$field] ?? null;
            }
            $pageToken = $response['nextPageToken'] ?? null;
        } while ($pageToken);

        return array_values(array_filter($names));
    }

    /** @return int 0 on success, 1 on failure (so the caller can count). */
    private function create(PendingRequest $api, string $url, array $body, string $label): int
    {
        $response = $api->post($url, $body);

        if ($response->successful()) {
            $this->line("  ✓ {$label}");

            return 0;
        }

        $this->error("  ✗ {$label}: ".($response->json('error.message') ?? 'HTTP '.$response->status()));

        return 1;
    }

    /**
     * An authorised client, from a service account key: a signed JWT
     * exchanged for an OAuth access token (Google's server-to-server flow).
     */
    private function client(): PendingRequest
    {
        $path = (string) ($this->option('credentials') ?: config('analytics.credentials'));

        if ($path === '' || ! is_readable($path)) {
            throw new RuntimeException('set GA_CREDENTIALS_PATH (or --credentials) to a readable service account JSON key.');
        }

        $key = json_decode((string) file_get_contents($path), true);

        if (! is_array($key) || empty($key['client_email']) || empty($key['private_key'])) {
            throw new RuntimeException('the credentials file isn\'t a service account JSON key.');
        }

        $b64 = fn (string $data) => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        $now = time();
        $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
            'iss' => $key['client_email'],
            'scope' => 'https://www.googleapis.com/auth/analytics.edit',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        if (! openssl_sign($unsigned, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('the service account private key couldn\'t sign a request.');
        }

        $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $unsigned.'.'.$b64($signature),
        ])->throw()->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Google didn\'t return an access token.');
        }

        return Http::withToken($token)->acceptJson()->timeout(20);
    }
}
