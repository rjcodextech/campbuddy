<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response headers that cost nothing and close off whole classes of
 * problems: MIME sniffing, clickjacking (the admin panel especially),
 * referrer leakage to the third-party sites the in-app browser opens, and
 * device features CampBuddy never uses. A header a controller already set
 * (PublicStorageController's own nosniff, say) is left alone.
 *
 * Deliberately no Content-Security-Policy or HSTS here: the pages carry
 * inline analytics/styles, so a CSP has to be written and tested against a
 * real deployment, and HSTS is best set once at the edge (Cloudflare) where
 * the https decision is made.
 */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
