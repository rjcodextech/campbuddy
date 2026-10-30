<?php

use App\Http\Middleware\EnsureEventIsPublic;
use App\Http\Middleware\RedirectWwwToApex;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'event.public' => EnsureEventIsPublic::class,
            'api.handshake' => \App\Http\Middleware\VerifyApiHandshake::class,
        ]);

        // Production sits behind Cloudflare (docs: deployment). Without this
        // Laravel sees the CDN edge's IP for every visitor — so the per-IP
        // rate limits (60/min on the API, 10/min on writes) would be shared
        // by everyone in a city — and sees plain http when TLS ends at the
        // edge, which yields http:// asset URLs (mixed content) on https pages.
        $trustedProxies = env('TRUSTED_PROXIES') ? explode(',', (string) env('TRUSTED_PROXIES')) : [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
            '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
            '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
            '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
            '127.0.0.1', '::1',
        ];
        $middleware->trustProxies(at: env('APP_ENV') === 'production' ? $trustedProxies : '*');

        // Every response — pages, the API, the admin panel, /up.
        $middleware->append(SecurityHeaders::class);

        // One address for the app: www.campbuddy.club → campbuddy.club.
        // Inside TrustProxies and SecurityHeaders so the redirect keeps https behind Cloudflare.
        $middleware->append(RedirectWwwToApex::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
