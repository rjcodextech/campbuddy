<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The admin Analytics page: reads the GA4 property through the Data API and
 * shapes it into the page's sections. Read-only (analytics.readonly scope).
 *
 * The property has more than one web stream (CampBuddy and WPSimplified share
 * it), so every report is filtered to one stream. Bots can be left out too:
 * headless browsers report a handful of tell-tale screen sizes.
 *
 * Results are cached for an hour per (stream, dates, bots), so opening the page
 * costs nothing on the shared host and little of the GA quota.
 */
class AnalyticsReport
{
    public const PERIODS = [
        'today' => 'Today (so far)',
        '7' => 'Last 7 days',
        '10' => 'Last 10 days',
        '30' => 'Last 30 days',
        '90' => 'Last 90 days',
        'custom' => 'Custom dates',
    ];

    public const DEFAULT_PERIOD = '10';

    public const CACHE_SECONDS = 3600;

    /** Screen sizes that only bots and headless browsers report. */
    public const BOT_SCREENS = ['800x600', '1600x1600', '2000x2000', '1024x1024', '1600x1200'];

    /** GA keeps standard report data for 14 months; older dates come back empty. */
    private const OLDEST_DAYS = 430;

    /** What's missing before the page can read GA, or null when it's set up. */
    public static function setupProblem(): ?string
    {
        if (! config('analytics.property_id')) {
            return 'GA_PROPERTY_ID is not set in .env.';
        }

        $path = self::credentialsPath();
        if ($path === null || ! is_readable($path)) {
            return 'GA_CREDENTIALS_PATH is not set, or the service account key file is not on this server.';
        }

        return null;
    }

    /**
     * @return array{stream: ?string, period: string, from: string, to: string, bots: bool, invalid: bool}
     */
    public static function filters(Request $request): array
    {
        $period = (string) $request->query('period', self::DEFAULT_PERIOD);
        $period = array_key_exists($period, self::PERIODS) ? $period : self::DEFAULT_PERIOD;
        $today = Carbon::today();
        $invalid = false;

        if ($period === 'custom') {
            $from = self::date($request->query('from'));
            $to = self::date($request->query('to'));

            if (! $from || ! $to || $from->gt($to) || $to->gt($today) || $from->lt($today->copy()->subDays(self::OLDEST_DAYS))) {
                $invalid = true;
                $period = self::DEFAULT_PERIOD;
            }
        }

        if ($period === 'today') {
            $from = $to = $today;
        } elseif ($period !== 'custom') {
            // Whole days only: today is still filling in.
            $to = $today->copy()->subDay();
            $from = $to->copy()->subDays((int) $period - 1);
        }

        $stream = $request->query('stream');

        return [
            'stream' => is_string($stream) && ctype_digit($stream) ? $stream : null,
            'period' => $period,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            // A checkbox: absent on a submitted form means "off"; a first visit has no query at all.
            'bots' => $request->query() === [] ? true : $request->boolean('hide_bots'),
            'invalid' => $invalid,
        ];
    }

    /**
     * The property's web streams, [stream id => name], with the attendee app's
     * own stream (GA_MEASUREMENT_ID) first.
     *
     * @return array<string, string>
     */
    public static function streams(): array
    {
        $property = config('analytics.property_id');

        return Cache::remember("ga-report:streams:{$property}", 86400, function () use ($property) {
            $streams = self::client()
                ->get("https://analyticsadmin.googleapis.com/v1beta/properties/{$property}/dataStreams")
                ->throw()
                ->json('dataStreams', []);

            $ours = config('services.google_analytics.measurement_id');
            usort($streams, fn ($a, $b) => (int) (($b['webStreamData']['measurementId'] ?? null) === $ours) <=> (int) (($a['webStreamData']['measurementId'] ?? null) === $ours));

            $list = [];
            foreach ($streams as $s) {
                $id = substr(strrchr((string) $s['name'], '/'), 1);
                $uri = parse_url($s['webStreamData']['defaultUri'] ?? '', PHP_URL_HOST);
                $list[$id] = trim(($s['displayName'] ?? $id).($uri ? " ({$uri})" : ''));
            }

            return $list;
        });
    }

