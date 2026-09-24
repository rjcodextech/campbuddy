<?php

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
            'event.public' => \App\Http\Middleware\EnsureEventIsPublic::class,
        ]);

        // Production sits behind Cloudflare (docs: deployment). Without this
        // Laravel sees the CDN edge's IP for every visitor — so the per-IP
        // rate limits (60/min on the API, 10/min on writes) would be shared
        // by everyone in a city — and sees plain http when TLS ends at the
        // edge, which yields http:// asset URLs (mixed content) on https pages.
        $middleware->trustProxies(at: '*');

        // Every response — pages, the API, the admin panel, /up.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
