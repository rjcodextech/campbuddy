<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * SSRF guard: the ingestion and branding jobs (§5.2, §8.2) fetch this URL
 * server-side on a schedule. An admin account is a trust boundary, not a
 * guarantee — this stops a malicious or compromised admin from pointing
 * CampBuddy's own server at its internal network or loopback interface.
 */
class NotPrivateNetworkUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $host = parse_url((string) $value, PHP_URL_HOST);

        if (! $host) {
            $fail("The {$attribute} must be a valid URL.");

            return;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        $isPrivate = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;

        if ($isPrivate) {
            $fail("The {$attribute} resolves to a private or reserved network address, which isn't allowed.");
        }
    }
}
