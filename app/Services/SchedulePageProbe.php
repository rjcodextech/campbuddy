<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * Reads a WordCamp's own schedule page the way a person does: every time
 * shown ("6:30 am IST", "10:00 – 10:45 AM CEST") and which session title
 * follows it. Two uses:
 *
 *   - the time-zone abbreviation the site prints (IST, CEST…), a last-resort
 *     source for the event's zone when its settings don't say;
 *   - a check that CampBuddy's session times are exactly what the site
 *     shows, session by session.
 *
 * Read-only and forgiving: a site without a schedule page, or a layout this
 * doesn't recognise, simply yields nothing to compare.
 */
class SchedulePageProbe
{
    private const TIMEOUT_SECONDS = 15;

    /** How far (in characters of page text) a title may sit after its time. */
    private const NEAR = 700;

    // "6:30 am", "06:30", "6.30 PM", "10:00 - 10:45 am", then an optional zone: "IST", "UTC+5:30".
    private const TIME = '/(?<![\d:.])(\d{1,2})\s*[:.]\s*(\d{2})\s*([aApP]\.?\s?[mM]\.?)?(?:\s*(?:-|to)\s*\d{1,2}\s*[:.]\s*\d{2}\s*(?:[aApP]\.?\s?[mM]\.?)?)?(?:\s*\(?\s*((?:UTC|GMT)\s*[+\-−]\s*\d{1,2}(?:\s*:?\s*\d{2})?|[A-Z]{2,5})\b)?/u';

    public function __construct(private readonly string $siteUrl) {}

    /**
     * @return array{zone_token: ?string, tokens: array, text: string}|null  null when no schedule page could be read
     */
    public function read(): ?array
    {
        $html = $this->fetch();

        return $html === null ? null : self::parse($html);
    }

    /**
     * @return array{zone_token: ?string, tokens: array<int, array{at: int, end: int, minutes: int}>, text: string}
     */
    public static function parse(string $html): array
    {
        $text = self::text($html);

        preg_match_all(self::TIME, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $tokens = [];
        $zones = [];
        foreach ($matches as $m) {
            $hour = (int) $m[1][0];
            $minute = (int) $m[2][0];
            $meridiem = strtolower(preg_replace('/[^ap]/i', '', $m[3][0] ?? ''));
            $zone = trim($m[4][0] ?? '');

            if ($hour > 23 || $minute > 59 || ($meridiem !== '' && ($hour < 1 || $hour > 12))) {
                continue;
            }
            // A bare "10.30" with no am/pm or zone is as likely a price or version.
            if ($meridiem === '' && $zone === '' && ! str_contains($m[0][0], ':')) {
                continue;
            }
            if ($meridiem === 'p' && $hour < 12) {
                $hour += 12;
            }
            if ($meridiem === 'a' && $hour === 12) {
                $hour = 0;
            }

            if ($zone !== '' && preg_match('/^[A-Z]{2,5}$/', $zone) && in_array($zone, ['AM', 'PM', 'TO', 'AT', 'OR'], true)) {
                $zone = '';
            }
            if ($zone !== '') {
                $zones[$zone] = ($zones[$zone] ?? 0) + 1;
            }

            $tokens[] = ['at' => $m[0][1], 'end' => $m[0][1] + strlen($m[0][0]), 'minutes' => $hour * 60 + $minute];
        }

        arsort($zones);

        return [
            'zone_token' => array_key_first($zones),
            'tokens' => $tokens,
            'text' => $text,
        ];
    }

    /**
     * The times shown just before each title — the slot a session sits in.
     *
     * @param  array{tokens: array, text: string}  $page  from parse()
     * @param  array<int, string>  $titles
     * @return array<string, array<int, int>> normalized title => minutes shown
     */
    public static function timesFor(array $page, array $titles): array
    {
        $text = $page['text'] ?? '';
        $found = [];

        foreach ($titles as $title) {
            $needle = self::normalizeTitle($title);
            if (mb_strlen($needle) < 4) {
                continue;
            }

            // Case-insensitive, any run of whitespace between words; byte
            // offsets on the same text the times were found in.
            $pattern = '/'.str_replace(' ', '\\s+', preg_quote($needle, '/')).'/iu';
            if (! preg_match_all($pattern, $text, $hits, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($hits[0] as [, $pos]) {
                $before = array_filter($page['tokens'] ?? [], fn ($t) => $t['end'] <= $pos && $pos - $t['end'] <= self::NEAR);
                if ($before !== []) {
                    $found[$needle][] = end($before)['minutes'];
                }
            }
        }

        return array_map(fn ($list) => array_values(array_unique($list)), $found);
    }

    public static function normalizeTitle(string $title): string
    {
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = str_replace(['’', '‘', '“', '”', '–', '—'], ["'", "'", '"', '"', '-', '-'], $title);

        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($title)) ?? '');
    }

    /** Page text with a line break at every block, so titles and times stay apart. */
    private static function text(string $html): string
    {
        try {
            $crawler = new Crawler($html);
            $crawler->filter('script, style, noscript, svg, header nav, footer')->each(fn (Crawler $n) => $n->getNode(0)?->parentNode?->removeChild($n->getNode(0)));
            $body = $crawler->filter('body')->count() ? $crawler->filter('body')->html() : $crawler->html();
        } catch (Throwable) {
            $body = $html;
        }

        $body = preg_replace('#<(?:br|/p|/div|/li|/h\d|/tr|/td|/th|/section|/article|/time|/span)\b[^>]*>#i', "$0\n", $body) ?? $body;
        $text = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(['’', '‘', '“', '”', '–', '—', "\u{00A0}"], ["'", "'", '"', '"', '-', '-', ' '], $text);

        return preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
    }

    /** The schedule page's HTML: its usual address, or the page's own REST content. */
    private function fetch(): ?string
    {
        $base = rtrim($this->siteUrl, '/');

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->accept('text/html')->get("{$base}/schedule/");
            if ($response->successful() && str_contains((string) $response->header('Content-Type'), 'html')) {
                return $response->body();
            }
        } catch (Throwable) {
            // Try the REST copy below.
        }

        try {
            $pages = Http::timeout(self::TIMEOUT_SECONDS)->acceptJson()
                ->get("{$base}/wp-json/wp/v2/pages", ['slug' => 'schedule', '_fields' => 'content'])
                ->json();
            $html = $pages[0]['content']['rendered'] ?? null;

            return is_string($html) && $html !== '' ? $html : null;
        } catch (Throwable) {
            return null;
        }
    }
}
