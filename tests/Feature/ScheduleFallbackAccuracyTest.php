<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\FetchLog;
use App\Services\SchedulePageProbe;
use App\Services\WordCampNormalizer;
use App\Services\WordCampRestClient;
use App\Support\HtmlText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reading a WordCamp's own schedule page without guessing. Each case is one that went wrong in the
 * first version of the page-time fallback (30 Oct 2026): a 24-hour page's "07:30" read as 7:30 pm,
 * "October 2026" read as 20 October, a page with no year dated in the wrong year, a day-two session
 * put on day one.
 */
class ScheduleFallbackAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private function minutes(string $html): array
    {
        return array_column(SchedulePageProbe::parse($html)['tokens'], 'minutes');
    }

    public function test_a_24_hour_page_is_read_as_written(): void
    {
        // Bengaluru 2026's page: "08:00 … 15:30".
        $this->assertSame([7 * 60 + 30, 15 * 60 + 30], $this->minutes('<p>07:30 Registration</p><p>15:30 Lightning talk</p>'));
    }

    public function test_a_12_hour_page_without_am_pm_reads_one_to_seven_as_the_afternoon(): void
    {
        $this->assertSame([9 * 60, 13 * 60 + 30, 16 * 60], $this->minutes('<p>9:00 Opening</p><p>1:30 Panel</p><p>4:00 Closing</p>'));
    }

    public function test_a_page_that_prints_am_pm_leaves_a_bare_time_alone(): void
    {
        $this->assertSame([9 * 60, 14 * 60, 6 * 60 + 30], $this->minutes('<p>9:00 AM Doors</p><p>2:00 PM Talk</p><p>6:30 Breakfast</p>'));
    }

    public function test_date_headings_are_whole_numbers_and_keep_unknown_years_unknown(): void
    {
        $page = SchedulePageProbe::parse('<h2>Published October 2026</h2><h2>Sunday 22 November</h2><p>10:00 Keynote</p><h2>Nov 23, 2026</h2><p>11:00 Panel</p><h2>2026-11-24</h2><p>12:00 Lunch</p>');

        $this->assertSame(['--11-22', '2026-11-23', '2026-11-24'], array_column($page['tokens'], 'date'));
    }

    public function test_a_page_date_is_placed_within_the_event_or_not_at_all(): void
    {
        // WordCamp Asia 2027 (9–11 April): a page that says "10 April" means 2027, not this year.
        $this->assertSame('2027-04-10', WordCampNormalizer::slotDate('--04-10', '2027-04-09', '2027-04-11'));
        $this->assertNull(WordCampNormalizer::slotDate('--05-10', '2027-04-09', '2027-04-11'), 'not one of the event days');
        $this->assertNull(WordCampNormalizer::slotDate('2026-04-10', '2027-04-09', '2027-04-11'));
        $this->assertNull(WordCampNormalizer::slotDate(null, '2026-12-12', '2026-12-13'), 'two days and no heading: unknown');
        $this->assertSame('2026-11-22', WordCampNormalizer::slotDate(null, '2026-11-22', '2026-11-22'), 'one day: that day');
        $this->assertSame('2027-01-01', WordCampNormalizer::slotDate('--01-01', '2026-12-31', '2027-01-01'), 'across new year');
        $this->assertNull(WordCampNormalizer::slotDate('--04-10', null, null), 'no event dates: no guess');
    }

    private function normalize(string $html, ?string $start, ?string $end): ?string
    {
        $page = SchedulePageProbe::parse($html);
        $titles = ['Untimed Talk'];
        $normalizer = new WordCampNormalizer(new WordCampRestClient('https://test.wordcamp.org/2026'));
        [$session] = $normalizer->normalizeSessions(
            [['id' => 1, 'title' => ['rendered' => 'Untimed Talk'], 'meta' => []]],
            [], [], new \DateTimeZone('Asia/Kolkata'),
            SchedulePageProbe::timesFor($page, $titles), $start, SchedulePageProbe::slotsFor($page, $titles), $end,
        );

        return $session['starts_at'];
    }

    public function test_a_session_the_site_did_not_time_gets_the_page_time_only_when_it_is_clear(): void
    {
        $twoDays = '<h2>Saturday 12 December</h2><p>10:00 Opening</p><h2>Sunday 13 December</h2><p>11:40 Untimed Talk</p>';
        $this->assertSame('2026-12-13T11:40:00+05:30', $this->normalize($twoDays, '2026-12-12', '2026-12-13'), 'day two stays day two');

        $noHeading = '<p>11:40 Untimed Talk</p>';
        $this->assertNull($this->normalize($noHeading, '2026-12-12', '2026-12-13'), 'two days and no heading: no time rather than a wrong one');
        $this->assertSame('2026-11-22T11:40:00+05:30', $this->normalize($noHeading, '2026-11-22', '2026-11-22'));

        $twice = '<h2>13 December</h2><p>11:40 Untimed Talk</p><p>15:00 Untimed Talk</p>';
        $this->assertNull($this->normalize($twice, '2026-12-12', '2026-12-13'), 'shown at two times: unknown');

        $this->assertNull($this->normalize('<p>Nothing here</p>', '2026-12-12', '2026-12-13'));
    }

    public function test_a_session_the_site_did_time_keeps_its_own_time(): void
    {
        $normalizer = new WordCampNormalizer(new WordCampRestClient('https://test.wordcamp.org/2026'));
        // WordCamp's own convention when nothing else says otherwise: the venue's wall clock written as UTC (sessionClock).
        $at = (new \DateTimeImmutable('2026-12-13 09:00', new \DateTimeZone('UTC')))->getTimestamp();
        [$session] = $normalizer->normalizeSessions([['id' => 1, 'title' => ['rendered' => 'Timed'], 'meta' => ['_wcpt_session_time' => $at]]], [], [], new \DateTimeZone('Asia/Kolkata'), [], '2026-12-12', [], '2026-12-13');

        $this->assertSame('2026-12-13T09:00:00+05:30', $session['starts_at']);
    }

    public function test_text_escaped_twice_by_the_site_reads_normally(): void
    {
        // WordCamp Canada 2026: "Cover, Media &amp; Text" showed on screen.
        $this->assertSame('Cover, Media & Text, Image', HtmlText::plain('<p>Cover, Media &amp;amp; Text, Image</p>'));
        $this->assertSame('People & Culture — XWP', HtmlText::plain('<p>People &amp;amp; Culture &amp;#8212; XWP</p>'));
        $this->assertSame('Use <b> for bold', HtmlText::plain('<p>Use &amp;lt;b&amp;gt; for bold</p>'), 'still text, never markup');
    }

    public function test_a_long_log_line_in_hindi_is_stored_whole_characters(): void
    {
        $event = Event::withoutEvents(fn () => Event::create(['slug' => 'wc-log', 'display_name' => 'Log', 'source_site_url' => 'https://log.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]));
        $message = str_repeat('सत्र का समय — ', 60);
        // Cut by bytes, this breaks a character: invalid UTF-8, which MySQL refuses.
        $this->assertFalse(mb_check_encoding(substr($message, 0, 500), 'UTF-8'));

        $log = FetchLog::create(['event_id' => $event->id, 'source' => 'x', 'job_type' => 'y', 'status' => 'ok', 'message' => substr($message, 0, 501), 'fetched_at' => now()]);

        $stored = $log->fresh()->message;
        $this->assertTrue(mb_check_encoding($stored, 'UTF-8'));
        $this->assertLessThanOrEqual(500, mb_strlen($stored));
    }
}
