<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventManager;
use App\Models\User;
use App\Support\EventData;
use App\Support\SocialKit;
use App\Support\SocialPlan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The fifteen posts in promotion order, their tentative days (SocialPlan) and the
 * calendar, the "remind me on my phone" switch and the morning reminder run.
 */
class SocialPlanTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'slug' => 'wordcamp-delhi-2026', 'display_name' => 'WordCamp Delhi 2026', 'short_name' => '#wcdelhi',
            'source_site_url' => 'https://delhi.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true,
            'timezone' => 'Asia/Kolkata', 'starts_on' => '2026-12-12', 'ends_on' => '2026-12-13',
            'info' => ['venue' => 'Dr. Ambedkar Intl. Centre, New Delhi'],
        ]);
    }

    public function test_fifteen_posts_in_the_order_an_event_is_promoted_each_with_its_day(): void
    {
        EventData::put($this->event->id, 'sessions', [['id' => 1, 'title' => 'Opening', 'starts_at' => '2026-12-12T03:30:00Z']]);
        $posts = SocialKit::posts($this->event, CarbonImmutable::parse('2026-10-22 10:00', 'Asia/Kolkata'));

        $this->assertSame(
            ['save-the-date', 'venue', 'speakers', 'sponsors', 'schedule', 'first-timer', 'contributor', 'wpsimplified', 'app', 'meet', 'countdown', 'today', 'spotlight', 'moments', 'thank-you'],
            array_column($posts, 'key'),
        );
        $byKey = collect($posts)->keyBy('key');
        $this->assertSame('2026-10-31', $byKey['save-the-date']['date']);
        $this->assertSame('Sat 31 Oct', $byKey['save-the-date']['date_label']);
        $this->assertSame('2026-12-14', $byKey['thank-you']['date']);
        $this->assertSame('#wcdelhi', $byKey['moments']['kicker']);
        $this->assertStringContainsString('https://wpsimplified.in', $byKey['wpsimplified']['caption']);
        foreach ($posts as $post) {
            $this->assertNotEmpty($post['when'], $post['key']);
        }
        // The free / no sign-up line is in no graphic.
        foreach ($posts as $post) {
            $this->assertStringNotContainsStringIgnoringCase('no sign-up', $post['kicker'].' '.$post['headline'].' '.$post['line']);
        }
    }

    public function test_a_late_start_spreads_the_missed_posts_over_the_days_left_in_order(): void
    {
        // Added to CampBuddy on 25 Nov, seventeen days before it starts.
        $this->event->forceFill(['created_at' => CarbonImmutable::parse('2026-11-25 04:00', 'UTC')])->save();
        $dates = SocialPlan::dates($this->event->fresh(), CarbonImmutable::parse('2026-11-27 10:00', 'Asia/Kolkata'));

        $this->assertSame('2026-11-25', $dates['save-the-date']['date'], 'the first post on the day it was added');
        $before = array_slice(array_column($dates, 'date'), 0, 11);
        $sorted = $before;
        sort($sorted);
        $this->assertSame($sorted, $before, 'still in order');
        $this->assertLessThan('2026-12-12', max($before), 'all before the event');
        $this->assertGreaterThan(5, count(array_unique($before)), 'spread, not piled on one day');

        // A day that has passed stays passed: the plan doesn't move with today.
        $this->assertSame($dates, SocialPlan::dates($this->event->fresh(), CarbonImmutable::parse('2026-12-05 10:00', 'Asia/Kolkata')));
    }

    public function test_the_calendar_shows_whole_weeks_with_the_posts_on_their_days(): void
    {
        $now = CarbonImmutable::parse('2026-10-22 10:00', 'Asia/Kolkata');
        $weeks = SocialPlan::calendar($this->event, SocialKit::posts($this->event, $now), $now);

        $this->assertNotEmpty($weeks);
        foreach ($weeks as $week) {
            $this->assertCount(7, $week);
        }
        $this->assertSame('Mon', CarbonImmutable::parse($weeks[0][0]['date'])->format('D'));
        $days = collect($weeks)->flatten(1)->keyBy('date');
        $this->assertSame('save-the-date', $days['2026-10-31']['posts'][0]['key']);
        $this->assertTrue($days['2026-12-12']['event']);
    }

    public function test_each_organizer_turns_post_reminders_on_for_themselves(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->post(route('admin.social.reminders'), ['on' => '1'])->assertRedirect();
        $this->assertTrue($admin->fresh()->social_reminders);
        $this->actingAs($admin)->post(route('admin.social.reminders'), ['on' => '0'])->assertRedirect();
        $this->assertFalse($admin->fresh()->social_reminders);

        $manager = EventManager::create(['name' => 'Asha', 'email' => 'asha@example.com', 'phone' => '+919800000000', 'password' => 'secret-password-1', 'is_active' => true]);
        $manager->events()->attach($this->event->id);
        $this->actingAs($manager, 'manager')->post(route('manager.social.reminders'), ['on' => '1'])->assertRedirect();
        $this->assertTrue($manager->fresh()->social_reminders);
    }

    public function test_the_morning_run_lists_the_posts_due_today_at_the_venue(): void
    {
        $this->event->forceFill(['created_at' => CarbonImmutable::parse('2026-10-01 04:00', 'UTC')])->save();
        $this->travelTo(CarbonImmutable::parse('2026-10-31 09:30', 'Asia/Kolkata'));

        $this->artisan('campbuddy:post-reminders', ['--dry-run' => true])
            ->expectsOutputToContain('WordCamp Delhi 2026: Coming up')
            ->assertSuccessful();

        $this->travelTo(CarbonImmutable::parse('2026-11-01 09:30', 'Asia/Kolkata'));
        $this->artisan('campbuddy:post-reminders', ['--dry-run' => true])
            ->expectsOutputToContain('0 posts due today')
            ->assertSuccessful();
    }
}
