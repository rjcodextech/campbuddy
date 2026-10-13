<?php

namespace App\Http\Middleware;

use App\Support\AppOrganizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token sign-in for the native app's organizer endpoints
 * (App\Support\AppOrganizer). `app.organizer` lets admins and event managers
 * in; `app.organizer:admin` only admins. The signed-in person is available as
 * $request->user() and the role as $request->attributes->get('app_role').
 */
class AppOrganizerAuth
{
    public function handle(Request $request, Closure $next, ?string $only = null): Response
    {
        $found = AppOrganizer::resolve($request->bearerToken());

        if (! $found) {
            return response()->json(['error' => ['code' => 'unauthenticated', 'message' => 'Sign in again.']], 401);
        }
        if ($only !== null && $found['role'] !== $only) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => 'This is for administrators.']], 403);
        }

        $request->setUserResolver(fn () => $found['user']);
        $request->attributes->set('app_role', $found['role']);

        return $next($request);
    }
}
