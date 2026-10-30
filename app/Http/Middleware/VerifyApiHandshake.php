<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API handshake between the website/PWA, the app and the server, in three steps:
 *
 *   1. Client hello — every call says which client sent it (X-CampBuddy-Client: pwa | native | web),
 *      from which phone (X-CampBuddy-Device, the anonymous id) and when (X-CampBuddy-Timestamp, unix
 *      seconds). A value that is there but malformed is refused (400). A malformed device id is not
 *      refused: it only gets the anonymous rate limit (ApiClient), as it always did.
 *   2. Freshness — a write sent more than max_skew_seconds away from the server's clock is answered
 *      401 HANDSHAKE_TIME_SKEW with the server's time (body and X-CampBuddy-Server-Time). The PWA and
 *      the app then retry once with that time, so a phone whose clock is wrong still works.
 *   3. Server proof — every answer carries X-CampBuddy-Handshake and X-CampBuddy-Server-Time; the
 *      clients only trust a JSON answer (a captive-portal page is treated as "no connection").
 *
 * Nothing here is a secret: a public app cannot hide one. Who may change what is still decided by the
 * owner tokens and organizer tokens, over HTTPS, behind the rate limits. Without strict mode
 * (config campbuddy.handshake.strict) a request without the headers passes — older app versions and
 * web pages cached on phones don't send them.
 */
class VerifyApiHandshake
{
    public const VERSION = 'v1';

    private const CLIENTS = ['pwa', 'native', 'web'];

    private const WRITES = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        $strict = (bool) config('campbuddy.handshake.strict', false);
        $client = $request->header('X-CampBuddy-Client');
        $timestamp = $request->header('X-CampBuddy-Timestamp');
        $write = in_array($request->method(), self::WRITES, true);

        if ($client !== null && ! in_array($client, self::CLIENTS, true)) {
            return $this->refuse('INVALID_HANDSHAKE_CLIENT', 'Unknown client in the handshake.', 400);
        }

        if ($timestamp !== null && ! preg_match('/^\d{9,11}$/', $timestamp)) {
            return $this->refuse('INVALID_HANDSHAKE_TIMESTAMP', 'The handshake time must be unix seconds.', 400);
        }

        if ($strict && $write && ($client === null || $timestamp === null)) {
            return $this->refuse('HANDSHAKE_REQUIRED', 'This request is missing the CampBuddy handshake. Please update the app or reload the page.', 400);
        }

        if ($write && $timestamp !== null && abs(time() - (int) $timestamp) > (int) config('campbuddy.handshake.max_skew_seconds', 300)) {
            return $this->refuse('HANDSHAKE_TIME_SKEW', "Your phone's clock is off. Retry with the server time.", 401, ['X-CampBuddy-Handshake-Error' => 'time-skew']);
        }

        return $this->sign($next($request));
    }

    private function refuse(string $code, string $message, int $status, array $headers = []): JsonResponse
    {
        $response = response()->json([
            'error' => ['code' => $code, 'message' => $message, 'server_time' => time()],
        ], $status, $headers + ['Cache-Control' => 'no-store']);

        return $this->sign($response);
    }

    private function sign(Response $response): Response
    {
        $response->headers->set('X-CampBuddy-Handshake', self::VERSION);
        $response->headers->set('X-CampBuddy-Server-Time', (string) time());

        return $response;
    }
}
