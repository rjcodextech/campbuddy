<?php

namespace App\Support;

/**
 * The venue's name as the event's own site spells it.
 *
 * central.wordcamp.org holds what an organizer once typed into a form, typos
 * included ("Rajasthan Internation Center"), while the event's own pages
 * usually spell it right, and often ("Rajasthan International Centre", 7
 * times on the site). So: find phrases on the site with the same number of
 * words where every word is the same or a near miss (a letter or two off,
 * same first letter), and take the site's spelling only when it appears
 * there more often than central's. Nothing close enough → central's name
 * stands. Only the name (before " — ") is touched, never the address.
 */
class VenueSpelling
{
    /** How far a word may be off and still be "the same word". */
    private const MAX_EDITS = 2;

    public static function fromSite(?string $venueLine, string $siteText): ?string
    {
        if ($venueLine === null || trim($siteText) === '') {
            return $venueLine;
        }

        [$name, $rest] = array_pad(explode(' — ', $venueLine, 2), 2, null);
        $words = preg_split('/\s+/u', trim($name)) ?: [];

        // A one-word name has too little to go on to be sure.
        if (count($words) < 2 || count($words) > 8) {
            return $venueLine;
        }

        $siteWords = preg_split('/[^\p{L}\p{N}\'’&.-]+/u', $siteText, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $counts = [];
        $asCentral = 0;

        for ($i = 0; $i + count($words) <= count($siteWords); $i++) {
            $phrase = array_slice($siteWords, $i, count($words));
            $phrase[count($phrase) - 1] = rtrim(end($phrase), '.');

            if (mb_strtolower(implode(' ', $phrase)) === mb_strtolower($name)) {
                $asCentral++;
            } elseif (self::nearlySame($words, $phrase)) {
                $key = implode(' ', $phrase);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        arsort($counts);
        $best = array_key_first($counts);

        if ($best === null || $counts[$best] <= $asCentral) {
            return $venueLine;
        }

        return $rest === null ? $best : "{$best} — {$rest}";
    }

    /** Same words, up to case and a letter or two, and at least as long a word each time. */
    private static function nearlySame(array $words, array $phrase): bool
    {
        foreach ($words as $index => $word) {
            $a = mb_strtolower($word);
            $b = mb_strtolower($phrase[$index]);

            if ($a === $b) {
                continue;
            }

            // Short words ("of", "the", numbers) must match exactly; a changed
            // word keeps its first letter.
            if (mb_strlen($a) < 4 || mb_substr($a, 0, 1) !== mb_substr($b, 0, 1) || levenshtein($a, $b) > self::MAX_EDITS) {
                return false;
            }
        }

        return true;
    }
}