    /**
     * Every report for the page, for one stream and date range.
     *
     * @return array{fetched_at: string, reports: array<string, list<array<string, string|float>>>}
     */
    public static function build(string $stream, string $from, string $to, bool $hideBots, bool $fresh = false): array
    {
        $key = 'ga-report:'.md5(implode('|', [config('analytics.property_id'), $stream, $from, $to, (int) $hideBots]));

        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, self::CACHE_SECONDS, function () use ($stream, $from, $to, $hideBots) {
            $base = [['filter' => ['fieldName' => 'streamId', 'stringFilter' => ['value' => $stream, 'matchType' => 'EXACT']]]];
            if ($hideBots) {
                $base[] = ['notExpression' => ['filter' => ['fieldName' => 'screenResolution', 'inListFilter' => ['values' => self::BOT_SCREENS]]]];
            }

            $requests = [];
            foreach (self::definitions() as $name => $def) {
                $filters = $base;
                if (isset($def['events'])) {
                    $filters[] = ['filter' => ['fieldName' => 'eventName', 'inListFilter' => ['values' => (array) $def['events']]]];
                }

                $requests[$name] = array_filter([
                    'dateRanges' => [['startDate' => $from, 'endDate' => $to]],
                    'dimensions' => array_map(fn ($d) => ['name' => $d], $def['dims'] ?? []),
                    'metrics' => array_map(fn ($m) => ['name' => $m], $def['metrics']),
                    'dimensionFilter' => ['andGroup' => ['expressions' => $filters]],
                    'orderBys' => isset($def['order'])
                        ? [$def['order'] === 'date'
                            ? ['dimension' => ['dimensionName' => 'date']]
                            : ['metric' => ['metricName' => $def['order']], 'desc' => true]]
                        : null,
                    'limit' => $def['limit'] ?? 50,
                ]);
            }

            $client = self::client();
            $property = config('analytics.property_id');
            $reports = [];

            // The Data API takes up to five reports per batch call.
            foreach (array_chunk($requests, 5, true) as $chunk) {
                $results = $client
                    ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$property}:batchRunReports", ['requests' => array_values($chunk)])
                    ->throw()
                    ->json('reports', []);

                foreach (array_keys($chunk) as $i => $name) {
                    $reports[$name] = self::rows($results[$i] ?? []);
                }
            }

            return ['fetched_at' => now()->toIso8601String(), 'reports' => $reports];
        });
    }

    /**
     * The reports the page shows. dims/metrics are GA4 API names; `events`
     * narrows a report to those event names; `order` sorts by a metric (desc)
     * or by date.
     *
     * @return array<string, array{dims?: list<string>, metrics: list<string>, events?: string|list<string>, order?: string, limit?: int}>
     */
    public static function definitions(): array
    {
        return [
            'totals' => ['metrics' => ['activeUsers', 'newUsers', 'sessions', 'engagedSessions', 'engagementRate', 'screenPageViews', 'eventCount', 'userEngagementDuration']],
            'daily' => ['dims' => ['date'], 'metrics' => ['activeUsers', 'newUsers', 'sessions', 'screenPageViews'], 'order' => 'date', 'limit' => 500],
            'new_returning' => ['dims' => ['newVsReturning'], 'metrics' => ['activeUsers']],
            'events' => ['dims' => ['eventName'], 'metrics' => ['eventCount', 'totalUsers'], 'order' => 'eventCount', 'limit' => 200],
            'wordcamps' => ['dims' => ['customEvent:event_slug'], 'metrics' => ['totalUsers', 'eventCount', 'userEngagementDuration'], 'order' => 'totalUsers', 'limit' => 100],

            'country' => ['dims' => ['country'], 'metrics' => ['activeUsers'], 'order' => 'activeUsers', 'limit' => 15],
            'city' => ['dims' => ['city'], 'metrics' => ['activeUsers'], 'order' => 'activeUsers', 'limit' => 15],
            'device' => ['dims' => ['deviceCategory'], 'metrics' => ['activeUsers'], 'order' => 'activeUsers'],
            'os' => ['dims' => ['operatingSystem'], 'metrics' => ['activeUsers'], 'order' => 'activeUsers', 'limit' => 8],
            'browser' => ['dims' => ['browser'], 'metrics' => ['activeUsers'], 'order' => 'activeUsers', 'limit' => 8],

            'source' => ['dims' => ['sessionSourceMedium'], 'metrics' => ['sessions', 'activeUsers'], 'order' => 'sessions', 'limit' => 15],
            'first_source' => ['dims' => ['firstUserSourceMedium'], 'metrics' => ['newUsers'], 'order' => 'newUsers', 'limit' => 12],
            'pages' => ['dims' => ['pagePath'], 'metrics' => ['screenPageViews', 'activeUsers', 'userEngagementDuration'], 'order' => 'screenPageViews', 'limit' => 25],
            'hour' => ['dims' => ['hour'], 'metrics' => ['activeUsers'], 'limit' => 24],
            'weekday' => ['dims' => ['dayOfWeek'], 'metrics' => ['activeUsers'], 'limit' => 7],

            'display_mode' => ['dims' => ['customEvent:display_mode'], 'metrics' => ['totalUsers', 'eventCount'], 'events' => 'page_context'],
            'nav_tab' => ['dims' => ['customEvent:tab'], 'metrics' => ['eventCount', 'totalUsers'], 'events' => 'nav_tab_click', 'order' => 'eventCount'],
            'explore_tab' => ['dims' => ['customEvent:tab'], 'metrics' => ['eventCount', 'totalUsers'], 'events' => 'explore_tab_view', 'order' => 'eventCount'],
            'saved_sessions' => ['dims' => ['customEvent:session_title'], 'metrics' => ['eventCount', 'totalUsers'], 'events' => 'session_save', 'order' => 'eventCount', 'limit' => 15],
            'quests' => ['dims' => ['customEvent:quest_title'], 'metrics' => ['eventCount', 'totalUsers'], 'events' => 'quest_complete', 'order' => 'eventCount', 'limit' => 15],

            'deals' => ['dims' => ['eventName', 'customEvent:offer_title'], 'metrics' => ['eventCount', 'totalUsers'], 'events' => ['deal_open', 'free_steal_open', 'generate_lead', 'deal_code_copy'], 'order' => 'eventCount', 'limit' => 30],
            'sponsors' => ['dims' => ['customEvent:sponsor_name'], 'metrics' => ['eventCount', 'totalUsers'], 'events' => 'sponsor_open', 'order' => 'eventCount', 'limit' => 20],
            'guide_sections' => ['dims' => ['customEvent:section'], 'metrics' => ['totalUsers'], 'events' => 'guide_section_view', 'order' => 'totalUsers'],
            'faq' => ['dims' => ['customEvent:question'], 'metrics' => ['eventCount'], 'events' => 'faq_open', 'order' => 'eventCount', 'limit' => 10],
            'install' => ['dims' => ['eventName', 'customEvent:platform', 'customEvent:outcome'], 'metrics' => ['eventCount', 'totalUsers'], 'events' => ['install_prompt_open', 'install_prompt_result', 'install_complete']],

            'reminder' => ['dims' => ['customEvent:result'], 'metrics' => ['eventCount', 'totalUsers'], 'events' => 'reminder_offer', 'order' => 'eventCount'],
            'vitals' => ['dims' => ['customEvent:metric_name', 'customEvent:metric_rating'], 'metrics' => ['eventCount'], 'events' => 'web_vitals'],
            'exceptions' => ['dims' => ['customEvent:description'], 'metrics' => ['eventCount', 'totalUsers'], 'events' => 'exception', 'order' => 'eventCount', 'limit' => 15],
            'api_errors' => ['dims' => ['customEvent:endpoint', 'customEvent:method', 'customEvent:status'], 'metrics' => ['eventCount', 'totalUsers'], 'events' => 'api_error', 'order' => 'eventCount', 'limit' => 15],
        ];
    }

    /** "storage/app/…" in .env is relative to the app, not to public/ where web requests run. */
    private static function credentialsPath(): ?string
    {
        $path = (string) config('analytics.credentials');

        if ($path === '') {
            return null;
        }

        return preg_match('#^([a-zA-Z]:)?[\\\\/]#', $path) ? $path : base_path($path);
    }

    private static function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<array<string, string|float>> */
    private static function rows(array $report): array
    {
        $dims = array_column($report['dimensionHeaders'] ?? [], 'name');
        $metrics = array_column($report['metricHeaders'] ?? [], 'name');
        $rows = [];

        foreach ($report['rows'] ?? [] as $row) {
            $out = [];
            foreach ($dims as $i => $name) {
                $out[$name] = (string) ($row['dimensionValues'][$i]['value'] ?? '');
            }
            foreach ($metrics as $i => $name) {
                $out[$name] = (float) ($row['metricValues'][$i]['value'] ?? 0);
            }
            $rows[] = $out;
        }

        return $rows;
    }

    /**
     * An authorised client from the service account key: a signed JWT
     * exchanged for an access token, kept for 50 minutes.
     */
    private static function client(): PendingRequest
    {
        if ($problem = self::setupProblem()) {
            throw new RuntimeException($problem);
        }

        $key = json_decode((string) file_get_contents(self::credentialsPath()), true);

        if (! is_array($key) || empty($key['client_email']) || empty($key['private_key'])) {
            throw new RuntimeException('The GA credentials file is not a service account JSON key.');
        }

        $token = Cache::remember('ga-report:token:'.md5($key['client_email']), 3000, function () use ($key) {
            $b64 = fn (string $data) => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
            $now = time();
            $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
                'iss' => $key['client_email'],
                'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            if (! openssl_sign($unsigned, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('The service account private key could not sign a request.');
            }

            $token = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.$b64($signature),
            ])->throw()->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Google did not return an access token.');
            }

            return $token;
        });

        return Http::withToken($token)->acceptJson()->timeout(30);
    }

    /** A short, readable reason from a failed GA call (Google's own message when there is one). */
    public static function explain(\Throwable $e): string
    {
        if ($e instanceof RequestException) {
            $message = $e->response->json('error.message') ?? $e->response->json('error_description');
            if (is_string($message) && $message !== '') {
                return str($message)->limit(400)->toString();
            }
        }

        return str($e->getMessage())->limit(400)->toString();
    }
}
