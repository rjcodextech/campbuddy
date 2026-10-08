<?php

namespace App\Support;

use App\Models\Event;

/**
 * The QR codes an organizer prints or shows for one event: each opens the
 * event in CampBuddy, tagged with where it was scanned (UTM), so Admin →
 * Analytics "Where people came from" tells a badge from a standee.
 *
 * "id-card / print" is the name the badge QR already had in GA before this
 * kit existed (WordCamp Rajasthan 2026), kept so reports stay comparable.
 */
class QrKit
{
    /** @var array<string, array{0: string, 1: string, 2: string, 3: string}> key => [label, where to use it, utm_source, utm_medium] */
    private const PLACES = [
        'badge' => ['Badge / ID card', 'Printed on every attendee badge. The best channel at WordCamp Rajasthan 2026.', 'id-card', 'print'],
        'standee' => ['Standee / poster', 'At the registration desk, help desk and hallways.', 'standee', 'print'],
        'slide' => ['Slide on screen', 'Between talks and in the opening and closing remarks.', 'slide', 'screen'],
        'social' => ['Social post', 'For the WordCamp\'s own posts, newsletters and emails.', 'social', 'post'],
    ];

    /** @return list<array{key: string, label: string, hint: string, url: string}> */
    public static function codes(Event $event): array
    {
        $base = route('event.home', $event);

        return collect(self::PLACES)->map(fn (array $place, string $key) => [
            'key' => $key,
            'label' => $place[0],
            'hint' => $place[1],
            'url' => $base.'?'.http_build_query([
                'utm_source' => $place[2],
                'utm_medium' => $place[3],
                'utm_campaign' => $event->slug,
            ]),
        ])->values()->all();
    }
}
