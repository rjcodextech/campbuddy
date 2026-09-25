<?php

namespace Tests\Feature;

use App\Jobs\EvaluateEventLifecycleJob;
use App\Models\AttendeeRoster;
use App\Models\DiscoveryProfile;
use App\Models\Event;
use App\Support\ChatWindow;
use App\Support\EventData;
use App\Support\EventTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nothing of an attendee's may disappear while the event might still be on.
 *
 * Scraped events often have no end date (or a wrong one) — a 3-day event can
 * arrive as "starts 1 Oct" with sessions on the 3rd. Everything that ends an
 * event (archiving, discovery profile expiry, the chat) must go by the
 * event's REAL last day — end date, start date or last session day, whichever
 * is latest — and then keep going for RETENTION_DAYS more.
 */
class RetentionTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $attributes = []): Event
    {
        return Event::withoutEvents(fn () => Event::create($attributes + [
            'slug' => 'wc-retention-'.uniqid(),
            'display_name' => 'WordCamp Retention 2026',
            'source_site_url' => 'https://retention.wordcamp.org/2026',
            'status' => 'active',
            'is_visible' => true,
        ]));
    }

    /** A 3-day event as it arrives when scraped: no end date, sessions on days 1 and 3. */
    private function threeDayEventWithoutEndDate(array $attributes = []): Event
    {
        $event = $this->event($attributes + ['starts_on' => '2026-10-01', 'ends_on' => null, 'timezone' => 'UTC']);

        EventData::put($event->id, 'sessions', [
            ['id' => 1, 'title' => 'Day one', 'starts_at' => '2026-10-01T09:00:00+00:00', 'duration_seconds' => 3600],
            ['id' => 2, 'title' => 'Day three', 'starts_at' => '2026-10-03T14:00:00+00:00', 'duration_seconds' => 3600],
        ]);

        return $event;
    }

    // ---- The event's real last day -------------------------------------------------

    public function test_the_last_day_is_the_latest_of_end_date_start_date_and_last_session(): void
    {
        $noEnd = $this->threeDayEventWithoutEndDate();
        $this->assertSame('2026-10-03', EventTime::lastDay($noEnd));

        // A wrong (too early) end date is overruled by the schedule…
        $wrongEnd = $this->threeDayEventWithoutEndDate(['ends_on' => '2026-10-02', 'slug' => 'wc-wrong-end']);
        $this->assertSame('2026-10-03', EventTime::lastDay($wrongEnd));

        // …and a later end date still wins.
        $laterEnd = $this->threeDayEventWithoutEndDate(['ends_on' => '2026-10-05', 'slug' => 'wc-later-end']);
        $this->assertSame('2026-10-05', EventTime::lastDay($laterEnd));

        // No sessions: the end date, then the start date.
        $this->assertSame('2026-10-08', EventTime::lastDay($this->event(['starts_on' => '2026-10-07', 'ends_on' => '2026-10-08'])));
        $this->assertSame('2026-10-07', EventTime::lastDay($this->event(['starts_on' => '2026-10-07'])));
        $this->assertNull(EventTime::lastDay($this->event()));
    }

    public function test_a_session_ending_after_midnight_counts_for_the_next_day(): void
    {
        $event = $this->event(['starts_on' => '2026-10-01', 'timezone' => 'UTC']);
        EventData::put($event->id, 'sessions', [
            ['id' => 1, 'title' => 'Afterparty', 'starts_at' => '2026-10-02T23:00:00+00:00', 'duration_seconds' => 7200],
        ]);

        $this->assertSame('2026-10-03', EventTime::lastDay($event));
    }

    public function test_a_stray_session_date_far_away_does_not_stretch_the_event(): void
    {
        $event = $this->event(['starts_on' => '2026-10-01', 'timezone' => 'UTC']);
        EventData::put($event->id, 'sessions', [
            ['id' => 1, 'title' => 'Typo year', 'starts_at' => '2026-12-25T09:00:00+00:00', 'duration_seconds' => 3600],
            ['id' => 2, 'title' => 'Real', 'starts_at' => '2026-10-02T09:00:00+00:00', 'duration_seconds' => 3600],
        ]);

        $this->assertSame('2026-10-02', EventTime::lastDay($event));
    }

    public function test_writing_new_schedule_data_updates_the_last_day_at_once(): void
    {
        $event = $this->threeDayEventWithoutEndDate();
        $this->assertSame('2026-10-03', EventTime::lastDay($event));

        EventData::put($event->id, 'sessions', [
            ['id' => 1, 'title' => 'Only day', 'starts_at' => '2026-10-01T09:00:00+00:00', 'duration_seconds' => 3600],
        ]);

        $this->assertSame('2026-10-01', EventTime::lastDay($event));
    }

    // ---- Retention window ---------------------------------------------------------

    public function test_an_unknown_time_zone_never_cuts_an_event_short(): void
    {
        $event = $this->event(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-10', 'timezone' => null]);

        // The latest zone on Earth (UTC−12): end of the 10th + 3 days = 11:59:59 UTC on the 14th.
        $this->assertSame('2026-10-14T11:59:59+00:00', EventTime::retentionEnd($event)->toIso8601String());

        // Still retained at 11:00 UTC on the 14th — it is only 04:00 on the 14th in Los Angeles, and 23:00 on the 13th in Baker Island.
        $this->assertTrue(EventTime::retained($event, CarbonImmutable::parse('2026-10-14T11:00:00Z')));
        $this->assertFalse(EventTime::retained($event, CarbonImmutable::parse('2026-10-14T12:30:00Z')));
    }

    public function test_an_event_with_no_dates_is_always_retained(): void
    {
        $event = $this->event();

        $this->assertNull(EventTime::retentionEnd($event));
        $this->assertTrue(EventTime::retained($event, CarbonImmutable::parse('2035-01-01T00:00:00Z')));

        $this->travelTo(CarbonImmutable::parse('2035-01-01T00:00:00Z'));
        EvaluateEventLifecycleJob::dispatchSync();
        $this->assertSame('active', $event->fresh()->status);
    }

    // ---- The event does not disappear mid-way (the Sylhet case) --------------------------

    public function test_an_event_without_an_end_date_is_not_archived_or_hidden_while_its_sessions_continue(): void
    {
        $event = $this->threeDayEventWithoutEndDate();

        // 01:00 UTC on the 2nd: the old rule archived it here (last day = start date).
        $this->travelTo(CarbonImmutable::parse('2026-10-02T01:00:00Z'));
        EvaluateEventLifecycleJob::dispatchSync();
        $this->assertSame('active', $event->fresh()->status);

        // Day 3: the event's own pages are still there for attendees.
        $this->travelTo(CarbonImmutable::parse('2026-10-03T12:00:00Z'));
        EvaluateEventLifecycleJob::dispatchSync();
        $this->assertSame('active', $event->fresh()->status);
        $this->withoutVite()->get(route('event.home', $event))->assertOk();

        // Retention: day 3 + 3 days = end of the 6th; archived the day after.
        $this->travelTo(CarbonImmutable::parse('2026-10-06T23:00:00Z'));
        EvaluateEventLifecycleJob::dispatchSync();
        $this->assertSame('active', $event->fresh()->status);

        $this->travelTo(CarbonImmutable::parse('2026-10-07T01:00:00Z'));
        EvaluateEventLifecycleJob::dispatchSync();
        $this->assertSame('archived', $event->fresh()->status);
    }

    public function test_the_picker_lists_such_an_event_through_its_real_last_day(): void
    {
        $this->threeDayEventWithoutEndDate();

        $this->travelTo(CarbonImmutable::parse('2026-10-03T12:00:00Z'));
        $this->withoutVite()->get('/')->assertSee('WordCamp Retention 2026');

        $this->travelTo(CarbonImmutable::parse('2026-10-04T12:00:00Z'));
        $this->withoutVite()->get('/')->assertDontSee('WordCamp Retention 2026');
    }

    public function test_the_page_tells_the_app_the_real_last_day_and_that_it_is_still_on(): void
    {
        $event = $this->threeDayEventWithoutEndDate();

        // 2 Oct 12:00 was "after" under the old rule (end date missing → start date).
        $this->travelTo(CarbonImmutable::parse('2026-10-02T12:00:00Z'));
        $this->withoutVite()->get(route('event.home', $event))
            ->assertSee('data-event-end="2026-10-03"', false)
            ->assertSee('data-event-phase="during"', false);
    }

    public function test_the_chat_runs_on_every_day_the_event_really_runs(): void
    {
        $event = $this->threeDayEventWithoutEndDate();

        $days = array_column(ChatWindow::for($event)->windows(), 'day');

        $this->assertSame(['2026-10-01', '2026-10-02', '2026-10-03'], $days);
    }

    // ---- Attendee-entered data ----------------------------------------------------

    public function test_a_profile_stamped_early_survives_to_the_real_last_day_and_expires_after_retention(): void
    {
        $event = $this->threeDayEventWithoutEndDate();

        // A profile from before the schedule was known: stamped with the end of day 1.
        $this->travelTo(CarbonImmutable::parse('2026-10-01T10:00:00Z'));
        $card = $this->postJson(route('api.discovery.store', $event), ['tags' => ['developer']])->assertCreated()->json();
        DiscoveryProfile::where('discovery_id', $card['discovery_id'])->update(['expires_at' => '2026-10-01 23:59:59']);

        $listed = fn () => collect($this->getJson(route('api.discovery.index', $event))->json('data'))->pluck('discovery_id')->all();

        $this->travelTo(CarbonImmutable::parse('2026-10-03T12:00:00Z'));
        $this->assertContains($card['discovery_id'], $listed(), 'day 3: the profile is still there');

        $this->travelTo(CarbonImmutable::parse('2026-10-06T23:00:00Z'));
        $this->assertContains($card['discovery_id'], $listed(), 'inside the retention days');

        // Retention over: the stored stamp (long past) decides.
        $this->travelTo(CarbonImmutable::parse('2026-10-08T00:30:00Z'));
        $this->assertNotContains($card['discovery_id'], $listed());
    }

    public function test_a_new_profile_is_stamped_with_the_retention_end(): void
    {
        $event = $this->threeDayEventWithoutEndDate();

        $this->travelTo(CarbonImmutable::parse('2026-10-01T10:00:00Z'));
        $this->postJson(route('api.discovery.store', $event), ['tags' => ['developer']])->assertCreated();

        $this->assertSame('2026-10-06T23:59:59+00:00', CarbonImmutable::parse(DiscoveryProfile::first()->expires_at)->utc()->toIso8601String());
    }

    public function test_open_to_meet_on_the_attendee_list_lasts_as_long_as_the_profile(): void
    {
        $event = $this->threeDayEventWithoutEndDate();
        $entry = AttendeeRoster::create([
            'event_id' => $event->id, 'name' => 'Asha Rao', 'gravatar_url' => null, 'links' => [],
            'content_hash' => hash('sha256', 'Asha Rao'), 'is_suppressed' => false,
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-10-01T10:00:00Z'));
        $this->postJson(route('api.discovery.store', $event), ['tags' => ['developer'], 'attendee_roster_id' => $entry->id])->assertCreated();
        DiscoveryProfile::query()->update(['expires_at' => '2026-10-01 23:59:59']);

        $this->travelTo(CarbonImmutable::parse('2026-10-03T12:00:00Z'));
        $row = collect($this->getJson(route('api.events.roster', $event))->json('data'))->firstWhere('name', 'Asha Rao');

        $this->assertTrue($row['open_to_meet']);
    }
}
