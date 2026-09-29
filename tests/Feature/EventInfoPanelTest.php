<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Explore → Info: the event's details in groups (At the venue, Help, Links),
 * with Open in Maps, a wifi Copy button and named links.
 */
class EventInfoPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake();
    }

    private function info(array $info, array $event = []): string
    {
        $event = Event::create($event + [
            'slug' => 'jaipur', 'display_name' => 'WordCamp Jaipur 2026', 'source_site_url' => 'https://jaipur.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true, 'country_code' => 'IN', 'info' => $info,
        ]);

        $html = $this->get(route('event.explore', $event))->assertOk()->getContent();
        preg_match('#<div data-explore-panel="info" hidden>(.*?)<div id="data-controls"#s', $html, $panel);

        return $panel[1];
    }

    public function test_everything_filled_in_sits_in_its_group_in_order(): void
    {
        $panel = $this->info([
            'venue' => 'RIC, Jaipur', 'wifi' => 'WCJ / camp2026', 'contributor_day_location' => 'Hall B',
            'social_event_info' => 'Rooftop, 7pm', 'nearby_venue_info' => 'Cafes nearby', 'registration_info' => 'Badges at the gate',
            'emergency_contact' => '+91 98765 43210', 'code_of_conduct_url' => 'https://jaipur.wordcamp.org/2026/code-of-conduct/',
            'important_links' => "https://jaipur.wordcamp.org/2026/\nhttps://jaipur.wordcamp.org/2026/tickets/",
        ]);

        $this->assertMatchesRegularExpression('#At the venue.*Venue.*RIC, Jaipur.*Wifi.*Contributor Day.*Social event.*Nearby.*'
            .'Help.*Emergency contact.*Registration.*Code of Conduct.*Links.*Event website.*Tickets#s', $panel);
    }

    public function test_venue_opens_in_maps_and_wifi_can_be_copied(): void
    {
        $panel = $this->info(['venue' => 'RIC & Hall, Jaipur', 'wifi' => 'WCJ / camp2026']);

        $this->assertStringContainsString('href="https://www.google.com/maps/search/?api=1&amp;query=RIC%20%26%20Hall%2C%20Jaipur"', $panel);
        $this->assertStringContainsString('data-track-link-type="maps"', $panel);
        $this->assertStringContainsString('data-copy-code="WCJ / camp2026"', $panel);
    }

    public function test_a_group_with_nothing_in_it_is_not_shown(): void
    {
        $panel = $this->info(['wifi' => 'WCJ / camp2026']);

        $this->assertStringContainsString('At the venue', $panel);
        $this->assertStringNotContainsString('>Help<', $panel);
        $this->assertStringNotContainsString('>Links<', $panel);
        $this->assertStringNotContainsString('Open in Maps', $panel);
    }

    public function test_no_information_says_so(): void
    {
        $this->assertStringContainsString("Event information hasn't been added yet.", $this->info([]));
    }

    public function test_bare_links_are_named_labelled_ones_keep_their_label_and_text_stays_text(): void
    {
        $panel = $this->info(['important_links' => implode("\n", [
            'https://jaipur.wordcamp.org/2026/',
            'https://jaipur.wordcamp.org/2026/call-for-volunteers/',
            'Travel guide: https://example.org/travel',
            'Bring your own mug',
            'javascript:alert(1)',
        ])]);

        $this->assertMatchesRegularExpression('#Event website</span><span class="useful-link__desc">jaipur\.wordcamp\.org/2026</span>#', $panel);
        $this->assertStringContainsString('>Call for volunteers<', $panel);
        $this->assertMatchesRegularExpression('#href="https://example\.org/travel".*?>Travel guide<#s', $panel);
        $this->assertStringContainsString('>Bring your own mug<', $panel);
        $this->assertStringContainsString('javascript:alert(1)', $panel);
        $this->assertStringNotContainsString('href="javascript:', $panel);
    }

    public function test_emergency_contact_is_a_call_or_mail_link_only_when_it_is_one(): void
    {
        $this->assertStringContainsString('href="tel:+919876543210"', $this->info(['emergency_contact' => '+91 98765 43210']));
        $this->assertStringContainsString('href="mailto:help@example.org"', $this->info(['emergency_contact' => 'help@example.org'], ['slug' => 'a']));

        $panel = $this->info(['emergency_contact' => 'Ask any volunteer'], ['slug' => 'b']);
        $this->assertStringContainsString('Ask any volunteer', $panel);
        $this->assertStringNotContainsString('data-track-link-type="emergency"', $panel);
    }

    public function test_the_head_shows_the_dates(): void
    {
        $this->assertStringContainsString('Sat 3 – Sun 4 Oct 2026', $this->info(['wifi' => 'x'], ['starts_on' => '2026-10-03', 'ends_on' => '2026-10-04']));
        $this->assertStringContainsString('Wed 30 Sep – Thu 1 Oct 2026', $this->info(['wifi' => 'x'], ['slug' => 'c', 'starts_on' => '2026-09-30', 'ends_on' => '2026-10-01']));
        $this->assertStringContainsString('Sat 3 Oct 2026', $this->info(['wifi' => 'x'], ['slug' => 'd', 'starts_on' => '2026-10-03', 'ends_on' => '2026-10-03']));
    }
}
