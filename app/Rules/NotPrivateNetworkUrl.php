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

        if (strtolower($host) === 'localhost') {
            return true;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        // Handle IPv4-mapped IPv6 (e.g. ::ffff:127.0.0.1)
        if (str_starts_with(strtolower($ip), '::ffff:')) {
            $ip = substr($ip, 7);
        }

        $isPublic = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;

        if (! $isPublic) {
            return true;
        }

        // Additional link-local (169.254.0.0/16) and 0.0.0.0/8 protection
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long !== false) {
                // 169.254.0.0/16 link-local
                if (($long & 0xFFFF0000) === (int) 0xA9FE0000) {
                    return true;
                }
                // 0.0.0.0/8
                if (($long & 0xFF000000) === 0) {
                    return true;
                }
                // 127.0.0.0/8
                if (($long & 0xFF000000) === 0x7F000000) {
                    return true;
                }
            }
        }

        return false;
    }
}
