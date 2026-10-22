<?php

namespace Tests\Feature;

use App\Jobs\FetchContributorTablesJob;
use App\Jobs\FillMissingPhotosJob;
use App\Models\AttendeeRoster;
use App\Models\ContributorTable;
use App\Models\Event;
use App\Services\ContributorDayParser;
use App\Services\WordCampNormalizer;
use App\Services\WordCampRestClient;
use App\Support\EventData;
use App\Support\RosterRoles;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Photos for everyone on the attendee list (FillMissingPhotosJob), the site's
 * own photos of speakers and organizers (WordCampNormalizer), Contributor Day
 * tables read from the site (FetchContributorTablesJob), and that a finished
 * WordCamp gets none of these fetches.
 */
class PeoplePhotosAndTablesTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://test.wordcamp.org/2026';

    private const REAL = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const NONE = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const PROFILE = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::preventStrayRequests();
        $this->event = $this->makeEvent('wc-test', now()->addDays(5));
    }

    private function makeEvent(string $slug, $start): Event
    {
        return Event::withoutEvents(fn () => Event::create([
            'slug' => $slug, 'display_name' => 'WordCamp '.$slug, 'source_site_url' => self::SITE, 'status' => 'active', 'is_visible' => true,
            'starts_on' => $start->toDateString(), 'ends_on' => $start->toDateString(), 'timezone' => 'Asia/Kolkata',
        ]));
    }

    private function entry(string $name, ?string $hash, array $links = []): AttendeeRoster
    {
        return AttendeeRoster::create([
            'event_id' => $this->event->id, 'name' => $name, 'content_hash' => md5($name),
            'gravatar_url' => $hash ? "https://secure.gravatar.com/avatar/{$hash}?s=192&d=mm&r=g" : null, 'links' => $links, 'is_suppressed' => false,
        ]);
    }

    public function test_speakers_and_organizers_use_the_sites_photo_first_and_keep_their_gravatar_for_matching(): void
    {
        $normalizer = new WordCampNormalizer(new WordCampRestClient(self::SITE));
        $speaker = [
            'id' => 7, 'title' => ['rendered' => 'Asha Rao'], 'content' => ['rendered' => ''], 'meta' => ['_wcpt_user_name' => 'asharao'],
            'avatar_urls' => ['96' => 'https://secure.gravatar.com/avatar/'.self::NONE.'?s=96&d=mm'],
            '_embedded' => ['wp:featuredmedia' => [['source_url' => 'https://test.wordcamp.org/files/asha.jpg', 'media_details' => ['sizes' => ['medium' => ['source_url' => 'https://test.wordcamp.org/files/asha-300x300.jpg']]]]]],
        ];

        $out = $normalizer->normalizeSpeakers([$speaker])[0];
        $this->assertSame('https://test.wordcamp.org/files/asha-300x300.jpg', $out['avatar_url']);
        $this->assertStringContainsString(self::NONE, $out['gravatar_url']);
        $this->assertSame('asharao', $out['wporg_username']);

        // Without a featured image, the Gravatar is the photo, as before.
        unset($speaker['_embedded']);
        $this->assertStringContainsString(self::NONE, $normalizer->normalizeOrganizers([$speaker])[0]['avatar_url']);

        // The attendee-list entry is still found by the Gravatar, not by the site's photo.
        $entry = $this->entry('A. Rao', self::NONE);
        EventData::put($this->event->id, 'speakers', [$out]);
        $this->assertSame(['speaker'], RosterRoles::withAutomatic($this->event)['marked'][$entry->id]['roles']);
    }

    public function test_the_photo_job_finds_site_photos_real_gravatars_and_wordpress_org_pictures(): void
    {
        EventData::put($this->event->id, 'speakers', [['id' => 1, 'name' => 'Asha Rao', 'avatar_url' => 'https://test.wordcamp.org/files/asha.jpg', 'gravatar_url' => null]]);
        $speaker = $this->entry('Asha Rao', self::NONE);
        $withGravatar = $this->entry('Ravi Kumar', self::REAL);
        $withProfile = $this->entry('Neha Singh', self::NONE, [['type' => 'website', 'url' => 'https://profiles.wordpress.org/neha/']]);
        $nothing = $this->entry('No Photo', self::NONE);

        Http::fake([
            'secure.gravatar.com/avatar/'.self::REAL.'*' => Http::response('', 200),
            'secure.gravatar.com/avatar/'.self::PROFILE.'*' => Http::response('', 200),
            'secure.gravatar.com/avatar/*' => Http::response('', 404),
            'profiles.wordpress.org/neha/' => Http::response('<img src="https://secure.gravatar.com/avatar/'.self::PROFILE.'?s=200" class="avatar">'),
        ]);

        (new FillMissingPhotosJob($this->event))->handle();

        $this->assertSame(['https://test.wordcamp.org/files/asha.jpg', 'site'], [$speaker->fresh()->photo_url, $speaker->fresh()->photo_source]);
        $this->assertSame('gravatar', $withGravatar->fresh()->photo_source);
        $this->assertStringContainsString(self::REAL, $withGravatar->fresh()->photo_url);
        $this->assertSame('wporg', $withProfile->fresh()->photo_source);
        $this->assertStringContainsString(self::PROFILE, $withProfile->fresh()->photo_url);
        $this->assertSame('none', $nothing->fresh()->photo_source);
        $this->assertNotNull($nothing->fresh()->photo_checked_at);

        // The roster the apps read shows the best photo; the Gravatar stays for matching.
        $row = collect($this->getJson('/api/v1/events/wc-test/roster')->assertOk()->json('data'))->firstWhere('name', 'Asha Rao');
        $this->assertSame('https://test.wordcamp.org/files/asha.jpg', $row['photo_url']);
        $this->assertStringContainsString(self::NONE, $row['gravatar_url']);

        // A second run the same day looks at nothing again.
        Http::fake(fn () => throw new \RuntimeException('no request expected'));
        (new FillMissingPhotosJob($this->event))->handle();
    }

    public function test_contributor_day_pages_are_read_in_their_usual_shapes(): void
    {
        $parser = new ContributorDayParser;

        $headings = $parser->parse('<h3>Polyglots</h3><p>Table lead: Asha Rao</p><h3>Core Team</h3><ul><li>Ravi Kumar</li><li>Neha Singh</li></ul><h3>Venue</h3><p>Hall B</p>');
        $this->assertSame([['team' => 'polyglots', 'title' => null, 'leads' => ['Asha Rao']], ['team' => 'core', 'title' => null, 'leads' => ['Ravi Kumar', 'Neha Singh']]], $headings);

        $lines = $parser->parse('<ul><li>Documentation – Asha Rao, Ravi Kumar</li><li>Meta: Neha Singh &amp; Amit Shah</li><li>Lunch: 1 pm</li></ul>');
        $this->assertSame('docs', $lines[0]['team']);
        $this->assertSame(['Asha Rao', 'Ravi Kumar'], $lines[0]['leads']);
        $this->assertSame(['other', 'Meta', ['Neha Singh', 'Amit Shah']], [$lines[1]['team'], $lines[1]['title'], $lines[1]['leads']]);
        $this->assertCount(2, $lines, 'a line that names no team is not a table');

        $table = $parser->parse('<table><tr><th>Team</th><th>Leads</th></tr><tr><td>Training</td><td>Asha Rao / Ravi Kumar</td></tr></table>');
        $this->assertSame([['team' => 'training', 'title' => null, 'leads' => ['Asha Rao', 'Ravi Kumar']]], $table);

        $this->assertSame([], $parser->parse('<p>Welcome to WordCamp! Tickets are on sale.</p>'));
    }

    public function test_tables_from_the_site_never_replace_ones_typed_in_by_hand(): void
    {
        ContributorTable::create(['event_id' => $this->event->id, 'team' => 'core', 'leads' => [['name' => 'Manual Lead']], 'sort_order' => 1, 'source' => 'manual']);
        Http::fake([
            self::SITE.'/wp-json/wp/v2/search*' => Http::response([['id' => 5, 'title' => 'Contributor Day', 'subtype' => 'page']]),
            self::SITE.'/wp-json/wp/v2/pages/5*' => Http::response(['content' => ['rendered' => '<ul><li>Core – Site Lead</li><li>Polyglots – Asha Rao</li></ul>']]),
        ]);

        (new FetchContributorTablesJob($this->event))->handle();
        (new FetchContributorTablesJob($this->event))->handle();

        $tables = ContributorTable::where('event_id', $this->event->id)->orderBy('id')->get();
        $this->assertSame(['core:manual', 'polyglots:site'], $tables->map(fn ($t) => $t->team.':'.$t->source)->all(), 'read twice, still one site table; the manual one untouched');
        $this->assertSame([['name' => 'Manual Lead']], $tables[0]->leads);

        // Saving a site table by hand makes it the person's.
        $admin = \App\Models\User::factory()->create();
        $this->actingAs($admin)->put(route('admin.events.tables.update', [$this->event, $tables[1]]), ['team' => 'polyglots', 'leads' => 'Asha Rao'])->assertRedirect();
        $this->assertSame('manual', $tables[1]->fresh()->source);
    }

    public function test_a_finished_wordcamp_gets_none_of_the_daily_fetches(): void
    {
        // Fetched for EventTime::FETCH_DAYS (7) after its last day, never after (owner, 22 Oct 2026).
        $ended = $this->makeEvent('wc-ended', now()->subDays(9));
        $recent = $this->makeEvent('wc-recent', now()->subDays(3));
        app(Kernel::class)->bootstrap();
        Queue::fake();

        foreach (['ingest-sessions-speakers-sponsors', 'ingest-event-info', 'ingest-attendee-roster', 'fill-missing-photos', 'fetch-contributor-tables'] as $name) {
            collect(app(Schedule::class)->events())->first(fn (ScheduledEvent $e) => $e->description === $name)->run(app());
        }

        foreach ([FillMissingPhotosJob::class, FetchContributorTablesJob::class, \App\Jobs\FetchSpeakersSponsorsSessionsJob::class, \App\Jobs\ParseAttendeeRosterJob::class, \App\Jobs\FetchEventInfoJob::class] as $job) {
            Queue::assertPushed($job, fn ($j) => $j->event->is($this->event));
            Queue::assertNotPushed($job, fn ($j) => $j->event->is($ended));
        }
        Queue::assertPushed(\App\Jobs\FetchEventInfoJob::class, fn ($j) => $j->event->is($recent));
    }
}
