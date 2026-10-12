<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureEventManager;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\EventNotifications;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Event → Notifications (admin and managers): every push about the event,
 * what it says, when it goes, and — once sent — when and to how many phones.
 */
class EventNotificationsTabTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-12 12:00', 'Asia/Kolkata'));
        $this->event = Event::withoutEvents(fn () => Event::create([
            'slug' => 'wc-notes', 'display_name' => 'WordCamp Notes', 'source_site_url' => 'https://notes.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true, 'starts_on' => '2026-10-02', 'ends_on' => '2026-10-02', 'timezone' => 'Asia/Kolkata',
        ]));
        foreach (['a', 'b', 'c'] as $d) {
            PushSubscription::create(['event_id' => $this->event->id, 'device_id' => $d, 'endpoint' => "https://push.example.test/{$d}", 'p256dh_key' => 'k', 'auth_key' => 'a']);
        }
    }

    public function test_it_says_what_went_what_is_scheduled_and_what_waits_for_a_link(): void
    {
        $this->event->forceFill([
            'thank_you_sent_at' => '2026-10-05 06:00:00',
            'save_data_sent_at' => '2026-10-09 06:00:00',
            'push_log' => ['thank-you' => ['at' => '2026-10-05T06:00:00+00:00', 'phones' => 3], 'save-data' => ['at' => '2026-10-09T06:00:00+00:00', 'phones' => 2]],
            'photo_album_url' => 'https://photos.app.goo.gl/x',
        ])->save();

        $rows = collect(EventNotifications::rows($this->event->fresh()))->keyBy('key');
        $this->assertSame(['reminders', 'thank-you', 'save-data', 'move-data', 'photos', 'videos'], $rows->keys()->all());
        $this->assertSame('sent', $rows['thank-you']['state']);
        $this->assertStringContainsString('to 3 phones', $rows['thank-you']['status']);
        $this->assertStringContainsString('to 2 phones', $rows['save-data']['status']);
        $this->assertSame('scheduled', $rows['move-data']['state']);
        $this->assertStringContainsString('Goes on Sat 17 Oct', $rows['move-data']['status']);
        $this->assertSame('ready', $rows['photos']['state']);
        $this->assertSame('waiting', $rows['videos']['state']);

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.events.notifications', $this->event))->assertOk()
            ->assertSee('Notifications')->assertSee('3 phones that turned on reminders')
            ->assertSee('Take it with you')->assertSee('Keep your WordCamp')->assertSee('Ready to send')
            ->assertSee(route('admin.events.memories', $this->event), false);
    }

    public function test_an_event_past_its_month_says_the_after_event_pushes_were_not_sent(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00', 'Asia/Kolkata'));
        $rows = collect(EventNotifications::rows($this->event->fresh()))->keyBy('key');
        $this->assertSame('missed', $rows['move-data']['state']);
    }

    public function test_a_manager_sees_it_for_their_own_events_only(): void
    {
        $manager = EventManager::create(['name' => 'Mona', 'email' => 'mona@example.test', 'phone' => '+919800000000', 'password' => 'secret-password-1', 'is_active' => true]);
        $manager->events()->sync([$this->event->id]);
        $other = Event::create(['slug' => 'wc-other', 'display_name' => 'Other', 'source_site_url' => 'https://other.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);
        $this->actingAs($manager, EnsureEventManager::GUARD);

        $this->get(route('manager.events.notifications', $this->event->id))->assertOk()->assertSee('Session reminders')
            ->assertSee(route('manager.events.memories', $this->event), false);
        $this->get(route('manager.events.notifications', $other->id))->assertNotFound();
    }
}
