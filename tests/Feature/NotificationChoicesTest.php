<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * "Your choices" (attendee/partials/notification-choices.blade.php, filled by notification-choices.js):
 * next to "Your data" on Explore and the Camp Card, the way back to notifications for someone who said
 * no, and the analytics choice, which is never tied to a feature.
 */
class NotificationChoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake();
    }

    public function test_the_card_sits_after_your_data_on_explore_and_the_camp_card(): void
    {
        $event = Event::create([
            'slug' => 'jaipur', 'display_name' => 'WordCamp Jaipur 2026', 'source_site_url' => 'https://jaipur.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true, 'country_code' => 'IN', 'starts_on' => '2026-11-21', 'ends_on' => '2026-11-22',
        ]);

        foreach (['event.explore', 'event.camp-card'] as $route) {
            $html = $this->get(route($route, $event))->assertOk()->getContent();
            $this->assertStringContainsString('data-notification-choices', $html, $route);
            $this->assertLessThan(strpos($html, 'id="your-choices"'), strpos($html, 'id="data-controls"'), "{$route}: after Your data");
            foreach (['Your choices', 'Talk reminders', 'Live updates from the organizers', 'data-choice-turn-on', 'Saying no to analytics changes nothing else in CampBuddy.'] as $text) {
                $this->assertStringContainsString($text, $html, "{$route}: {$text}");
            }
            // The analytics rows stay hidden unless the analytics partial runs (it shows them only where Google Analytics can).
            $this->assertMatchesRegularExpression('/<li data-analytics-choice hidden>/', $html);
        }
    }
}
