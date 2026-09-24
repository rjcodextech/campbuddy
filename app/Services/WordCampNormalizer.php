<?php

namespace App\Services;

use App\Support\HtmlText;
use App\Support\SafeUrl;

/**
 * Transforms raw wp/v2 REST payloads into the flat shape CampBuddy
 * actually renders from — resolving taxonomy joins and pulling the meta
 * fields that matter (session start time, sponsor website, ...) out from
 * under WordPress's generic post envelope.
 */
class WordCampNormalizer
{
    public function __construct(private readonly WordCampRestClient $client) {}

    /** Longest session description kept — enough to decide, small enough for the page. */
    private const DESCRIPTION_LIMIT = 600;

    /**
     * @param  array<int, array<string, mixed>>  $sessions
     * @param  array<int, string>  $trackNames
     * @param  array<int, string>  $categoryNames
     * @return array<int, array<string, mixed>>
     */
    public function normalizeSessions(array $sessions, array $trackNames, array $categoryNames = [], ?\DateTimeZone $zone = null, array $shownOnSchedule = []): array
    {
        $clock = $zone ? self::sessionClock($sessions, $zone, $shownOnSchedule) : null;

        return array_map(function (array $session) use ($trackNames, $categoryNames, $zone, $clock) {
            $meta = is_array($session['meta'] ?? null) ? $session['meta'] : [];
            $trackIds = $this->ids($session['session_track'] ?? []);
            $categoryIds = $this->ids($session['session_category'] ?? []);
            $startsAt = is_numeric($meta['_wcpt_session_time'] ?? null) ? (int) $meta['_wcpt_session_time'] : 0;
            $duration = is_numeric($meta['_wcpt_session_duration'] ?? null) ? (int) $meta['_wcpt_session_duration'] : null;

            return [
                'id' => $session['id'],
                'title' => $this->decodeTitle($this->rendered($session['title'] ?? null)),
                'link' => $this->httpUrl($session['link'] ?? null),
                'speaker_ids' => $this->ids($meta['_wcpt_speaker_id'] ?? []),
                'track_ids' => $trackIds,
                'track_names' => $this->names($trackIds, $trackNames),
                'category_names' => $this->names($categoryIds, $categoryNames),
                'starts_at' => $startsAt > 0 ? self::sessionInstant($startsAt, $zone, $clock) : null,
                'duration_seconds' => $duration !== null && $duration > 0 ? $duration : null,
                'session_type' => is_string($meta['_wcpt_session_type'] ?? null) ? $meta['_wcpt_session_type'] : null,
                // What the talk is about — the one thing a first-timer needs to
                // choose between two sessions. Plain text, never markup.
                'description' => HtmlText::plain($this->rendered($session['content'] ?? null), self::DESCRIPTION_LIMIT)
                    ?? HtmlText::plain($this->rendered($session['excerpt'] ?? null), self::DESCRIPTION_LIMIT),
                'slides_url' => $this->httpUrl($meta['_wcpt_session_slides'] ?? null),
                'video_url' => $this->httpUrl($meta['_wcpt_session_video'] ?? null),
            ];
        }, $sessions);
    }

