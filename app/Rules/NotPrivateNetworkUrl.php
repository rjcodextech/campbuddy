<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * SSRF guard: the ingestion and branding jobs fetch this URL
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

        if (self::isPrivateHost($host)) {
            $fail("The {$attribute} resolves to a private or reserved network address, which isn't allowed.");
        }
    }

    /**
     * True when a host name (or IP literal) points at loopback, a private
     * range or a reserved range. Shared with the outbound HTTP client's
     * redirect check (AppServiceProvider), so a public WordCamp address that
     * answers with a redirect can't bounce a fetch into the internal network.
     */
    public static function isPrivateHost(string $host): bool
    {
        $host = trim($host, '[]');
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
