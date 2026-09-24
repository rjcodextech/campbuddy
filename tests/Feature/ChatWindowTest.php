<?php

namespace Tests\Feature;

use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Models\Event;
use App\Services\WordCampNormalizer;
use App\Support\ChatWindow;
use App\Support\EventData;
use App\Support\EventTime;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The discovery chat is open only during the event: each event day from an
 * hour before its first session to an hour after its last, in the event's
 * own time zone — and three messages per person per day. Getting a time
 * zone or a boundary wrong here would open or close the chat at the wrong
 * time, so the edges are tested exactly.
 */
class ChatWindowTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $attributes = [], array $sessions = []): Event
    {
        $event = Event::withoutEvents(fn () => Event::create($attributes + [
            'slug' => 'wc-'.uniqid(),
            'display_name' => 'WordCamp Test 2026',
            'source_site_url' => 'https://test.wordcamp.org/2026',
            'status' => 'active',
            'is_visible' => true,
        ]));
        EventData::put($event->id, 'sessions', $sessions);

        return $event;
    }

    /** A session at a local clock time in a zone, as the normalizer stores it. */
    private function at(int $id, string $localStart, string $zone, int $minutes = 30): array
    {
        return [
            'id' => $id,
            'title' => "Session {$id}",
            'starts_at' => CarbonImmutable::parse($localStart, $zone)->toIso8601String(),
            'duration_seconds' => $minutes * 60,
        ];
    }

    private function openAt(Event $event, string $utc): bool
    {
        return ChatWindow::for($event)->current(CarbonImmutable::parse($utc)) !== null;
    }

    // ---- Opening and closing --------------------------------------------------

    public function test_one_day_in_india_opens_an_hour_before_and_closes_an_hour_after_exactly(): void
    {
        $zone = 'Asia/Kolkata'; // UTC+05:30
        $event = $this->event(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-10', 'timezone' => $zone], [
            $this->at(1, '2026-10-10 10:00', $zone),
            $this->at(2, '2026-10-10 17:00', $zone), // ends 17:30
        ]);

        // Opens 09:00 IST = 03:30 UTC; closes 18:30 IST = 13:00 UTC.
        $this->assertFalse($this->openAt($event, '2026-10-10T03:29:59Z'));
        $this->assertTrue($this->openAt($event, '2026-10-10T03:30:00Z'));
        $this->assertTrue($this->openAt($event, '2026-10-10T12:59:59Z'));
        $this->assertFalse($this->openAt($event, '2026-10-10T13:00:00Z'));

        $window = ChatWindow::for($event)->windows()[0];
        $this->assertSame('2026-10-10', $window['day']);
        $this->assertSame('09:00', $window['opens']->setTimezone($zone)->format('H:i'));
        $this->assertSame('18:30', $window['closes']->setTimezone($zone)->format('H:i'));
    }

    public function test_each_day_of_a_multi_day_event_has_its_own_window_and_nights_are_closed(): void
    {
        $zone = 'Europe/Berlin';
        $event = $this->event(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-12', 'timezone' => $zone], [
            $this->at(1, '2026-10-10 09:00', $zone),
            $this->at(2, '2026-10-10 18:00', $zone, 60),
            $this->at(3, '2026-10-11 10:30', $zone),
            $this->at(4, '2026-10-11 16:00', $zone),
            $this->at(5, '2026-10-12 09:30', $zone, 180), // Contributor Day, one long session
        ]);

        $windows = ChatWindow::for($event)->windows();
        $local = fn ($w) => [$w['day'], $w['opens']->setTimezone($zone)->format('H:i'), $w['closes']->setTimezone($zone)->format('H:i')];

        $this->assertSame([
            ['2026-10-10', '08:00', '20:00'],
            ['2026-10-11', '09:30', '17:30'],
            ['2026-10-12', '08:30', '13:30'],
        ], array_map($local, $windows));

        // 23:00 Berlin on day 1 — closed, and the next opening is day 2 at 09:30.
        $status = ChatWindow::for($event)->status(CarbonImmutable::parse('2026-10-10 23:00', $zone));
        $this->assertFalse($status['open']);
        $this->assertSame('Sun 11 Oct, 9:30 AM', $status['opens_label']);
        $this->assertSame(CarbonImmutable::parse('2026-10-11 09:30', $zone)->toIso8601String(), CarbonImmutable::parse($status['opens_at'])->setTimezone($zone)->toIso8601String());

        // Mid-morning day 2 — open, day 2 of 3.
        $status = ChatWindow::for($event)->status(CarbonImmutable::parse('2026-10-11 11:00', $zone));
        $this->assertTrue($status['open']);
        $this->assertSame(2, $status['day_number']);
        $this->assertSame(3, $status['days']);
        $this->assertSame('5:30 PM', $status['closes_label']);
    }

    public function test_a_day_without_sessions_falls_back_to_eight_to_seven_local(): void
    {
        $zone = 'Asia/Kolkata';
        $event = $this->event(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-11', 'timezone' => $zone], [
            $this->at(1, '2026-10-10 10:00', $zone),
        ]);

        $day2 = ChatWindow::for($event)->windows()[1];
        $this->assertSame('2026-10-11', $day2['day']);
        $this->assertSame('08:00', $day2['opens']->setTimezone($zone)->format('H:i'));
        $this->assertSame('19:00', $day2['closes']->setTimezone($zone)->format('H:i'));
    }

    public function test_after_the_last_day_it_stays_closed_for_good(): void
    {
        $zone = 'UTC';
        $event = $this->event(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-10', 'timezone' => $zone], [
            $this->at(1, '2026-10-10 10:00', $zone),
        ]);

        $status = ChatWindow::for($event)->status(CarbonImmutable::parse('2026-10-11T10:00:00Z'));
        $this->assertFalse($status['open']);
        $this->assertTrue($status['ended']);
        $this->assertNull($status['opens_at']);
    }

    public function test_sessions_outside_the_event_dates_do_not_open_the_chat(): void
    {
        $zone = 'UTC';
        $event = $this->event(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-10', 'timezone' => $zone], [
            $this->at(1, '2026-10-09 10:00', $zone), // a pre-event meetup the day before
            $this->at(2, '2026-10-10 10:00', $zone),
        ]);

        $this->assertSame(['2026-10-10'], array_column(ChatWindow::for($event)->windows(), 'day'));
        $this->assertFalse($this->openAt($event, '2026-10-09T10:00:00Z'));
    }

    public function test_without_dates_the_session_days_are_the_event_days(): void
    {
        $zone = 'UTC';
        $event = $this->event(['timezone' => $zone], [
            $this->at(1, '2026-10-10 10:00', $zone),
            $this->at(2, '2026-10-11 10:00', $zone),
        ]);

        $this->assertSame(['2026-10-10', '2026-10-11'], array_column(ChatWindow::for($event)->windows(), 'day'));
    }

    public function test_no_schedule_and_no_dates_means_no_chat(): void
    {
        $event = $this->event();

        $this->assertSame([], ChatWindow::for($event)->windows());
        $this->assertFalse(ChatWindow::for($event)->status()['open']);
        $this->assertFalse(ChatWindow::for($event)->status()['ended']);
    }

    // ---- Time zones -----------------------------------------------------------

    public function test_the_day_the_clocks_change_is_handled(): void
    {
        // US daylight saving ends at 02:00 on 1 Nov 2026 (PDT UTC-7 → PST UTC-8).
        $zone = 'America/Los_Angeles';
        $event = $this->event(['starts_on' => '2026-10-31', 'ends_on' => '2026-11-01', 'timezone' => $zone], [
            $this->at(1, '2026-10-31 09:00', $zone),
            $this->at(2, '2026-11-01 09:00', $zone),
        ]);

        [$sat, $sun] = ChatWindow::for($event)->windows();

        $this->assertSame('2026-10-31T15:00:00+00:00', $sat['opens']->toIso8601String()); // 08:00 PDT
        $this->assertSame('2026-11-01T16:00:00+00:00', $sun['opens']->toIso8601String()); // 08:00 PST
        $this->assertSame('08:00', $sun['opens']->setTimezone($zone)->format('H:i'));
    }

    public function test_a_zone_ahead_of_utc_counts_the_local_day_not_the_utc_day(): void
    {
        // New Zealand is UTC+13 in October: 09:00 on the 10th is still the 9th in UTC.
        $zone = 'Pacific/Auckland';
        $event = $this->event(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-10', 'timezone' => $zone], [
            $this->at(1, '2026-10-10 09:00', $zone),
        ]);

        $window = ChatWindow::for($event)->windows()[0];
        $this->assertSame('2026-10-10', $window['day']);
        $this->assertSame('2026-10-09T19:00:00+00:00', $window['opens']->toIso8601String());
        $this->assertTrue($this->openAt($event, '2026-10-09T19:30:00Z'));
    }

    public function test_a_fixed_offset_zone_works_too(): void
    {
        $event = $this->event(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-10', 'timezone' => '+05:30'], [
            $this->at(1, '2026-10-10 10:00', '+05:30'),
        ]);

        $this->assertSame('2026-10-10T03:30:00+00:00', ChatWindow::for($event)->windows()[0]['opens']->toIso8601String());
    }

    public function test_the_servers_own_time_zone_never_matters(): void
    {
        $zone = 'Asia/Kolkata';
        $event = $this->event(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-10', 'timezone' => $zone], [
            $this->at(1, '2026-10-10 10:00', $zone),
        ]);
        $expected = array_map(fn ($w) => $w['opens']->toIso8601String(), ChatWindow::for($event)->windows());

        $original = date_default_timezone_get();
        try {
            foreach (['America/New_York', 'Pacific/Kiritimati', 'Asia/Tokyo'] as $serverZone) {
                date_default_timezone_set($serverZone);
                $this->assertSame($expected, array_map(fn ($w) => $w['opens']->toIso8601String(), ChatWindow::for($event->fresh())->windows()), $serverZone);
                $this->assertTrue($this->openAt($event->fresh(), '2026-10-10T04:00:00Z'), $serverZone);
            }
        } finally {
            date_default_timezone_set($original);
        }
    }

    // ---- Messages obey the window, three per day ------------------------------------

    /** @return array{0: array, 1: array, 2: Event} two mutual matches at a two-day IST event */
    private function mutualAtTwoDayEvent(): array
    {
        $zone = 'Asia/Kolkata';
        $event = $this->event(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-11', 'timezone' => $zone], [
            $this->at(1, '2026-10-10 10:00', $zone),
            $this->at(2, '2026-10-10 17:00', $zone),
            $this->at(3, '2026-10-11 10:00', $zone),
            $this->at(4, '2026-10-11 15:00', $zone),
        ]);

        $join = fn () => $this->postJson(route('api.discovery.store', $event), ['tags' => ['developer']])->json();
        $asha = $join();
        $ben = $join();
        $this->withToken($asha['owner_token'])->postJson(route('api.discovery.waves.store', [$event, $asha['discovery_id']]), ['to' => $ben['discovery_id'], 'name' => 'Asha'])->assertCreated();
        $this->withToken($ben['owner_token'])->postJson(route('api.discovery.waves.store', [$event, $ben['discovery_id']]), ['to' => $asha['discovery_id'], 'name' => 'Ben'])->assertCreated();

        return [$asha, $ben, $event];
    }

    private function send(Event $event, array $from, array $to, string $body)
    {
        return $this->withToken($from['owner_token'])->postJson(
            route('api.discovery.messages.store', [$event, $from['discovery_id']]),
            ['to' => $to['discovery_id'], 'body' => $body]
        );
    }

    public function test_messages_are_refused_outside_the_window_and_say_when_it_opens(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-10 07:00', 'Asia/Kolkata'));
        [$asha, $ben, $event] = $this->mutualAtTwoDayEvent();

        $this->send($event, $asha, $ben, 'Hi')->assertUnprocessable()
            ->assertJsonValidationErrors(['body' => 'next at 9:00 AM (event time)']);

        $this->withToken($asha['owner_token'])->getJson(route('api.discovery.waves.index', [$event, $asha['discovery_id']]))
            ->assertJson(['chat' => ['open' => false, 'opens_label' => '9:00 AM', 'timezone' => 'Asia/Kolkata'], 'mutual' => [['can_send' => false, 'reason' => 'closed']]]);

        // A wave is fine any time; a wave carrying words waits for the window.
        $carl = $this->postJson(route('api.discovery.store', $event), ['tags' => ['developer']])->json();
        $this->withToken($carl['owner_token'])->postJson(route('api.discovery.waves.store', [$event, $carl['discovery_id']]), ['to' => $asha['discovery_id'], 'name' => 'Carl', 'message' => 'Early!'])
            ->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->withToken($carl['owner_token'])->postJson(route('api.discovery.waves.store', [$event, $carl['discovery_id']]), ['to' => $asha['discovery_id'], 'name' => 'Carl'])
            ->assertCreated();
    }

    public function test_the_closing_moment_is_already_closed(): void
    {
        [$asha, $ben, $event] = $this->mutualAtTwoDayEvent();

        // Day 1 closes 18:30 IST.
        $this->travelTo(CarbonImmutable::parse('2026-10-10 18:29:59', 'Asia/Kolkata'));
        $this->send($event, $asha, $ben, 'Just in time')->assertCreated();

        $this->travelTo(CarbonImmutable::parse('2026-10-10 18:30:00', 'Asia/Kolkata'));
        $this->send($event, $ben, $asha, 'Too late')->assertUnprocessable()->assertJsonValidationErrors(['body' => 'Sun 11 Oct, 9:00 AM']);
    }

    public function test_three_messages_per_person_per_day_and_turns_reset_each_day(): void
    {
        [$asha, $ben, $event] = $this->mutualAtTwoDayEvent();

        // Day 1, 11:00 IST: three each, alternating.
        $this->travelTo(CarbonImmutable::parse('2026-10-10 11:00', 'Asia/Kolkata'));
        foreach ([1, 2, 3] as $n) {
            $this->send($event, $asha, $ben, "Asha {$n}")->assertCreated();
            $this->send($event, $ben, $asha, "Ben {$n}")->assertCreated();
        }
        $this->send($event, $asha, $ben, 'Asha 4')->assertUnprocessable()->assertJsonValidationErrors(['body' => 'today']);

        // Overnight: closed for both, limit or not.
        $this->travelTo(CarbonImmutable::parse('2026-10-11 02:00', 'Asia/Kolkata'));
        $this->send($event, $ben, $asha, 'Night')->assertUnprocessable()->assertJsonValidationErrors(['body' => '9:00 AM']);

        // Day 2, 10:00 IST: a fresh three each — and Ben (who wrote last yesterday) may start.
        $this->travelTo(CarbonImmutable::parse('2026-10-11 10:00', 'Asia/Kolkata'));
        $this->send($event, $ben, $asha, 'Morning!')->assertCreated();
        $this->send($event, $ben, $asha, 'Again')->assertUnprocessable()->assertJsonValidationErrors(['body' => 'Wait for their reply']);

        $state = $this->withToken($asha['owner_token'])->getJson(route('api.discovery.waves.index', [$event, $asha['discovery_id']]))
            ->assertJson(['chat' => ['open' => true, 'day' => '2026-10-11', 'day_number' => 2], 'mutual' => [['mine' => 0, 'theirs' => 1, 'can_send' => true]]])
            ->json('mutual.0.messages');

        // Every message keeps its event day, for the app to group by.
        $this->assertCount(7, $state);
        $this->assertSame(['2026-10-10', '2026-10-11'], array_values(array_unique(array_column($state, 'day'))));
    }

    // ---- Session times and the event's time zone -------------------------------------

    public function test_session_times_are_read_as_the_venues_clock(): void
    {
        $zone = new DateTimeZone('Asia/Kolkata');
        $tenAm = gmmktime(10, 0, 0, 10, 10, 2026); // WordCamp stores "10:00" as 10:00 UTC

        $this->assertSame('2026-10-10T10:00:00+05:30', WordCampNormalizer::sessionInstant($tenAm, $zone, 'wall'));
        $this->assertSame('2026-10-10T15:30:00+05:30', WordCampNormalizer::sessionInstant($tenAm, $zone, 'instant'));
        $this->assertSame('2026-10-10T10:00:00+00:00', WordCampNormalizer::sessionInstant($tenAm, null));
    }

    public function test_the_sites_own_displayed_time_decides_how_to_read_the_timestamp(): void
    {
        $zone = new DateTimeZone('Asia/Kolkata');
        $ts = gmmktime(10, 0, 0, 10, 10, 2026);
        $raw = fn (string $shown) => [['id' => 1, 'meta' => ['_wcpt_session_time' => $ts], 'session_date_time' => ['date' => 'October 10, 2026', 'time' => $shown]]];

        $this->assertSame('wall', WordCampNormalizer::sessionClock($raw('10:00 am'), $zone));
        $this->assertSame('wall', WordCampNormalizer::sessionClock($raw('10:00'), $zone));
        $this->assertSame('instant', WordCampNormalizer::sessionClock($raw('3:30 pm'), $zone));
        $this->assertSame('instant', WordCampNormalizer::sessionClock($raw('15:30'), $zone));
        $this->assertSame('wall', WordCampNormalizer::sessionClock([['id' => 1, 'meta' => ['_wcpt_session_time' => $ts]]], $zone), 'no hint: WordCamp\'s normal behaviour');
    }

    public function test_wordpress_time_zone_settings_become_usable_zones(): void
    {
        $this->assertSame('Asia/Kolkata', EventTime::fromWordPress('Asia/Kolkata', 5.5));
        $this->assertSame('+05:30', EventTime::fromWordPress('', 5.5));
        $this->assertSame('-03:30', EventTime::fromWordPress('', '-3.5'));
        $this->assertSame('+00:00', EventTime::fromWordPress('', 0));
        $this->assertNull(EventTime::fromWordPress('Mars/Olympus', null));

        $this->assertSame('+05:30', EventTime::normalize('UTC+5:30'));
        $this->assertSame('-08:00', EventTime::normalize('-8'));
        $this->assertNull(EventTime::normalize('+05:17'));
        $this->assertNull(EventTime::normalize('nonsense'));
    }

    public function test_the_fetch_reads_the_time_zone_from_the_site_unless_an_admin_set_it(): void
    {
        $site = 'https://test.wordcamp.org/2026';
        $ok = fn ($body) => Http::response($body, 200, ['X-WP-TotalPages' => '1']);
        Http::fake([
            $site.'/wp-json/' => Http::response(['timezone_string' => 'Asia/Kolkata', 'gmt_offset' => 5.5]),
            $site.'/wp-json/wp/v2/sessions*' => $ok([['id' => 1, 'title' => ['rendered' => 'Keynote'], 'meta' => ['_wcpt_session_time' => gmmktime(10, 0, 0, 10, 10, 2026), '_wcpt_session_duration' => 3600]]]),
            $site.'/wp-json/wp/v2/*' => $ok([]),
        ]);

        $event = $this->event(['source_site_url' => $site]);
        FetchSpeakersSponsorsSessionsJob::dispatchSync($event);

        $this->assertSame('Asia/Kolkata', $event->fresh()->timezone);
        $this->assertSame('2026-10-10T10:00:00+05:30', EventData::get($event->id, 'sessions')[0]['starts_at']);

        $locked = $this->event(['source_site_url' => $site, 'timezone' => 'Europe/London', 'timezone_locked' => true]);
        FetchSpeakersSponsorsSessionsJob::dispatchSync($locked);

        $this->assertSame('Europe/London', $locked->fresh()->timezone);
        $this->assertSame('2026-10-10T10:00:00+01:00', EventData::get($locked->id, 'sessions')[0]['starts_at']);
    }

    public function test_an_admin_can_set_or_clear_the_time_zone(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $admin = \App\Models\User::factory()->create();
        $event = $this->event(['timezone' => 'UTC']);
        $form = fn (array $extra) => $extra + [
            'display_name' => $event->display_name, 'slug' => $event->slug, 'source_site_url' => $event->source_site_url,
            'status' => 'active', 'is_visible' => 1,
        ];

        $this->actingAs($admin)->put(route('admin.events.update', $event), $form(['timezone' => 'Asia/Kolkata']))->assertRedirect();
        $this->assertSame(['Asia/Kolkata', true], [$event->fresh()->timezone, $event->fresh()->timezone_locked]);
        // A new zone re-reads the session times in it.
        \Illuminate\Support\Facades\Queue::assertPushed(FetchSpeakersSponsorsSessionsJob::class);

        $this->actingAs($admin)->put(route('admin.events.update', $event), $form(['timezone' => 'UTC+5:30']))->assertRedirect();
        $this->assertSame('+05:30', $event->fresh()->timezone);

        $this->actingAs($admin)->put(route('admin.events.update', $event), $form(['timezone' => 'Nowhere/Land']))->assertSessionHasErrors('timezone');

        $this->actingAs($admin)->put(route('admin.events.update', $event), $form(['timezone' => '']))->assertRedirect();
        $this->assertFalse($event->fresh()->timezone_locked, 'blank hands it back to the WordCamp site');
    }
}
