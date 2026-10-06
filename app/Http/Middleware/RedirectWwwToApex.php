<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * www.campbuddy.club → campbuddy.club, same path and query.
 *
 * The browser keeps an attendee's saved sessions, Camp Card and discovery
 * profile per origin, so the same person on www and on the bare domain had
 * two separate copies (GA showed people landing on www). One address keeps
 * one copy, and search engines see one site.
 */
class RedirectWwwToApex
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if (! str_starts_with($host, 'www.')) {
            return $next($request);
        }

        $url = $request->getScheme().'://'.substr($host, 4).$request->getRequestUri();

        // 308 keeps a POST a POST; a page view gets the usual permanent redirect.
        return redirect()->away($url, $request->isMethod('GET') || $request->isMethod('HEAD') ? 301 : 308);
    }
}
