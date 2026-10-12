<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Support\PastEvents;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Bring it from your last WordCamp": an event's Home lists the WordCamps
 * that ended in the last month, for the phone to check what it holds
 * (bring-data.js); a finished event offers nothing.
 */
class BringDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-12 12:00', 'Asia/Kolkata'));
    }

    private function event(string $slug, string $day, array $attributes = []): Event
    {
        return Event::withoutEvents(fn () => Event::create($attributes + [
            'slug' => $slug, 'display_name' => ucwords(str_replace('-', ' ', $slug)), 'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'active', 'is_visible' => true, 'starts_on' => $day, 'ends_on' => $day, 'timezone' => 'Asia/Kolkata',
        ]));
    }

    public function test_home_lists_the_wordcamps_of_the_last_month_newest_first(): void
    {
        $next = $this->event('wordcamp-next', '2026-11-01');
        $this->event('wordcamp-delhi', '2026-10-05');      // 7 days ago
        $this->event('wordcamp-pune', '2026-09-20');       // 22 days ago
        $this->event('wordcamp-old', '2026-09-01');        // 41 days: too long ago
        $this->event('wordcamp-hidden', '2026-10-01', ['is_visible' => false]);
        $this->event('wordcamp-later', '2026-12-01');      // not over

        $this->assertSame(['wordcamp-delhi', 'wordcamp-pune'], array_column(PastEvents::forHome($next), 'slug'));
        $this->assertSame(7, PastEvents::forHome($next)[0]['daysAgo']);

        $html = $this->get('/event/wordcamp-next')->assertOk()->assertSee('id="bring-data"', false)->getContent();
        preg_match('#<script type="application/json" id="home-data">(.*?)</script>#s', $html, $m);
        $data = json_decode($m[1], true);
        $this->assertSame(['Wordcamp Delhi', 'Wordcamp Pune'], array_column($data['pastEvents'], 'name'));
    }

    public function test_a_finished_wordcamp_offers_nothing_to_bring(): void
    {
        $done = $this->event('wordcamp-done', '2026-10-10');
        $this->event('wordcamp-delhi', '2026-10-05');

        $this->assertSame([], PastEvents::forHome($done));
    }
}
