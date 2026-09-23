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
                'title' => $session['title']['rendered'] ?? '',
                'link' => $session['link'] ?? null,
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
                'slides_url' => $session['meta']['_wcpt_session_slides'] ?: null,
                'video_url' => $session['meta']['_wcpt_session_video'] ?: null,
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
                'name' => $speaker['title']['rendered'] ?? '',
                'bio_html' => $bioHtml,
                'avatar_url' => $speaker['avatar_urls'][96] ?? $speaker['avatar_urls'][24] ?? null,
                'link' => $speaker['link'] ?? null,
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
                'name' => $sponsor['title']['rendered'] ?? '',
                'description_html' => $contentHtml,
                'website' => $sponsor['meta']['_wcpt_sponsor_website'] ?: null,
                'logo_url' => $this->client->extractFirstImage($contentHtml),
                'tier_ids' => $tierIds,
                'tier_names' => array_values(array_filter(array_map(
                    fn ($id) => $tierNames[$id] ?? null,
                    $tierIds
                ))),
                'link' => $sponsor['link'] ?? null,
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
            'name' => $organizer['title']['rendered'] ?? '',
            'bio_html' => $organizer['content']['rendered'] ?? '',
            'avatar_url' => $organizer['avatar_urls'][96] ?? null,
        ], $organizers);
    }
}
