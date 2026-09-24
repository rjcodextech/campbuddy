<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The homepage WordCamp picker: the next 10 upcoming events, soonest first.
 */
class HomePickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake(); // creating live events queues their ingest — not under test here
    }

    private function event(string $name, ?string $starts, ?string $ends = null, array $overrides = []): Event
    {
        static $n = 0;
        $n++;

        return Event::create($overrides + [
            'slug' => "wc-{$n}",
            'display_name' => $name,
            'source_site_url' => "https://e{$n}.wordcamp.org/2026",
            'starts_on' => $starts,
            'ends_on' => $ends,
            'status' => 'active',
            'is_visible' => true,
        ]);
    }

    /** The event names in the order the picker lists them. */
    private function listed(): array
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('#<h3 class="event-card__title">([^<]*)</h3>#', $html, $m);

        return array_map('html_entity_decode', $m[1]);
    }

    public function test_events_are_listed_soonest_first(): void
    {
        $this->event('Third', now()->addDays(30)->toDateString());
        $this->event('First', now()->addDays(2)->toDateString());
        $this->event('Second', now()->addDays(10)->toDateString());

        $this->assertSame(['First', 'Second', 'Third'], $this->listed());
    }

    public function test_only_the_next_ten_are_shown(): void
    {
        foreach (range(1, 12) as $i) {
            $this->event("Event {$i}", now()->addDays($i)->toDateString());
        }

        $listed = $this->listed();

        $this->assertCount(10, $listed);
        $this->assertSame('Event 1', $listed[0]);
        $this->assertSame('Event 10', $listed[9]);
        $this->assertNotContains('Event 11', $listed, 'the 11th and 12th soonest are cut, not the earliest');
    }

    public function test_an_event_with_no_date_goes_after_the_dated_ones(): void
    {
        $this->event('No date yet', null);
        $this->event('Dated later', now()->addDays(40)->toDateString());
        $this->event('Dated sooner', now()->addDays(3)->toDateString());

        $this->assertSame(['Dated sooner', 'Dated later', 'No date yet'], $this->listed());
    }

    public function test_events_on_the_same_day_keep_a_stable_order(): void
    {
        $day = now()->addDays(5)->toDateString();
        $this->event('Alpha', $day);
        $this->event('Beta', $day);
        $this->event('Gamma', $day);

        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $this->listed());
        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $this->listed());
    }

    public function test_an_event_in_progress_is_still_shown_until_its_last_day_ends(): void
    {
        $this->event('Running now', now()->subDay()->toDateString(), now()->addDay()->toDateString());
        $this->event('Ends today', now()->subDays(2)->toDateString(), now()->toDateString());
        $this->event('Single day today', now()->toDateString(), null);

        $this->assertEqualsCanonicalizing(['Running now', 'Ends today', 'Single day today'], $this->listed());
    }

    public function test_past_events_are_hidden_including_ones_with_no_end_date(): void
    {
        $this->event('Ended yesterday', now()->subDays(3)->toDateString(), now()->subDay()->toDateString());
        // Only a start date, and it has passed: that IS its last known day, so it's over.
        $this->event('Single day, past', now()->subDays(2)->toDateString(), null);
        $this->event('Upcoming', now()->addDays(4)->toDateString());

        $this->assertSame(['Upcoming'], $this->listed());
    }

    public function test_drafts_hidden_and_archived_events_are_never_listed(): void
    {
        $soon = now()->addDays(3)->toDateString();
        $this->event('Live', $soon);
        $this->event('Draft', $soon, null, ['status' => 'draft']);
        $this->event('Approved only', $soon, null, ['status' => 'approved']);
        $this->event('Archived', $soon, null, ['status' => 'archived']);
        $this->event('Switched off', $soon, null, ['is_visible' => false]);

        $this->assertSame(['Live'], $this->listed());
    }

    public function test_the_empty_state_still_renders(): void
    {
        $this->get('/')->assertOk()->assertSee('No WordCamp is live yet');
    }

    public function test_a_single_event_offers_a_direct_open_button_and_several_offer_the_picker(): void
    {
        $this->event('Only one', now()->addDays(3)->toDateString());
        $this->get('/')->assertSee('Open Only one');

        $this->event('Another', now()->addDays(9)->toDateString());
        $this->get('/')->assertSee('Choose your WordCamp ↓');
    }
}
