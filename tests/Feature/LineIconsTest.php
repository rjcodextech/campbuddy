<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Support\FirstTimerGuide;
use App\Support\LineIcons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Line icons instead of emoji (App\Support\LineIcons): every icon a page
 * points at is in that page's sprite, and the places that used emoji don't
 * any more.
 */
class LineIconsTest extends TestCase
{
    use RefreshDatabase;

    /** Emoji and pictographs; the plain symbols the UI uses as text (✓ ✕ ★ ❚) are outside these ranges. */
    private const EMOJI = '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{26FF}\x{23F0}-\x{23FA}\x{2B50}\x{267F}\x{2728}]/u';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
    }

    private function event(): Event
    {
        return Event::create([
            'slug' => 'wc-icons',
            'display_name' => 'WordCamp Icons 2026',
            'source_site_url' => 'https://icons.wordcamp.org/2026',
            'starts_on' => now()->addDays(5)->toDateString(),
            'ends_on' => now()->addDays(6)->toDateString(),
            'status' => 'active',
            'is_visible' => true,
            'info' => [
                'venue' => 'Town Hall', 'wifi' => 'Network: WC, password: hello', 'registration_info' => 'At the desk.',
                'contributor_day_location' => 'Room 2', 'social_event_info' => 'At 7pm.', 'emergency_contact' => '+91 98765 43210',
                'code_of_conduct_url' => 'https://icons.wordcamp.org/2026/code-of-conduct/', 'nearby_venue_info' => 'Hotels nearby.',
                'important_links' => "https://icons.wordcamp.org/2026/\nhttps://icons.wordcamp.org/2026/tickets/",
            ],
        ]);
    }

    /** @return array<string, string> page => HTML */
    private function pages(): array
    {
        $event = $this->event();

        return [
            'picker' => $this->get('/')->assertOk()->getContent(),
            'guide' => $this->get(route('guide'))->assertOk()->getContent(),
            'event guide' => $this->get(route('event.guide', $event))->assertOk()->getContent(),
            'home' => $this->get(route('event.home', $event))->assertOk()->getContent(),
            'explore' => $this->get(route('event.explore', $event))->assertOk()->getContent(),
            'quest' => $this->get(route('event.quest', $event))->assertOk()->getContent(),
            'contribute' => $this->get(route('event.contribute', $event))->assertOk()->getContent(),
        ];
    }

    public function test_every_icon_a_page_uses_is_in_its_sprite(): void
    {
        foreach ($this->pages() as $page => $html) {
            preg_match_all('/href="#li-([a-z-]+)"/', $html, $used);
            $this->assertNotEmpty($used[1], "{$page} shows line icons");

            foreach (array_unique($used[1]) as $name) {
                $this->assertStringContainsString('<symbol id="li-'.$name.'"', $html, "{$page}: #li-{$name} is in the sprite");
            }
        }
    }

    public function test_the_places_that_had_emoji_show_line_icons(): void
    {
        $boxes = ['about-who__emoji', 'useful-link__icon', 'guide-step__icon', 'guide-tip__icon', 'action-card__icon', 'guide-hero__meta', 'event-card__location', 'guide-section__title'];

        foreach ($this->pages() as $page => $html) {
            foreach ($boxes as $box) {
                preg_match_all('/class="[^"]*\b'.preg_quote($box, '/').'\b[^"]*"[^>]*>(.*?)<\/(span|p|ul|h2)>/su', $html, $found);

                foreach ($found[1] as $inside) {
                    $this->assertDoesNotMatchRegularExpression(self::EMOJI, strip_tags($inside), "{$page}: .{$box} has no emoji left");
                }
            }
        }
    }

    public function test_the_guide_data_names_real_icons(): void
    {
        foreach ([...FirstTimerGuide::day(), ...FirstTimerGuide::tips(), ...FirstTimerGuide::students()] as $item) {
            $this->assertTrue(LineIcons::has($item['icon']), "\"{$item['icon']}\" ({$item['title']}) is a line icon");
        }
    }

    public function test_quest_and_contribute_name_real_icons(): void
    {
        foreach (['quest.js' => "/icon: '([a-z-]+)'/", 'contrib-teams.js' => "/icon: '([a-z-]+)'/"] as $file => $pattern) {
            preg_match_all($pattern, file_get_contents(resource_path("js/attendee/{$file}")), $names);
            $this->assertNotEmpty($names[1], "{$file} names icons");

            foreach ($names[1] as $name) {
                $this->assertTrue(LineIcons::has($name), "{$file}: \"{$name}\" is a line icon");
            }
        }

        $this->assertTrue(LineIcons::has('sparkles'), 'the fallback exists');
    }
}
