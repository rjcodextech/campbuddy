<?php

namespace Tests\Feature;

use App\Jobs\FetchEventInfoJob;
use App\Models\Event;
use App\Models\User;
use App\Services\EventInfoFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Event Information auto-fetch: what it reads, that it leaves blanks blank,
 * and that it never overwrites something an admin typed. HTTP is faked with
 * responses shaped like the real central.wordcamp.org / wp-json ones.
 */
class EventInfoFetchTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://test.wordcamp.org/2026';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function event(array $overrides = []): Event
    {
        return Event::create($overrides + [
            'slug' => 'wc-test',
            'display_name' => 'WordCamp Test 2026',
            'source_site_url' => self::SITE,
            'status' => 'active',
            'is_visible' => true,
        ]);
    }

    /** A central.wordcamp.org record, plus last year's for the same city (which must be ignored). */
    private function centralRecords(): array
    {
        return [
            [
                'URL' => 'https://test.wordcamp.org/2024/',
                'Venue Name' => 'Old Venue',
                'Physical Address' => '9 Old Road',
                'Location' => 'Testville',
            ],
            [
                'URL' => 'https://test.wordcamp.org/2026/',
                'Venue Name' => 'Grand Hall',
                'Physical Address' => "1 Main St\r\nGrand Hall\r\n\r\nTestville 12345",
                'Location' => 'Testville, Testland',
            ],
        ];
    }

    /** The site's page list: id, slug, link, title. */
    private function pageIndex(): array
    {
        $page = fn (int $id, string $slug, string $title) => [
            'id' => $id, 'slug' => $slug, 'link' => self::SITE."/{$slug}/", 'title' => ['rendered' => $title],
        ];

        return [
            $page(1, 'tickets', 'Tickets'),
            $page(2, 'schedule', 'Schedule'),
            $page(3, 'contact', 'Contact'),
            $page(4, 'code-of-conduct', 'Code of Conduct'),
            $page(5, 'contributor-day', 'Contributor Day'),
            $page(6, 'faq', 'FAQ'),
            $page(8, 'ticket-countdown', 'Ticket countdown'), // must lose to /tickets/
            $page(9, 'sponsors', 'Sponsors'),
        ];
    }

    /** Rendered content by page id. */
    private function pageContent(): array
    {
        return [
            1 => '<p>Tickets are live</p>'
                .'<p>Tickets for WordCamp Test cover all sessions, lunch and coffee. Buy yours early.</p>'
                .'<p>Please view this page in a browser to purchase or manage tickets.</p>'
                .'<p>Do I need a separate ticket for Contributor Day? No, it is included with every attendee ticket.</p>',
            3 => '<p>Questions? Write to <a href="mailto:hello@test.example">hello@test.example</a> and we will reply.</p>',
            4 => '<p>Report incidents to <a href="mailto:report@wordcamp.org">report@wordcamp.org</a> or, if you prefer, to our '
                .'volunteer coordinator at private.person@gmail.com who has kindly offered to help with any reports.</p>',
            5 => '<p>Contributor Day is one of the best parts of WordCamp.</p>'
                .'<p>Contributor Day will be held at the Community Hub, 5 Side Street. Bring a laptop.</p>',
            6 => '<p>Is there wifi? Yes: the venue Wi-Fi network is TestCamp and the password is wordpress2026.</p>',
        ];
    }

    /** @var array{central: array, index: array, content: array}|null */
    private ?array $site = null;

    /**
     * Fakes central.wordcamp.org and the site's REST pages. Calling it again in
     * the same test changes what they answer (Http::fake() stubs stack, so the
     * fake itself is only registered once).
     */
    private function fakeSite(?array $central = null, ?array $index = null, ?array $content = null): void
    {
        $registered = $this->site !== null;

        $this->site = [
            'central' => $central ?? $this->centralRecords(),
            'index' => $index ?? $this->pageIndex(),
            'content' => $content ?? $this->pageContent(),
        ];

        if ($registered) {
            return;
        }

        Http::fake(function (Request $request) {
            ['central' => $central, 'index' => $index, 'content' => $content] = $this->site;
            $url = $request->url();
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            if (str_contains($url, 'central.wordcamp.org')) {
                return Http::response($central);
            }

            if (str_contains($url, '/wp-json/wp/v2/pages')) {
                if (isset($query['include'])) {
                    $ids = array_map('intval', explode(',', $query['include']));

                    return Http::response(collect($index)
                        ->filter(fn ($p) => in_array($p['id'], $ids, true) && isset($content[$p['id']]))
                        ->map(fn ($p) => ['id' => $p['id'], 'link' => $p['link'], 'content' => ['rendered' => $content[$p['id']]]])
                        ->values()->all());
                }

                return Http::response($index, 200, ['X-WP-TotalPages' => 1]);
            }

            return Http::response('', 404);
        });
    }

    // ---- The fetcher ------------------------------------------------------

    public function test_it_reads_every_field_from_central_and_the_site_pages(): void
    {
        $this->fakeSite();

        $fetcher = new EventInfoFetcher(self::SITE, 'WordCamp Test 2026');
        $info = $fetcher->fetch();

        $this->assertTrue($fetcher->reachable);

        // Venue: this year's central record (matched on URL, not last year's), one line,
        // with the name repeated in the address collapsed.
        $this->assertSame('Grand Hall — 1 Main St, Testville 12345', $info['venue']);

        // Links: the site + the pages an attendee needs — /tickets/, not /ticket-countdown/.
        $this->assertSame(
            implode("\n", [self::SITE.'/', self::SITE.'/tickets/', self::SITE.'/schedule/', self::SITE.'/contact/', self::SITE.'/faq/']),
            $info['important_links']
        );

        $this->assertSame(self::SITE.'/code-of-conduct/', $info['code_of_conduct_url']);
        $this->assertSame('Tickets for WordCamp Test cover all sessions, lunch and coffee. Buy yours early.', $info['registration_info']);
        $this->assertSame('Contributor Day will be held at the Community Hub, 5 Side Street.', $info['contributor_day_location']);
        $this->assertSame('Yes: the venue Wi-Fi network is TestCamp and the password is wordpress2026.', $info['wifi']);

        // Contact page email — not the shared report@ address and not a personal one from CoC prose.
        $this->assertSame('hello@test.example', $info['emergency_contact']);

        // Nothing on the site about these → blank, not invented.
        $this->assertNull($info['social_event_info']);
        $this->assertNull($info['nearby_venue_info']);
        $this->assertSame(EventInfoFetcher::FIELDS, array_keys($info));
    }

    public function test_fields_the_site_does_not_state_stay_blank(): void
    {
        // A site with pages but nothing useful on them, and no central record.
        $this->fakeSite(central: [], index: [
            ['id' => 1, 'slug' => 'home', 'link' => self::SITE.'/', 'title' => ['rendered' => 'Home']],
            ['id' => 2, 'slug' => 'sponsors', 'link' => self::SITE.'/sponsors/', 'title' => ['rendered' => 'Sponsors']],
        ], content: []);

        $fetcher = new EventInfoFetcher(self::SITE, 'WordCamp Test 2026');
        $info = $fetcher->fetch();

        $this->assertTrue($fetcher->reachable);
        $this->assertSame([], array_filter($info, fn ($v) => $v !== null));
    }

    public function test_a_wifi_mention_without_credentials_is_not_wifi_info(): void
    {
        $this->fakeSite(content: [6 => '<p>Free wifi will be available throughout the venue for all attendees.</p>']);

        $this->assertNull((new EventInfoFetcher(self::SITE, 'X'))->fetch()['wifi']);
    }

    public function test_an_emergency_phone_number_is_preferred_when_the_page_labels_one(): void
    {
        $this->fakeSite(content: [6 => '<p>In an emergency or for first aid call the venue desk on +91 141 555 0100 at any time.</p>'] + $this->pageContent());

        $this->assertSame('+91 141 555 0100', (new EventInfoFetcher(self::SITE, 'X'))->fetch()['emergency_contact']);
    }

    public function test_a_long_specific_page_is_not_mistaken_for_the_schedule(): void
    {
        // Seen on a real site: /english-information/evening-programme-november-12th/ is one evening's
        // programme, not the schedule — and central stores some names HTML-escaped.
        $index = $this->pageIndex();
        $index[] = ['id' => 20, 'slug' => 'evening-programme-november-12th', 'link' => self::SITE.'/evening-programme-november-12th/', 'title' => ['rendered' => 'Evening programme November 12th']];
        $index = array_values(array_filter($index, fn ($p) => $p['slug'] !== 'schedule'));

        $central = $this->centralRecords();
        $central[1]['Venue Name'] = 'St. Joseph&#039;s Boys&#039; High School';
        $central[1]['Physical Address'] = '27 Museum Rd, Bengaluru';

        $this->fakeSite(central: $central, index: $index);

        $info = (new EventInfoFetcher(self::SITE, 'X'))->fetch();

        $this->assertStringNotContainsString('evening-programme', $info['important_links']);
        $this->assertSame("St. Joseph's Boys' High School — 27 Museum Rd, Bengaluru", $info['venue']);
    }

    public function test_it_falls_back_to_scraping_when_the_rest_api_is_unavailable(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();

            return match (true) {
                str_contains($url, 'central.wordcamp.org') => Http::response([]),
                str_contains($url, '/wp-json/') => Http::response('', 404),
                $url === self::SITE.'/' => Http::response(
                    '<html><body><nav><a href="/2026/tickets/">Tickets</a> <a href="/2026/code-of-conduct/">Code of Conduct</a>'
                    .' <a href="https://elsewhere.example/tickets/">Tickets</a> <a href="/2024/tickets/">Old</a></nav></body></html>'
                ),
                $url === self::SITE.'/tickets/' => Http::response(
                    '<html><body><header>Site header</header><div class="entry-content">'
                    .'<p>Tickets for WordCamp Test are on sale until the day before the event.</p></div><footer>Footer</footer></body></html>'
                ),
                $url === self::SITE.'/code-of-conduct/' => Http::response('<html><body><main><p>Be excellent to each other, always.</p></main></body></html>'),
                default => Http::response('', 404),
            };
        });

        $fetcher = new EventInfoFetcher(self::SITE, 'X');
        $info = $fetcher->fetch();

        $this->assertStringContainsString('scrape', $fetcher->sources);
        $this->assertSame(self::SITE.'/code-of-conduct/', $info['code_of_conduct_url']);
        $this->assertSame('Tickets for WordCamp Test are on sale until the day before the event.', $info['registration_info']);
        // Links that leave the event's own /2026/ site are not followed.
        $this->assertStringNotContainsString('elsewhere.example', (string) $info['important_links']);
        $this->assertStringNotContainsString('/2024/', (string) $info['important_links']);
    }

    // ---- The job ----------------------------------------------------------

    public function test_the_job_stores_what_it_found_and_a_snapshot_of_it(): void
    {
        $this->fakeSite();
        $event = $this->event(['info' => ['venue' => 'Testville, Testland'], 'info_fetched' => ['venue' => 'Testville, Testland']]);

        FetchEventInfoJob::dispatchSync($event);
        $event->refresh();

        $this->assertSame('Grand Hall — 1 Main St, Testville 12345', $event->info['venue']);
        $this->assertSame($event->info, $event->info_fetched, 'Everything on file was auto-filled, so the snapshot equals it.');
        $this->assertNotNull($event->info_fetched_at);
        $this->assertArrayNotHasKey('nearby_venue_info', $event->info, 'Fields with no data are omitted, not stored blank.');

        $log = $event->fetchLogs()->where('job_type', 'event_info')->first();
        $this->assertSame('ok', $log->status);
        $this->assertStringContainsString('7 of 9 fields found', $log->message);
    }

    public function test_a_value_an_admin_typed_is_kept_and_reported(): void
    {
        $this->fakeSite();
        $event = $this->event([
            'info' => ['venue' => 'Jaipur Marriott', 'wifi' => 'OurNet / hunter2'],
            // No snapshot: everything already on file was entered by hand.
        ]);

        FetchEventInfoJob::dispatchSync($event);
        $event->refresh();

        $this->assertSame('Jaipur Marriott', $event->info['venue'], 'The admin\'s venue beats the fetched one.');
        $this->assertSame('OurNet / hunter2', $event->info['wifi']);
        $this->assertSame(self::SITE.'/code-of-conduct/', $event->info['code_of_conduct_url'], 'Fields the admin left blank are filled.');

        $message = $event->fetchLogs()->where('job_type', 'event_info')->value('message');
        $this->assertStringContainsString('kept your edits to: venue, wifi', $message);
    }

    public function test_an_auto_filled_value_is_refreshed_and_then_blanked_when_the_source_drops_it(): void
    {
        $event = $this->event();

        $this->fakeSite();
        FetchEventInfoJob::dispatchSync($event);
        $this->assertSame('hello@test.example', $event->fresh()->info['emergency_contact']);

        // The contact page loses its email; central still answers, so the site is "reachable".
        $this->fakeSite(content: array_diff_key($this->pageContent(), [3 => 1]));
        FetchEventInfoJob::dispatchSync($event->fresh());

        $event->refresh();
        $this->assertArrayNotHasKey('emergency_contact', $event->info);
        $this->assertSame('Grand Hall — 1 Main St, Testville 12345', $event->info['venue'], 'Still-present fields survive the refresh.');
    }

    public function test_an_outage_changes_nothing(): void
    {
        $event = $this->event(['info' => ['venue' => 'Somewhere real', 'wifi' => 'net / pw'], 'info_fetched' => ['venue' => 'Somewhere real']]);

        Http::fake(fn () => Http::response('down', 500));

        FetchEventInfoJob::dispatchSync($event);
        $event->refresh();

        $this->assertSame(['venue' => 'Somewhere real', 'wifi' => 'net / pw'], $event->info);
        $this->assertNull($event->info_fetched_at);

        $log = $event->fetchLogs()->where('job_type', 'event_info')->first();
        $this->assertSame('error', $log->status);
        $this->assertStringContainsString('nothing changed', $log->message);
    }

    // ---- The admin screen -------------------------------------------------

    public function test_the_admin_can_fetch_now_and_sees_where_each_value_came_from(): void
    {
        $this->fakeSite();
        $event = $this->event(['info' => ['wifi' => 'OurNet / hunter2']]);
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.events.fetch-info', $event))
            ->assertRedirect(route('admin.events.edit', $event))
            ->assertSessionHas('status', fn ($s) => str_starts_with($s, 'Event information updated'));

        $html = $this->actingAs($admin)->get(route('admin.events.edit', $event))->assertOk()->getContent();

        $this->assertStringContainsString('Fetch latest', $html);
        $this->assertStringContainsString('Auto-filled from the WordCamp site', $html);
        $this->assertStringContainsString('Edited by you', $html);   // the wifi they typed
        $this->assertStringContainsString('Grand Hall', $html);
        $this->assertStringContainsString('Nothing found on the WordCamp site — left blank.', $html);
    }

    public function test_saving_the_form_untouched_does_not_turn_auto_values_into_edits(): void
    {
        $this->fakeSite();
        $event = $this->event();
        $admin = User::factory()->create();

        FetchEventInfoJob::dispatchSync($event);
        $event->refresh();

        // Browsers submit textareas with CRLF line endings.
        $payload = array_map(fn ($v) => str_replace("\n", "\r\n", $v), $event->info);

        $this->actingAs($admin)->put(route('admin.events.update-info', $event), $payload)->assertRedirect();
        $event->refresh();

        $this->assertSame($event->info, $event->info_fetched, 'Stored values still equal the fetched snapshot, so they stay "auto".');
    }

    public function test_only_signed_in_admins_can_trigger_a_fetch(): void
    {
        $this->post(route('admin.events.fetch-info', $this->event()))->assertRedirect(route('login'));
    }
}