    /**
     * @param  array<int, array<string, mixed>>  $speakers
     * @return array<int, array<string, mixed>>
     */
    public function normalizeSpeakers(array $speakers): array
    {
        return array_map(function (array $speaker) {
            $bioHtml = $this->rendered($speaker['content'] ?? null);

            return [
                'id' => $speaker['id'],
                'name' => $this->decodeTitle($this->rendered($speaker['title'] ?? null)),
                'bio_html' => $bioHtml,
                'avatar_url' => $this->avatar($speaker),
                'link' => $this->httpUrl($speaker['link'] ?? null),
                'social_links' => $this->client->extractSocialLinks($bioHtml),
            ];
        }, $speakers);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sponsors
     * @param  array<int, string>  $tierNames
     * @return array<int, array<string, mixed>>
     */
    public function normalizeSponsors(array $sponsors, array $tierNames): array
    {
        return array_map(function (array $sponsor) use ($tierNames) {
            $tierIds = $this->ids($sponsor['sponsor_level'] ?? []);
            $contentHtml = $this->rendered($sponsor['content'] ?? null);

            return [
                'id' => $sponsor['id'],
                'name' => $this->decodeTitle($this->rendered($sponsor['title'] ?? null)),
                'description_html' => $contentHtml,
                'website' => $this->httpUrl($sponsor['meta']['_wcpt_sponsor_website'] ?? null),
                // Shown as an <img> in CampBuddy's pages: web addresses only.
                'logo_url' => SafeUrl::web($this->client->extractFirstImage($contentHtml)),
                'tier_ids' => $tierIds,
                'tier_names' => $this->names($tierIds, $tierNames),
                'link' => $this->httpUrl($sponsor['link'] ?? null),
            ];
        }, $sponsors);
    }

    /**
     * @param  array<int, array<string, mixed>>  $organizers
     * @return array<int, array<string, mixed>>
     */
    public function normalizeOrganizers(array $organizers): array
    {
        return array_map(fn (array $organizer) => [
            'id' => $organizer['id'],
            'name' => $this->decodeTitle($this->rendered($organizer['title'] ?? null)),
            'bio_html' => $this->rendered($organizer['content'] ?? null),
            'avatar_url' => $this->avatar($organizer),
        ], $organizers);
    }

    /** A WordPress `{ rendered: "…" }` field's string, or "" for anything else. */
    private function rendered(mixed $field): string
    {
        return is_array($field) && is_string($field['rendered'] ?? null) ? $field['rendered'] : '';
    }

    /**
     * A list of positive integer ids out of whatever the site sent — a
     * single id, a list, numeric strings — never a crash on odd data.
     *
     * @return array<int, int>
     */
    private function ids(mixed $value): array
    {
        $list = is_array($value) ? $value : [$value];

        return array_values(array_unique(array_map('intval', array_filter(
            $list,
            fn ($id) => is_numeric($id) && (int) $id > 0
        ))));
    }

    /**
     * @param  array<int, int>  $ids
     * @param  array<int, string>  $lookup
     * @return array<int, string>
     */
    private function names(array $ids, array $lookup): array
    {
        return array_values(array_filter(array_map(
            fn ($id) => isset($lookup[$id]) ? $this->decodeTitle((string) $lookup[$id]) : null,
            $ids
        )));
    }

    /** The largest Gravatar the site offers, as a safe web address. */
    private function avatar(array $person): ?string
    {
        $urls = is_array($person['avatar_urls'] ?? null) ? $person['avatar_urls'] : [];

        return SafeUrl::web($urls[96] ?? $urls['96'] ?? $urls[48] ?? $urls['48'] ?? $urls[24] ?? $urls['24'] ?? null);
    }

    /**
     * These URLs come from a third-party site and end up as link targets and
     * the in-app browser's iframe src — so only real web addresses get through.
     * A `javascript:` (or `data:`) value would otherwise run in CampBuddy's
     * own origin. Also absorbs a missing/empty meta key without an
     * "undefined array key" error aborting the whole event's ingestion.
     */
    private function httpUrl(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        return preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }

    /**
     * WordPress's REST API returns `title.rendered` (and any other
     * "rendered" field meant for display) with HTML entities already
     * encoded — e.g. a real apostrophe comes through as `&#8217;`. That's
     * fine if you inject it as HTML, but everywhere this app treats a
     * title as plain text (JS's textContent-based escaping, Blade's
     * {{ }}) it would otherwise double-encode: the literal `&` gets
     * re-escaped to `&amp;`, and the raw entity code shows up on screen
     * instead of the character it represents. Decoding once here, at the
     * source, means every consumer downstream just gets plain text.
     */
    private function decodeTitle(string $title): string
    {
        return html_entity_decode($title, ENT_QUOTES | ENT_HTML5);
    }

    /**
     * WordCamp saves a session's time as the clock time at the venue, packed
     * into a Unix timestamp as if that clock time were UTC ("10:00" in Jaipur
     * is stored as 10:00 UTC). So the real moment is that clock time *in the
     * event's zone*. $clock 'instant' is the other reading (a true UTC
     * timestamp), used only when the site itself shows otherwise.
     */
    public static function sessionInstant(int $timestamp, ?\DateTimeZone $zone, ?string $clock = 'wall'): string
    {
        if ($zone === null) {
            return gmdate('c', $timestamp);
        }

        if ($clock === 'instant') {
            return (new \DateTimeImmutable('@'.$timestamp))->setTimezone($zone)->format('c');
        }

        return (new \DateTimeImmutable(gmdate('Y-m-d H:i:s', $timestamp), $zone))->format('c');
    }

    /**
     * Double-checks which reading the site uses, against the time it shows
     * for the same sessions (its session_date_time field, e.g. "10:00 am"):
     * does that match the timestamp read as clock time ('wall', WordCamp's
     * normal behaviour) or as a true UTC moment shown in the event's zone
     * ('instant')? 'wall' unless the site clearly says otherwise.
     *
     * When the REST field is missing, the times printed on the site's own
     * schedule page (SchedulePageProbe, $shownOnSchedule: title => minutes)
     * cast the votes instead.
     *
     * @param  array<int, array<string, mixed>>  $sessions  raw REST items
     * @param  array<string, array<int, int>>  $shownOnSchedule
     */
    public static function sessionClock(array $sessions, \DateTimeZone $zone, array $shownOnSchedule = []): string
    {
        $votes = ['rest' => ['wall' => 0, 'instant' => 0], 'page' => ['wall' => 0, 'instant' => 0]];

        foreach (array_slice($sessions, 0, 60) as $session) {
            $timestamp = $session['meta']['_wcpt_session_time'] ?? null;

            if (! is_numeric($timestamp) || (int) $timestamp <= 0) {
                continue;
            }

            $asWall = (int) gmdate('G', (int) $timestamp) * 60 + (int) gmdate('i', (int) $timestamp);
            $local = (new \DateTimeImmutable('@'.(int) $timestamp))->setTimezone($zone);
            $asInstant = (int) $local->format('G') * 60 + (int) $local->format('i');

            if ($asWall === $asInstant) {
                continue; // a UTC event: both readings agree
            }

            $shown = $session['session_date_time']['time'] ?? null;
            $minutes = is_string($shown) ? self::minutesOfDay($shown) : null;
            if ($minutes !== null) {
                $votes['rest']['wall'] += (int) ($minutes === $asWall);
                $votes['rest']['instant'] += (int) ($minutes === $asInstant);
            }

            $title = SchedulePageProbe::normalizeTitle((string) ($session['title']['rendered'] ?? ''));
            foreach ($shownOnSchedule[$title] ?? [] as $pageMinutes) {
                $votes['page']['wall'] += (int) ($pageMinutes === $asWall);
                $votes['page']['instant'] += (int) ($pageMinutes === $asInstant);
            }
        }

        // The REST field decides when it spoke; otherwise the schedule page.
        foreach (['rest', 'page'] as $source) {
            if ($votes[$source]['wall'] + $votes[$source]['instant'] > 0) {
                return $votes[$source]['instant'] > $votes[$source]['wall'] ? 'instant' : 'wall';
            }
        }

        return 'wall';
    }

    /** "10:00 am", "10:00 AM", "10.00", "22:00", "10h00" → minutes after midnight. */
    private static function minutesOfDay(string $text): ?int
    {
        if (! preg_match('/(\d{1,2})\s*[:.h]\s*(\d{2})\s*([ap])?\.?\s*m?\.?/i', $text, $m)) {
            return null;
        }

        $hour = (int) $m[1];
        $minute = (int) $m[2];
        $meridiem = strtolower($m[3] ?? '');

        if ($hour > 23 || $minute > 59) {
            return null;
        }
        if ($meridiem === 'p' && $hour < 12) {
            $hour += 12;
        }
        if ($meridiem === 'a' && $hour === 12) {
            $hour = 0;
        }

        return $hour * 60 + $minute;
    }
}
