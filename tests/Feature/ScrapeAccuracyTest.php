<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Support\EventData;
use App\Support\SystemHealth;
use App\Support\VenueSpelling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Lessons from the first events, built in so the next one doesn't need a
 * hand fix: a venue name misspelled on central.wordcamp.org (Rajasthan),
 * an admin edit gone stale (the old "Jaipur Marriott"), and dates that
 * don't match the schedule.
 */
class ScrapeAccuracyTest extends TestCase
{
    use RefreshDatabase;

    // ---- Venue spelling ---------------------------------------------------

    public function test_the_venue_name_follows_the_events_own_site_when_central_misspells_it(): void
    {
        $site = 'Venue: Rajasthan International Centre, Jaipur. Tickets for Rajasthan International Centre. '
            .'Getting to the Rajasthan International Centre. Old post: Rajasthan Internation Center.';

        $this->assertSame(
            'Rajasthan International Centre — Sansthan Path, JLN Marg, Jaipur - 302017',
            VenueSpelling::fromSite('Rajasthan Internation Center — Sansthan Path, JLN Marg, Jaipur - 302017', $site)
        );
    }

    public function test_the_venue_name_stays_when_the_site_agrees_or_has_nothing_close(): void
    {
        $line = 'UBC Robson Square — 800 Robson St, Vancouver';

        // The site spells it the same way.
        $this->assertSame($line, VenueSpelling::fromSite($line, 'Join us at UBC Robson Square in Vancouver.'));
        // Nothing on the site: central stands.
        $this->assertSame($line, VenueSpelling::fromSite($line, ''));
        $this->assertSame($line, VenueSpelling::fromSite($line, 'Welcome to WordCamp Canada!'));
        // A near miss seen less often than central's own spelling doesn't win.
        $this->assertSame($line, VenueSpelling::fromSite($line, 'UBC Robson Square. UBC Robson Square. UBC Robsen Square.'));
        // A different place that only shares short words is never taken.
        $this->assertSame('Hall of Fame — Pune', VenueSpelling::fromSite('Hall of Fame — Pune', 'Mall of Game and more. Mall of Game.'));
        // Case alone isn't a spelling fix.
        $this->assertSame($line, VenueSpelling::fromSite($line, 'UBC ROBSON SQUARE, UBC ROBSON SQUARE'));
        // A one-word name has too little to go on.
        $this->assertSame('Madurodam', VenueSpelling::fromSite('Madurodam', 'Madurodamm Madurodamm'));
        $this->assertNull(VenueSpelling::fromSite(null, 'anything'));
    }

    // ---- A stale edit is visible ------------------------------------------

    public function test_the_admin_form_says_what_the_site_says_under_an_edited_field(): void
    {
        Queue::fake();
        $this->withoutVite();
        $event = Event::create([
            'slug' => 'wc-raj', 'display_name' => 'WordCamp Raj', 'source_site_url' => 'https://raj.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true,
            'info' => ['venue' => 'Jaipur Marriott', 'wifi' => 'Net'],
            'info_fetched' => ['venue' => 'Rajasthan International Centre — Jaipur', 'wifi' => 'Net'],
            'info_fetched_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())->get(route('admin.events.edit', $event))->assertOk()
            ->assertSee('The WordCamp site now says: “Rajasthan International Centre — Jaipur”', false)
            ->assertSee('From the WordCamp site.');
    }

    // ---- Dates vs the schedule --------------------------------------------

    private function liveEvent(array $attributes = []): Event
    {
        return Event::create($attributes + [
            'slug' => 'wc-dates', 'display_name' => 'WordCamp Dates', 'source_site_url' => 'https://dates.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true, 'timezone' => 'Asia/Kolkata',
            'starts_on' => '2026-11-21', 'ends_on' => '2026-11-22',
        ]);
    }

    private function sessions(Event $event, array $starts): void
    {
        EventData::put($event->id, 'sessions', array_map(
            fn (string $start, int $i) => ['id' => $i + 1, 'title' => "Talk {$i}", 'starts_at' => $start],
            $starts, array_keys($starts)
        ));
    }

    private function dateWarnings(): array
    {
        return array_values(array_filter(SystemHealth::problems(), fn ($p) => str_contains($p['title'], "schedule doesn't match")));
    }

    public function test_a_schedule_inside_the_dates_or_with_a_contributor_day_before_them_is_fine(): void
    {
        $this->sessions($this->liveEvent(), ['2026-11-20T04:00:00+00:00', '2026-11-21T04:30:00+00:00', '2026-11-22T11:00:00+00:00']);

        $this->assertSame([], $this->dateWarnings());
    }

    public function test_a_schedule_that_runs_past_the_dates_is_flagged_with_what_to_do(): void
    {
        // Central says 21–22 Nov, the schedule is a week later.
        $this->sessions($this->liveEvent(), ['2026-11-28T04:00:00+00:00', '2026-11-29T04:00:00+00:00']);

        $warnings = $this->dateWarnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('The app says 21 Nov – 22 Nov, but the sessions run 28 Nov – 29 Nov', $warnings[0]['detail']);
        $this->assertStringContainsString('follow central.wordcamp.org', $warnings[0]['detail']);
    }

    public function test_session_days_are_read_at_the_venue(): void
    {
        // 22 Nov 20:00 UTC is 23 Nov 01:30 in India: one day past the end, still fine.
        $this->sessions($this->liveEvent(), ['2026-11-22T20:00:00+00:00']);
        $this->assertSame([], $this->dateWarnings());
    }
}
