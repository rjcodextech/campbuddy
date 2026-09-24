<?php

namespace App\Providers;

use App\Rules\NotPrivateNetworkUrl;
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

        // Named on purpose: each limiter keeps its own counters. At a WordCamp
        // most attendees share the venue wifi's one public IP, so a per-IP
        // limit alone would throttle the whole room at once. Limits are per
        // person where a request says who it is (a discovery owner token, a
        // device id), with a far higher per-IP ceiling as the backstop.
        RateLimiter::for('api-general', fn (Request $request) => Limit::perMinute(600)->by('api-general:'.$request->ip()));

        RateLimiter::for('api-writes', function (Request $request) {
            $token = $request->bearerToken();

            return $token
                ? [
                    Limit::perMinute(30)->by('api-writes:t:'.hash('sha256', $token)),
                    Limit::perMinute(300)->by('api-writes:ip:'.$request->ip()),
                ]
                // Joining, deal leads: nothing identifies the person yet.
                : [Limit::perMinute(120)->by('api-writes:anon:'.$request->ip())];
        });

        RateLimiter::for('api-bookmarks', fn (Request $request) => [
            Limit::perMinute(30)->by('api-bookmarks:d:'.sha1((string) $request->input('device_id', $request->ip()))),
            Limit::perMinute(600)->by('api-bookmarks:ip:'.$request->ip()),
        ]);
    }
}
