<?php

namespace Tests\Feature;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\Offer;
use App\Models\OfferLead;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The header every page of one event shares (who the event is + tabs with
 * counts), and the two-column Details page: nothing that was on it is lost.
 */
class AdminEventHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Carbon::setTestNow('2026-09-26 10:00:00');
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function event(array $overrides = []): Event
    {
        return Event::create($overrides + [
            'slug' => 'wc-test',
            'display_name' => 'WordCamp Test',
            'source_site_url' => 'https://test.wordcamp.org/2026',
            'status' => 'active',
            'is_visible' => true,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-02',
        ]);
    }

    /** @return array<string, string> */
    private function pages(Event $event): array
    {
        return [
            'details' => route('admin.events.edit', $event),
            'quests' => route('admin.events.quests.index', $event),
            'offers' => route('admin.events.offers.index', $event),
            'roster' => route('admin.events.roster.index', $event),
            'leads' => route('admin.events.deal-leads.index', $event),
        ];
    }

    public function test_every_event_page_starts_with_who_the_event_is(): void
    {
        $event = $this->event();

        foreach ($this->pages($event) as $name => $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('aria-label="Event sections"', false)
                ->assertSee('WordCamp Test')
                ->assertSee('Active')
                ->assertSee('1 Oct 2026 – 2 Oct 2026')
                ->assertSee('In 5 days')
                ->assertSee('/event/wc-test')
                ->assertSee('View in app');

            $this->assertTrue(true, $name);
        }
    }

    public function test_the_tabs_carry_the_counts_of_what_is_on_each_page(): void
    {
        $event = $this->event();
        $offer = Offer::create(['event_id' => $event->id, 'title' => 'Hosting deal', 'description' => '20% off', 'url' => 'https://host.example', 'icon' => '🏷', 'is_active' => true]);
        Offer::create(['event_id' => $event->id, 'title' => 'Theme deal', 'description' => '10% off', 'url' => 'https://theme.example', 'icon' => '🏷', 'is_active' => true]);
        foreach (['Jamie Rivera', 'Sam Lee', 'Ana Ruiz'] as $i => $name) {
            AttendeeRoster::create(['event_id' => $event->id, 'name' => $name, 'links' => [], 'content_hash' => 'h'.$i, 'is_suppressed' => $i === 2]);
        }
        OfferLead::create(['event_id' => $event->id, 'offer_id' => $offer->id, 'name' => 'Priya', 'email' => 'priya@example.com']);

        $html = $this->get(route('admin.events.edit', $event))->assertOk()->getContent();

        // Read each tab's count out of its link.
        $counts = [];
        preg_match_all('#<a href="([^"]+)"[^>]*>\s*([^<]+?)\s*<span[^>]*>([\d,]+)</span>#', $html, $m, PREG_SET_ORDER);
        foreach ($m as [, , $label, $count]) {
            $counts[html_entity_decode(trim($label))] = (int) str_replace(',', '', $count);
        }

        // Details has no count; the roster count is every entry, suppressed ones too.
        $this->assertSame(
            ['Quests & checklist' => count(Quest::DEFAULT_CHECKLIST), 'Deals' => 2, 'Roster' => 3, 'Deal leads' => 1],
            $counts
        );
    }

    public function test_the_count_only_covers_this_event(): void
    {
        $event = $this->event();
        $other = $this->event(['slug' => 'wc-other', 'display_name' => 'WordCamp Other']);
        Offer::create(['event_id' => $other->id, 'title' => 'Not mine', 'description' => 'x', 'url' => 'https://x.example', 'icon' => '🏷', 'is_active' => true]);
        AttendeeRoster::create(['event_id' => $other->id, 'name' => 'Someone', 'links' => [], 'content_hash' => 'z', 'is_suppressed' => false]);

        $html = $this->get(route('admin.events.offers.index', $event))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('#Deals\s*<span[^>]*>\s*1\s*</span>#', $html);
        $this->assertDoesNotMatchRegularExpression('#Roster\s*<span[^>]*>\s*1\s*</span>#', $html);
    }

    public function test_the_current_tab_is_marked(): void
    {
        $event = $this->event();

        foreach ($this->pages($event) as $key => $url) {
            $html = $this->get($url)->getContent();

            // (The breadcrumbs and the sidebar mark their own current item; look only at the tab strip.)
            preg_match('#<nav aria-label="Event sections".*?</nav>#s', $html, $nav);

            $this->assertSame(1, substr_count($nav[0] ?? '', 'aria-current="page"'), "{$key}: exactly one tab is current");
            $this->assertMatchesRegularExpression('#<a href="'.preg_quote($url, '#').'"[^>]*aria-current="page"#', $nav[0] ?? '', "{$key}: it is this page's tab");
        }
    }

    public function test_view_in_app_only_for_an_event_that_is_live_and_visible(): void
    {
        $draft = $this->event(['slug' => 'wc-draft', 'status' => 'draft']);
        $hidden = $this->event(['slug' => 'wc-hidden', 'is_visible' => false]);

        $this->get(route('admin.events.edit', $draft))->assertOk()->assertDontSee('View in app');
        $this->get(route('admin.events.edit', $hidden))->assertOk()->assertDontSee('View in app')->assertSee('Hidden');
    }

    public function test_an_undated_event_says_so_instead_of_inventing_a_date(): void
    {
        $event = $this->event(['starts_on' => null, 'ends_on' => null]);

        $this->get(route('admin.events.edit', $event))->assertOk()->assertSee('Date not set yet');
    }

    public function test_the_details_page_keeps_every_form_and_action(): void
    {
        $draft = $this->event(['slug' => 'wc-draft', 'status' => 'draft']);

        $html = $this->get(route('admin.events.edit', $draft))->assertOk()->getContent();

        foreach ([
            route('admin.events.update', $draft),
            route('admin.events.update-info', $draft),
            route('admin.events.fetch-info', $draft),
            route('admin.events.refresh', $draft),
            route('admin.events.refresh-branding', $draft),
            route('admin.events.upload-branding', $draft),
            route('admin.events.destroy', $draft),
        ] as $action) {
            $this->assertStringContainsString('action="'.$action.'"', $html, "Missing form: {$action}");
        }

        foreach (['display_name', 'slug', 'short_name', 'source_site_url', 'starts_on', 'ends_on', 'timezone', 'status', 'is_visible', 'venue', 'wifi', 'contributor_day_location', 'code_of_conduct_url', 'registration_info', 'emergency_contact', 'social_event_info', 'nearby_venue_info', 'important_links', 'logo', 'favicon'] as $field) {
            $this->assertMatchesRegularExpression('/name="'.$field.'"/', $html, "Missing field: {$field}");
        }

        $this->assertStringContainsString('Delete draft event', $html);
    }

    public function test_the_recent_fetch_history_lists_what_ran_and_how_it_went(): void
    {
        $event = $this->event();
        $event->fetchLogs()->create(['source' => 'wordcamp', 'job_type' => 'sessions_speakers_sponsors', 'status' => 'error', 'message' => 'Upstream timed out', 'fetched_at' => now()->subHour()]);
        $event->fetchLogs()->create(['source' => 'wordcamp', 'job_type' => 'branding', 'status' => 'ok', 'message' => 'Logo saved', 'fetched_at' => now()->subMinutes(5)]);

        $this->get(route('admin.events.edit', $event))
            ->assertOk()
            ->assertSee('Recent fetch history (2)')
            ->assertSeeInOrder(['Branding', 'Logo saved', 'Schedule', 'Upstream timed out']);
    }
}
