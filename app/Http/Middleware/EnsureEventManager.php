<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The gate to everything under /manager except the sign-in page: a signed-in,
 * still-active event manager. Deliberately not the framework's `auth`
 * middleware — that one looks at the admin guard and sends people to the admin
 * login.
 *
 * Two things that make an admin's change take effect at once, not only at the
 * next sign-in: switching a manager off, and changing their password (a
 * fingerprint of the password stays in the session, so every other session
 * that still holds the old one is signed out).
 */
class EnsureEventManager
{
    public const GUARD = 'manager';

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard(self::GUARD);
        $manager = $guard->user();

        if ($manager === null) {
            return $this->toLogin($request);
        }

        $fingerprint = hash_hmac('sha256', (string) $manager->getAuthPassword(), (string) config('app.key'));
        $known = $request->session()->get('manager.password');

        if (! $manager->is_active || ($known !== null && ! hash_equals($known, $fingerprint))) {
            $guard->logout();
            $request->session()->forget(['manager.password', 'manager.intended']);

            return $this->toLogin($request, $manager->is_active ? null : 'This account has been switched off. Ask an admin.');
        }

        // First request of this session (or a "remember me" sign-in): remember the fingerprint.
        if ($known === null) {
            $request->session()->put('manager.password', $fingerprint);
        }

        // Pages with someone's session in them are never kept by the browser or a proxy: after signing
        // out, the Back button shows the sign-in page, not the last screen.
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function toLogin(Request $request, ?string $message = null): Response
    {
        // Come back to the page they asked for after signing in — our own key,
        // so an admin's "intended" URL is never mixed up with it.
        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            $request->session()->put('manager.intended', $request->fullUrl());
        }

        $redirect = redirect()->route('manager.login');

        return $message ? $redirect->withErrors(['email' => $message]) : $redirect;
    }
}
