<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every attendee-facing {event:slug} route needs the same guard: mirror
 * the §10 public API filter (status=active AND is_visible=true) or 404
 * rather than leak a draft/hidden event. Centralized here instead of
 * repeated per controller method (§20.7).
 */
class EnsureEventIsPublic
{
    public function handle(Request $request, Closure $next): Response
    {
        $event = $request->route('event');

        abort_unless($event && $event->status === 'active' && $event->is_visible, 404);

        return $next($request);
    }
}
