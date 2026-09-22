<?php

declare(strict_types=1);

namespace CampBuddy\Middleware;

use CampBuddy\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

final class SecurityHeadersMiddleware
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function __invoke(ServerRequestInterface $request, Handler $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' https://cdn.jsdelivr.net https://www.googletagmanager.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https://i.ytimg.com https://rajasthan.wordcamp.org https://wpsimplified.in https://www.google-analytics.com",
            "connect-src 'self' https://www.google-analytics.com",
            "font-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
        ]);

        $response = $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()')
            ->withHeader('Content-Security-Policy', $csp);

        if ($this->settings->isProduction() && str_starts_with($this->settings->appUrl, 'https://')) {
            $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
