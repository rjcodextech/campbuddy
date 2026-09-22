<?php

declare(strict_types=1);

namespace CampBuddy\Domain\Event;

use CampBuddy\Settings;
use CampBuddy\Support\UpstreamClient;

/**
 * Server-side port of app.js's buildEventPatch()/sponsorsFromLive()/
 * agendaFromLive(). Field names and behavior are kept identical on purpose
 * so the /api/v1/event, /sponsors and /agenda responses are drop-in
 * replacements for what the client used to compute itself from the raw
 * upstream shape.
 */
final class EventFetcher
{
    private const SPONSOR_TIER_MAP = [
        'Nahargarh Fort' => ['label' => 'Platinum', 'cls' => 'platinum'],
        'Hawa Mahal' => ['label' => 'Silver', 'cls' => 'silver'],
        'Jal Mahal' => ['label' => 'Bronze', 'cls' => 'bronze'],
    ];

    private const TIER_ORDER = ['Platinum', 'Silver', 'Bronze'];

    public function __construct(
        private readonly UpstreamClient $client,
        private readonly Settings $settings,
    ) {
    }

    /**
     * Fetches the raw upstream event object as-is (same shape as
     * /events?slug=...'s events[0]) so it can be cached and re-served
     * verbatim — the frontend's own buildEventPatch()/sponsorsFromLive()/
     * agendaFromLive() then process it exactly like they process the live
     * upstream response today. Returns null if the upstream call failed or
     * returned something unusable.
     *
     * @return array<string, mixed>|null
     */
    public function fetchRaw(string $slug): ?array
    {
        $url = $this->settings->upstreamApiBase . '/events?slug=' . rawurlencode($slug);
        $json = $this->client->getJson($url);

        $events = $json['events'] ?? null;
        $event = is_array($events) && isset($events[0]) && is_array($events[0]) ? $events[0] : null;

        return $event;
    }

    /**
     * @param array<string, mixed> $ev
     * @return array<string, mixed>
     */
    public static function buildEventPatch(array $ev): array
    {
        $patch = [];

        if (!empty($ev['title'])) {
            $patch['name'] = $ev['title'];
        }
        if (!empty($ev['event_tagline'])) {
            $patch['tagline'] = $ev['event_tagline'];
        }
        if (!empty($ev['event_start_date'])) {
            $patch['starts'] = $ev['event_start_date'] . 'T09:00:00+05:30';
        }
        if (!empty($ev['event_end_date'])) {
            $patch['conference'] = $ev['event_end_date'] . 'T09:00:00+05:30';
        }
        if (!empty($ev['event_venue_name'])) {
            $patch['venue'] = $ev['event_venue_name'];
        }
        if (!empty($ev['event_venue_address'])) {
            $patch['address'] = $ev['event_venue_address'];
        }
        if (!empty($ev['event_hashtag'])) {
            $patch['hashtag'] = $ev['event_hashtag'];
        }
        if (!empty($ev['event_home_url'])) {
            $patch['officialUrl'] = $ev['event_home_url'];
        }
        if (!empty($ev['event_tickets_url'])) {
            $patch['ticketUrl'] = $ev['event_tickets_url'];
        }
        if (!empty($ev['event_venue_directions_url'])) {
            $patch['directionsUrl'] = $ev['event_venue_directions_url'];
        }
        if (!empty($ev['event_email'])) {
            $patch['contactUrl'] = 'mailto:' . $ev['event_email'];
        }

        if (!empty($ev['event_social']) && is_array($ev['event_social'])) {
            $socials = [];
            foreach ($ev['event_social'] as $s) {
                if (is_array($s) && !empty($s['label']) && !empty($s['url'])) {
                    $socials[] = [$s['label'], $s['url']];
                }
            }
            if ($socials !== []) {
                $patch['socials'] = $socials;
            }
        }

        if (!empty($ev['event_ticket_types']) && is_array($ev['event_ticket_types'])) {
            foreach ($ev['event_ticket_types'] as $t) {
                if (
                    is_array($t)
                    && isset($t['name']) && preg_match('/^general ticket$/i', (string) $t['name'])
                    && ($t['status'] ?? null) === 'available'
                    && !empty($t['price'])
                ) {
                    $patch['ticketPrice'] = $t['price'] . ' for both days';
                    break;
                }
            }
        }

        return $patch;
    }

    /**
     * @param array<string, mixed> $ev
     * @return array<int, mixed>|null
     */
    public static function sponsorsFromLive(array $ev): ?array
    {
        $sponsors = $ev['event_sponsors'] ?? null;
        if (!is_array($sponsors) || $sponsors === []) {
            return null;
        }

        $groups = [];
        foreach ($sponsors as $s) {
            if (!is_array($s) || empty($s['name'])) {
                continue;
            }
            $tierKey = $s['tier'] ?? null;
            $tier = self::SPONSOR_TIER_MAP[$tierKey] ?? ['label' => $tierKey ?: 'Sponsors', 'cls' => 'bronze'];
            $label = $tier['label'];

            if (!isset($groups[$label])) {
                $groups[$label] = ['tier' => $label, 'cls' => $tier['cls'], 'items' => []];
            }
            $groups[$label]['items'][] = [
                'name' => $s['name'],
                'url' => $s['url'] ?? '',
                'logo' => $s['logo'] ?? null,
            ];
        }

        if ($groups === []) {
            return null;
        }

        $out = [];
        foreach (self::TIER_ORDER as $t) {
            if (isset($groups[$t])) {
                $out[] = $groups[$t];
            }
        }
        foreach ($groups as $label => $group) {
            if (!in_array($label, self::TIER_ORDER, true)) {
                $out[] = $group;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $ev
     * @return array<int, mixed>|null
     */
    public static function agendaFromLive(array $ev): ?array
    {
        $agenda = $ev['event_agenda'] ?? null;
        if (!is_array($agenda) || $agenda === []) {
            return null;
        }

        $out = [];
        foreach ($agenda as $a) {
            if (!is_array($a)) {
                continue;
            }
            $out[] = [
                'day' => $a['day'] ?? '',
                'time' => $a['time'] ?? '',
                'title' => $a['title'] ?? '',
                'desc' => $a['description'] ?? '',
            ];
        }

        return $out;
    }
}
