<?php

namespace App\Providers;

use App\Rules\NotPrivateNetworkUrl;
use App\Support\ApiClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        Http::globalOptions([
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
        ]);

        // Named on purpose: each limiter keeps its own counters. Counted per
        // phone (ApiClient::key) so a venue's shared wifi or a mobile network's
        // shared address doesn't throttle everyone at once; the per-address
        // limit is only a high backstop. Numbers: config/campbuddy.php.
        RateLimiter::for('api-general', fn (Request $request) => $this->apiLimits($request, 'reads'));
        RateLimiter::for('api-writes', fn (Request $request) => $this->apiLimits($request, 'writes', 'w'));
        // Saving a morning's sessions is a quick burst: twice the write allowance.
        RateLimiter::for('api-bookmarks', fn (Request $request) => $this->apiLimits($request, 'writes', 'b', 2));
    }

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
