<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Quest;
use App\Support\FirstTimerGuide;
use Database\Seeders\DemoEventSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * CampBuddy is for first-time WordCamp attendees first: the WordCamp 101
 * guide (general and per event), Home pointing to it, guidance next to
 * schedule facts, and friendly pages when something goes wrong.
 */
class FirstTimerExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function event(array $overrides = []): Event
    {
        return Event::withoutEvents(fn () => Event::create($overrides + [
            'slug' => 'wc-test',
            'display_name' => 'WordCamp Test 2026',
            'short_name' => 'WCTest',
            'source_site_url' => 'https://test.wordcamp.org/2026',
            'status' => 'active',
            'is_visible' => true,
            'info' => ['venue' => 'Town Hall, Main Street', 'code_of_conduct_url' => 'javascript:alert(1)'],
        ]));
    }

    public function test_the_general_guide_explains_wordcamp_without_an_event(): void
    {
        $response = $this->get(route('guide'))->assertOk();

        $response->assertSee('Your first WordCamp? Start here.')
            ->assertSee('What is a WordCamp?')
            ->assertSee('Hallway track')
            ->assertSee('Contributor Day')
            ->assertSee('Choose your WordCamp');

        foreach (FirstTimerGuide::faq() as $entry) {
            $response->assertSee($entry['q']);
        }
    }

    public function test_the_general_guide_sets_no_cookies(): void
    {
        $this->get(route('guide'))->assertOk()->assertCookieMissing(config('session.cookie'));
    }

    public function test_an_event_guide_adds_that_events_details_and_schedule(): void
    {
        $event = $this->event();
        Cache::put("event:{$event->id}:sessions", [
            ['id' => 1, 'title' => 'Lunch', 'starts_at' => '2026-10-01T12:00:00+00:00', 'track_names' => ['Atrium'], 'session_type' => 'custom', 'speaker_ids' => []],
        ], 3600);

        $response = $this->get(route('event.guide', $event))->assertOk();

        $response->assertSee('New to WCTest? Start here.')
            ->assertSee('At WordCamp Test 2026')
            ->assertSee('Town Hall, Main Street')
            ->assertSee('id="guide-data"', false)
            ->assertSee('Atrium')
            ->assertSee('data-guide-match="lunch"', false)
            // A hostile Code of Conduct value never becomes a link.
            ->assertDontSee('javascript:alert', false);
    }

    public function test_a_hidden_events_guide_is_not_found(): void
    {
        $event = $this->event(['is_visible' => false]);

        $this->get(route('event.guide', $event))->assertNotFound();
    }

    public function test_every_event_screen_links_to_the_guide(): void
    {
        $event = $this->event();

        foreach (['event.home', 'event.my-day', 'event.quest', 'event.contribute', 'event.explore', 'event.camp-card'] as $route) {
            $this->get(route($route, $event))->assertOk()->assertSee(route('event.guide', $event), false);
        }
    }

    public function test_home_offers_the_start_here_card_and_guidance_copy(): void
    {
        $event = $this->event();

        $this->get(route('event.home', $event))
            ->assertOk()
            ->assertSee('New to WordCamp? Start here')
            ->assertSee('id="start-here-dismiss"', false)
            ->assertSee('"moments"', false)
            ->assertSee('Hallway-track time', false);
    }

    public function test_the_picker_points_newcomers_to_the_guide(): void
    {
        $this->get(route('home'))->assertOk()->assertSee(route('guide'), false)->assertSee('First WordCamp? Read this first');
    }

    public function test_every_moment_the_guide_matches_on_is_a_real_key(): void
    {
        foreach (FirstTimerGuide::moments() as $moment) {
            $this->assertNotEmpty($moment['match']);
            $this->assertIsBool($moment['key']);
            $this->assertIsBool($moment['talk']);
        }
    }

    public function test_a_missing_page_gets_a_friendly_branded_404(): void
    {
        $this->get('/event/no-such-wordcamp')
            ->assertNotFound()
            ->assertSee("We couldn't find that page")
            ->assertSee('Back to all WordCamps');
    }

    public function test_a_fresh_install_has_the_default_things_to_do(): void
    {
        $this->assertSame(8, Quest::whereNull('event_id')->where('source', 'default')->count());
    }

    public function test_the_demo_seeder_builds_a_complete_walkthrough_event(): void
    {
        $this->seed(DemoEventSeeder::class);
        $event = Event::where('slug', DemoEventSeeder::SLUG)->firstOrFail();

        $sessions = Cache::get("event:{$event->id}:sessions");
        $this->assertGreaterThan(10, count($sessions));
        $this->assertStringNotContainsString('<script', json_encode($sessions));
        $this->assertSame(20, $event->attendeeRoster()->count());

        foreach (['event.home', 'event.my-day', 'event.guide', 'event.explore'] as $route) {
            $this->get(route($route, $event))->assertOk();
        }

        // Re-running resets rather than duplicating.
        $this->seed(DemoEventSeeder::class);
        $this->assertSame(1, Event::where('slug', DemoEventSeeder::SLUG)->count());
    }
}
