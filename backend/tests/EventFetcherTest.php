<?php

declare(strict_types=1);

namespace CampBuddy\Tests;

use CampBuddy\Domain\Event\EventFetcher;
use PHPUnit\Framework\TestCase;

final class EventFetcherTest extends TestCase
{
    public function testBuildEventPatchMapsKnownFields(): void
    {
        $patch = EventFetcher::buildEventPatch([
            'title' => 'WordCamp Rajasthan 2026',
            'event_tagline' => 'Rajasthan\'s WordPress & AI community',
            'event_start_date' => '2026-10-03',
            'event_end_date' => '2026-10-04',
            'event_venue_name' => 'Rajasthan International Centre',
            'event_venue_address' => 'Jhalana Doongri, Jaipur',
            'event_hashtag' => '#WordCampRajasthan',
            'event_home_url' => 'https://rajasthan.wordcamp.org/2026/',
            'event_tickets_url' => 'https://rajasthan.wordcamp.org/2026/tickets/',
            'event_venue_directions_url' => 'https://maps.app.goo.gl/example',
            'event_email' => 'hello@example.com',
            'event_social' => [
                ['label' => 'X', 'url' => 'https://x.com/wprajasthan'],
                ['label' => 'Missing url'],
            ],
            'event_ticket_types' => [
                ['name' => 'General Ticket', 'status' => 'available', 'price' => '₹1,000'],
                ['name' => 'VIP', 'status' => 'available', 'price' => '₹5,000'],
            ],
        ]);

        self::assertSame('WordCamp Rajasthan 2026', $patch['name']);
        self::assertSame('2026-10-03T09:00:00+05:30', $patch['starts']);
        self::assertSame('2026-10-04T09:00:00+05:30', $patch['conference']);
        self::assertSame('mailto:hello@example.com', $patch['contactUrl']);
        self::assertSame([['X', 'https://x.com/wprajasthan']], $patch['socials']);
        self::assertSame('₹1,000 for both days', $patch['ticketPrice']);
    }

    public function testBuildEventPatchSkipsMissingFields(): void
    {
        $patch = EventFetcher::buildEventPatch(['title' => 'Only a title']);

        self::assertSame(['name' => 'Only a title'], $patch);
    }

    public function testSponsorsFromLiveGroupsAndOrdersByTier(): void
    {
        $sponsors = EventFetcher::sponsorsFromLive([
            'event_sponsors' => [
                ['name' => 'AddWeb', 'tier' => 'Hawa Mahal', 'url' => 'https://addwebsolution.com'],
                ['name' => 'Elicus', 'tier' => 'Nahargarh Fort', 'url' => 'https://elicus.com'],
                ['name' => 'Yoast', 'tier' => 'Jal Mahal'],
                ['name' => 'Unknown Tier Co', 'tier' => 'Mystery Tier'],
                ['tier' => 'Nahargarh Fort'], // no name -> skipped
            ],
        ]);

        self::assertNotNull($sponsors);
        self::assertSame('Platinum', $sponsors[0]['tier']);
        self::assertSame('Elicus', $sponsors[0]['items'][0]['name']);
        self::assertSame('Silver', $sponsors[1]['tier']);
        self::assertSame('Bronze', $sponsors[2]['tier']);
        // Unknown tier falls back to its own label, appended after the known tiers.
        self::assertSame('Mystery Tier', $sponsors[3]['tier']);
    }

    public function testSponsorsFromLiveReturnsNullWhenEmpty(): void
    {
        self::assertNull(EventFetcher::sponsorsFromLive([]));
        self::assertNull(EventFetcher::sponsorsFromLive(['event_sponsors' => []]));
    }

    public function testAgendaFromLiveMapsDescriptionToDesc(): void
    {
        $agenda = EventFetcher::agendaFromLive([
            'event_agenda' => [
                ['day' => 'Day 1', 'time' => '09:00 AM', 'title' => 'Registration', 'description' => 'Doors open'],
            ],
        ]);

        self::assertSame([
            ['day' => 'Day 1', 'time' => '09:00 AM', 'title' => 'Registration', 'desc' => 'Doors open'],
        ], $agenda);
    }
}
