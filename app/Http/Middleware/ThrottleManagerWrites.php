<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caps how many changes one event manager can save per minute.
 *
 * Every save clears the caches behind an event's attendee pages, so a script
 * (or a stuck double-click loop) saving over and over would keep every open
 * app re-fetching. Counted per manager, not per address: a venue's wifi puts a
 * dozen organizers behind one address, and a per-address limit would lock them
 * out of each other's work. Only writes count — reading a page is free.
 * Deliberately not `throttle:` — that one counts per address unless the
 * default guard has a user, which a manager never is.
 */
class ThrottleManagerWrites
{
    public const LIMIT_PER_MINUTE = 60;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $manager = $request->user(EnsureEventManager::GUARD);
        $key = 'manager-writes:'.($manager?->getAuthIdentifier() ?? $request->ip());

        if (RateLimiter::tooManyAttempts($key, self::LIMIT_PER_MINUTE)) {
            return response('Too many changes in a minute — wait a moment and try again.', 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
