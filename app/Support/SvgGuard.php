<?php

namespace App\Support;

/**
 * A logo or favicon is an image, never a program. An SVG can carry <script>,
 * event-handler attributes and javascript: links, and one fetched from a
 * third-party WordCamp site — or uploaded — is served from CampBuddy's own
 * origin. Anything that looks executable is refused rather than "cleaned".
 */
class SvgGuard
{
    public static function isSafe(string $svg): bool
    {
        if (! preg_match('/<svg[\s>]/i', $svg)) {
            return false;
        }

        return ! preg_match('/<\s*(script|foreignObject|iframe|object|embed)\b|\son[a-z]+\s*=|javascript\s*:|<!ENTITY/i', $svg);
    }
}
