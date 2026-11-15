<?php

namespace App\Providers;

use App\Rules\NotPrivateNetworkUrl;
use App\Support\ApiClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Message\UriInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // On the live site, artisan commands that empty the database
        // (migrate:fresh, migrate:refresh, migrate:reset, db:wipe) refuse to run.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // When the site is configured as https (APP_URL=https://…), every
        // generated link is https regardless of what the proxy/host reports —
        // a login page or manifest link that downgrades to http is a
        // mixed-content failure waiting to happen.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Every fetch CampBuddy makes of someone else's site (WordCamp REST
        // APIs, Attendees pages, logos): say who is asking, so a site admin
        // reading their logs knows what the traffic is, and follow redirects
        // only to other public web addresses. The admin-entered URL is checked
        // for private addresses when saved (NotPrivateNetworkUrl), but a
        // public site answering with a redirect to 127.0.0.1 or a cloud
        // metadata address would otherwise walk straight past that check.
        $httpOptions = [
            'headers' => [
                'User-Agent' => 'CampBuddy/2.0 (+'.rtrim((string) config('app.url'), '/').'; WordCamp attendee companion)',
            ],
            'allow_redirects' => [
                'max' => 5,
                'protocols' => ['http', 'https'],
                'on_redirect' => function ($request, $response, UriInterface $uri) {
                    if (NotPrivateNetworkUrl::isPrivateHost($uri->getHost())) {
                        throw new \RuntimeException("Refused to follow a redirect to a private or reserved address ({$uri->getHost()}).");
                    }
                },
            ],
        ];

        if (class_exists(\Composer\CaBundle\CaBundle::class)) {
            $caPath = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
            if (is_file($caPath)) {
                $httpOptions['verify'] = $caPath;
            }
        }

        Http::globalOptions($httpOptions);

        // Named on purpose: each limiter keeps its own counters. Counted per
        // phone (ApiClient::key) so a venue's shared wifi or a mobile network's
        // shared address doesn't throttle everyone at once; the per-address
        // limit is only a high backstop. Numbers: config/campbuddy.php.
        RateLimiter::for('api-general', fn (Request $request) => $this->apiLimits($request, 'reads'));
        RateLimiter::for('api-writes', fn (Request $request) => $this->apiLimits($request, 'writes', 'w'));
        // Saving a morning's sessions is a quick burst: twice the write allowance.
        RateLimiter::for('api-bookmarks', fn (Request $request) => $this->apiLimits($request, 'writes', 'b', 2));

        // Every other limit is named too. A plain `throttle:N,M` keeps ONE counter per
        // address (or per signed-in id) shared by every plain throttle on the site: calendar
        // files used up the organizer sign-in, page views used up the sponsor form's submit,
        // and a re-fetch counted twice against the organizer's saves.
        foreach (self::ADDRESS_LIMITS as $name => [$attempts, $minutes]) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinutes($minutes, $attempts)->by($request->ip()));
        }

        // The native app's organizer side. Laravel runs throttles before the route's
        // bearer-token check (AppOrganizerAuth), so these count per sign-in token — never
        // per address, which the organizers share with every attendee on the venue wifi.
        // A request without a token (always refused) counts against its address only.
        foreach (self::ORGANIZER_LIMITS as $name => $perMinute) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($perMinute)->by(
                $request->bearerToken() ? 't:'.hash('sha256', $request->bearerToken()) : 'ip:'.$request->ip()
            ));
        }
    }

    /** Public forms, per address: name => [attempts, minutes]. */
    private const ADDRESS_LIMITS = [
        'calendar-file' => [30, 1],
        'roster-removal' => [20, 1],
        'sponsor-deals' => [30, 1],
        'sponsor-deals-submit' => [5, 10],
        'app-login' => [20, 1],
    ];

    /** The organizer app, per sign-in token: name => per minute. */
    private const ORGANIZER_LIMITS = [
        'app-writes' => 60,
        'app-broadcast' => 10,
        'app-fetch' => 10,
        'app-site' => 10,
        'app-publish' => 20,
        'app-export' => 20,
        'app-analytics' => 30,
        'app-discover' => 5,
    ];

    /**
     * @return array<int, Limit>
     */
    private function apiLimits(Request $request, string $kind, string $bucket = 'r', int $factor = 1): array
    {
        $limits = config('campbuddy.rate_limits');
        $who = ApiClient::key($request);
        $ip = $request->ip();

        if ($who === null) {
            return [Limit::perMinute($limits["anonymous_{$kind}"] * $factor)->by("api:{$bucket}:anon:{$ip}")];
        }

        return [
            Limit::perMinute($limits["device_{$kind}"] * $factor)->by("api:{$bucket}:who:{$who}"),
            Limit::perMinute($limits["address_{$kind}"] * $factor)->by("api:{$bucket}:ip:{$ip}"),
        ];
    }
}
