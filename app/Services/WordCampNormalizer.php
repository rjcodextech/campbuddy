<?php

namespace App\Services;

/**
 * Transforms raw wp/v2 REST payloads into the flat shape CampBuddy
 * actually renders from — resolving taxonomy joins and pulling the meta
 * fields that matter (session start time, sponsor website, ...) out from
 * under WordPress's generic post envelope.
 */
class WordCampNormalizer
{
    public function __construct(private readonly WordCampRestClient $client) {}

    /**
     * @param  array<int, array<string, mixed>>  $sessions
     * @param  array<int, string>  $trackNames
     * @return array<int, array<string, mixed>>
     */
    public function normalizeSessions(array $sessions, array $trackNames): array
    {
        return array_map(function (array $session) use ($trackNames) {
            $trackIds = $session['session_track'] ?? [];

            return [
                'id' => $session['id'],
                'title' => $this->decodeTitle($session['title']['rendered'] ?? ''),
                'link' => $this->httpUrl($session['link'] ?? null),
                'speaker_ids' => $session['meta']['_wcpt_speaker_id'] ?? [],
                'track_ids' => $trackIds,
                'track_names' => array_values(array_filter(array_map(
                    fn ($id) => $trackNames[$id] ?? null,
                    $trackIds
                ))),
                'starts_at' => isset($session['meta']['_wcpt_session_time']) && $session['meta']['_wcpt_session_time'] > 0
                    ? gmdate('c', (int) $session['meta']['_wcpt_session_time'])
                    : null,
                'duration_seconds' => $session['meta']['_wcpt_session_duration'] ?? null,
                'session_type' => $session['meta']['_wcpt_session_type'] ?? null,
                'slides_url' => $this->httpUrl($session['meta']['_wcpt_session_slides'] ?? null),
                'video_url' => $this->httpUrl($session['meta']['_wcpt_session_video'] ?? null),
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
            $bioHtml = $speaker['content']['rendered'] ?? '';

            return [
                'id' => $speaker['id'],
                'name' => $this->decodeTitle($speaker['title']['rendered'] ?? ''),
                'bio_html' => $bioHtml,
                'avatar_url' => $speaker['avatar_urls'][96] ?? $speaker['avatar_urls'][24] ?? null,
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
            $tierIds = $sponsor['sponsor_level'] ?? [];
            $contentHtml = $sponsor['content']['rendered'] ?? '';

            return [
                'id' => $sponsor['id'],
                'name' => $this->decodeTitle($sponsor['title']['rendered'] ?? ''),
                'description_html' => $contentHtml,
                'website' => $this->httpUrl($sponsor['meta']['_wcpt_sponsor_website'] ?? null),
                'logo_url' => $this->client->extractFirstImage($contentHtml),
                'tier_ids' => $tierIds,
                'tier_names' => array_values(array_filter(array_map(
                    fn ($id) => $tierNames[$id] ?? null,
                    $tierIds
                ))),
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
            'name' => $this->decodeTitle($organizer['title']['rendered'] ?? ''),
            'bio_html' => $organizer['content']['rendered'] ?? '',
            'avatar_url' => $organizer['avatar_urls'][96] ?? null,
        ], $organizers);
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
}
