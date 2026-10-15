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
        // Strip null bytes and control chars that can conceal payload tokens
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $svg);

        if (! preg_match('/<svg[\s>]/i', $clean)) {
            return false;
        }

        // Iteratively decode HTML & XML entities to catch obfuscation like &colon; or &#x61;
        $decoded = $clean;
        for ($i = 0; $i < 3; $i++) {
            $prev = $decoded;
            $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $decoded = rawurldecode($decoded);
            if ($decoded === $prev) {
                break;
            }
        }

        // Block dangerous elements, protocols, event handlers, and XXE constructs across both raw and decoded forms
        $dangerousPattern = '/<\s*(script|foreignObject|iframe|object|embed|animate|set)\b|\son[a-z0-9_-]+\s*=|javascript\s*:|vbscript\s*:|data\s*:\s*text\/html|<!(?:DOCTYPE|ENTITY)/i';

        if (preg_match($dangerousPattern, $clean) || preg_match($dangerousPattern, $decoded)) {
            return false;
        }

        return true;
    }
}
