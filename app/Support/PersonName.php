<?php

namespace App\Support;

/**
 * A person's name as people see and search it. Names typed on WordCamp sites can carry characters
 * nobody can see — a zero-width space pasted from a chat ("Arif\u{200B}"), a byte-order mark, a
 * soft hyphen, right-to-left overrides — and doubled or non-breaking spaces. Shown, they look
 * fine; searched ("Remove me from the list"), "Arif" never matches. This takes them out.
 *
 * Zero-width joiner and non-joiner (U+200D, U+200C) stay: Hindi, Malayalam and other scripts
 * need them to spell a name, and so do emoji sequences.
 */
class PersonName
{
    private const INVISIBLE = '/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}\x{00AD}\x{200B}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{2066}-\x{2069}\x{FEFF}]/u';

    public static function clean(string $name): string
    {
        $name = preg_replace(self::INVISIBLE, '', $name) ?? $name;

        // Every kind of space (incl. the non-breaking one) and line break → one plain space.
        return trim(preg_replace('/[\s\x{00A0}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]+/u', ' ', $name) ?? $name);
    }

    /** For comparing: clean and case-folded. */
    public static function key(string $name): string
    {
        return mb_strtolower(self::clean($name));
    }

    /** Titles some sites put before a name ("Md. Kamrul Islam" on one list, "Kamrul Islam" on another). */
    private const TITLES = ['md', 'mr', 'mrs', 'ms', 'dr'];

    /**
     * The same person spelled a little differently on two lists: accents off
     * ("Stéphane" = "Stephane"), punctuation off, and a middle initial or a
     * title dropped ("Priyanka P Sida" = "Priyanka Sida", "Md. Mahmudul Hasan"
     * = "Mahmudul Hasan") while two words stay. Only Latin and Greek accents go:
     * the vowel signs of Hindi and other scripts are part of the name.
     */
    public static function loose(string $name): string
    {
        $name = mb_strtolower(self::clean(html_entity_decode($name, ENT_QUOTES | ENT_HTML5)));
        if (class_exists(\Normalizer::class) && ($parts = \Normalizer::normalize($name, \Normalizer::FORM_D)) !== false) {
            $name = \Normalizer::normalize((string) preg_replace('/[\x{0300}-\x{036F}]/u', '', $parts), \Normalizer::FORM_C) ?: $name;
        }

        $words = preg_split('/[^\p{L}\p{M}\p{N}]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kept = array_values(array_filter($words, fn ($w) => mb_strlen($w) > 1 && ! in_array($w, self::TITLES, true)));

        return implode(' ', count($kept) >= 2 ? $kept : $words);
    }

    /**
     * Disambiguated variant keys for cross-referencing the public attendee
     * roster against official WordCamp organizer/speaker/volunteer lists.
     * Callers must only accept a match when it resolves to a single person.
     *
     * @return array{stripped: string, compact: ?string, first_last: ?string, folded: ?string}
     */
    public static function variantKeys(string $name): array
    {
        $decoded = self::clean(html_entity_decode($name, ENT_QUOTES | ENT_HTML5));
        // Strip parenthetical or bracketed nicknames/handles e.g. "(Satya)", "[Yugi5002]".
        $noParens = trim((string) preg_replace('/\s*(?:\([^)]{1,40}\)|\[[^\]]{1,40}\])/u', '', $decoded));
        // Strip trailing digits stuck onto a word e.g. "srinjana123 Sarma" -> "srinjana Sarma".
        $noDigits = trim((string) preg_replace('/(\p{L}{2,})\d+\b/u', '$1', $noParens));

        $stripped = self::loose($noDigits);
        $words = $stripped === '' ? [] : explode(' ', $stripped);

        $compact = null;
        $compactRaw = preg_replace('/\s+/u', '', $stripped) ?? '';
        if (mb_strlen($compactRaw) >= 6) {
            $compact = $compactRaw;
        }

        // First + last word when middle names are dropped ("Aditya Vikram Singh" -> "Aditya Singh").
        $firstLast = count($words) >= 2 ? $words[0].' '.$words[count($words) - 1] : null;

        // Collapse doubled ASCII letters so one-letter typos match ("Priyanshii Vijayvargiya" == "Priyanshi Vijayvargiya").
        $folded = null;
        if (count($words) >= 2 && mb_strlen($stripped) >= 8) {
            $candidate = preg_replace('/([a-z])\1+/u', '$1', $stripped) ?? '';
            if ($candidate !== '') {
                $folded = $candidate;
            }
        }

        return [
            'stripped' => $stripped,
            'compact' => $compact,
            'first_last' => $firstLast,
            'folded' => $folded,
        ];
    }
}
