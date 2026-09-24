<?php

namespace App\Support;

/**
 * Third-party data (a scraped attendee link, an avatar address) ends up as an
 * href or an image source in CampBuddy's own pages. A `javascript:` or `data:`
 * value there would run in CampBuddy's origin, so only web addresses pass.
 */
class SafeUrl
{
    /** The address if it is an http(s) web address, otherwise null. */
    public static function web(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        return preg_match('#^https?://[^\s<>"\']+$#i', $url) === 1 && strlen($url) <= 500 ? $url : null;
    }
}
