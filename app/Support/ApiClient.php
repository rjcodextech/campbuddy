<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Who an API request is from, for rate limiting only: the discovery owner
 * token when there is one (discovery writes), otherwise the app's anonymous
 * per-phone device id (X-CampBuddy-Device header, or the device_id a
 * reminder request carries). Hashed — never stored or logged — and null when
 * the request doesn't say, which gets the smaller anonymous allowance.
 */
class ApiClient
{
    private const DEVICE_ID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public static function key(Request $request): ?string
    {
        if ($token = $request->bearerToken()) {
            return 't:'.hash('sha256', $token);
        }

        $device = (string) ($request->header('X-CampBuddy-Device') ?: $request->input('device_id', ''));

        return preg_match(self::DEVICE_ID, $device) === 1 ? 'd:'.hash('sha256', strtolower($device)) : null;
    }
}
