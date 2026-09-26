<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The address of a WordCamp's own site: https, on wordcamp.org or one of its
 * subdomains, with no login details and no odd port in it.
 *
 * What an event manager may set as an event's source. Everything the app shows
 * from a WordCamp — sessions, speakers, sponsors, logos — is fetched from this
 * address on a schedule, so an address anyone could point elsewhere would let a
 * lower-trust account put someone else's content in front of every attendee.
 * (Admins are not held to this; NotPrivateNetworkUrl still applies to both.)
 *
 * A value equal to `$unchanged` passes: an event an admin already pointed
 * somewhere unusual must stay saveable by its manager, who isn't changing it.
 */
class WordCampUrl implements ValidationRule
{
    public function __construct(private readonly ?string $unchanged = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->unchanged !== null && $value === $this->unchanged) {
            return;
        }

        $parts = is_string($value) ? parse_url($value) : false;
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';

        $ok = is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && ($host === 'wordcamp.org' || str_ends_with($host, '.wordcamp.org'))
            && ! isset($parts['user'])
            && (! isset($parts['port']) || (int) $parts['port'] === 443);

        if (! $ok) {
            $fail('The '.str_replace('_', ' ', $attribute).' must be an https address on wordcamp.org, like https://rajasthan.wordcamp.org/2026.');
        }
    }
}
