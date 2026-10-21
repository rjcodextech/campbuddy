<?php

namespace Tests\Feature;

use App\Jobs\SendBroadcastPushJob;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\NativePushToken;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\AppOrganizer;
use App\Support\EventBroadcasts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Live announcements (App\Support\EventBroadcasts): who they reach (devices that
 * opened the event from three days before it), the banner, the rules on
 * sending, and the three ways to send (app API, admin panel, manager panel).
 */
class EventBroadcastsTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->event = Event::withoutEvents(fn () => Event::create([
            'slug' => 'wc-live', 'display_name' => 'WordCamp Live', 'source_site_url' => 'https://live.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true,
            'starts_on' => now()->addDay()->toDateString(), 'ends_on' => now()->addDays(2)->toDateString(), 'timezone' => 'Asia/Kolkata',
        ]));
    }

    private function sub(string $device, $updated): PushSubscription
    {
        $s = PushSubscription::create(['event_id' => $this->event->id, 'device_id' => $device, 'endpoint' => "https://push.example.com/{$device}", 'p256dh_key' => 'k', 'auth_key' => 'a']);
        DB::table('push_subscriptions')->where('id', $s->id)->update(['created_at' => $updated, 'updated_at' => $updated]);

        return $s;
    }

    public function test_a_device_says_it_has_the_event_open_at_most_once_an_hour(): void
    {
        $this->postJson('/api/v1/events/wc-live/seen', ['device_id' => 'device-aaaa-1111', 'platform' => 'pwa'])->assertNoContent();
        $first = DB::table('event_device_visits')->first();
        $this->travel(10)->minutes();
        $this->postJson('/api/v1/events/wc-live/seen', ['device_id' => 'device-aaaa-1111'])->assertNoContent();
        $this->assertSame($first->last_seen_at, DB::table('event_device_visits')->value('last_seen_at'));
        $this->travel(2)->hours();
        $this->postJson('/api/v1/events/wc-live/seen', ['device_id' => 'device-aaaa-1111'])->assertNoContent();
        $this->assertNotSame($first->last_seen_at, DB::table('event_device_visits')->value('last_seen_at'));
        $this->assertSame(1, DB::table('event_device_visits')->count());

        $this->postJson('/api/v1/events/wc-live/seen', ['device_id' => 'bad id!'])->assertUnprocessable();
    }

    public function test_the_push_reaches_devices_seen_since_three_days_before_the_event(): void
    {
        $old = now()->subDays(20);
        $this->sub('seen-recently', $old);       // opened the event lately (a visit), subscribed long ago
        $this->sub('subscribed-lately', now()); // turned notifications on in the window
        $this->sub('long-gone', $old);           // neither
        NativePushToken::create(['event_id' => $this->event->id, 'device_id' => 'phone-seen', 'platform' => 'android', 'token' => 'tok-1']);
        DB::table('native_push_tokens')->update(['created_at' => $old, 'updated_at' => $old]);
        EventBroadcasts::seen($this->event, 'seen-recently', 'web');
        EventBroadcasts::seen($this->event, 'phone-seen', 'android');
        DB::table('event_device_visits')->insert(['event_id' => $this->event->id, 'device_id' => 'long-gone', 'platform' => 'web', 'first_seen_at' => $old, 'last_seen_at' => $old]);

        $this->assertSame(['web' => 2, 'app' => 1, 'seen' => 2], EventBroadcasts::reach($this->event));
    }

    public function test_presets_fill_in_where_and_the_session_and_own_words_win(): void
    {
        $this->assertSame(['title' => '☕ Tea break!', 'body' => 'Tea and snacks are out in the lobby. Back to the sessions in 15 minutes.', 'tab' => null], EventBroadcasts::compose('tea', null, null, 'the lobby', null, null));
        $this->assertSame('⏰ Keynote: Ten years starts in 5 minutes', EventBroadcasts::compose('session', '', '', null, 'Keynote: Ten years', null)['title']);
        $this->assertSame('Collect your badge and swag at the registration desk in the lobby.', EventBroadcasts::compose('registration', null, null, 'the lobby', null, null)['body']);
        $this->assertSame('🔔 Sessions are starting again', EventBroadcasts::compose('resume', null, null, null, null, null)['title']);
        $this->assertSame(['title' => 'Bus leaves', 'body' => 'Gate 2, 6 pm', 'tab' => 'my-day'], EventBroadcasts::compose('custom', 'Bus leaves', 'Gate 2, 6 pm', null, null, 'my-day'));
    }

    public function test_a_manager_sends_from_the_app_and_everyone_sees_the_banner_for_half_an_hour(): void
    {
        Queue::fake();
        $manager = EventManager::factory()->create();
        $manager->events()->attach($this->event->id);
        $headers = ['Authorization' => 'Bearer '.AppOrganizer::issue('manager', $manager->id, 'phone')['token']];

        $data = $this->getJson("/api/v1/app/organizer/events/{$this->event->id}/broadcasts", $headers)->assertOk()->json('data');
        $this->assertContains('lunch', collect($data['presets'])->pluck('key')->all());

        $this->postJson("/api/v1/app/organizer/events/{$this->event->id}/broadcasts", ['kind' => 'lunch', 'where' => 'the dining hall'], $headers)->assertCreated();
        Queue::assertPushed(SendBroadcastPushJob::class);
        $this->assertDatabaseHas('event_manager_changes', ['section' => 'notifications', 'action' => 'added']);

        $this->getJson('/api/v1/events/wc-live/live-notice')->assertOk()->assertJsonPath('data.title', '🍽️ Lunch is served')->assertJsonPath('data.icon', 'utensils')->assertJsonPath('data.tone', 'break')
            ->assertJsonPath('data.body', 'Lunch is ready in the dining hall. Sessions start again after the break.');

        // A second one straight away is refused (a double tap, two organizers at once).
        $this->postJson("/api/v1/app/organizer/events/{$this->event->id}/broadcasts", ['kind' => 'tea'], $headers)->assertUnprocessable();
        // A custom one needs words.
        $this->travel(2)->minutes();
        $this->postJson("/api/v1/app/organizer/events/{$this->event->id}/broadcasts", ['kind' => 'custom'], $headers)->assertUnprocessable();

        $this->travel(31)->minutes();
        Cache::flush();
        $this->getJson('/api/v1/events/wc-live/live-notice')->assertOk()->assertJsonPath('data', null);
    }

    public function test_admin_and_manager_send_from_the_web_panel(): void
    {
        Queue::fake();
        $admin = User::factory()->create();
        $this->actingAs($admin)->get(route('admin.events.notifications', $this->event))->assertOk()->assertSee('Send a live announcement')
            ->assertSee('data-preset-title="🍽️ Lunch is served"', false)->assertSee('fills in its ready-made words');
        $this->actingAs($admin)->post(route('admin.events.broadcasts.store', $this->event), ['kind' => 'opening'])->assertRedirect();
        $this->assertSame('🎤 Opening remarks are starting', DB::table('event_broadcasts')->value('title'));

        $manager = EventManager::factory()->create();
        $manager->events()->attach($this->event->id);
        $this->travel(2)->minutes();
        $this->actingAs($manager, 'manager');
        \Illuminate\Support\Facades\Auth::shouldUse('web');
        \Illuminate\Support\Facades\Auth::guard('manager')->setUser($manager);
        $this->post(route('manager.events.broadcasts.store', $this->event), ['kind' => 'closing'])->assertRedirect();
        $this->assertSame(2, DB::table('event_broadcasts')->count());
    }

    public function test_the_job_sends_and_counts(): void
    {
        $this->sub('seen-recently', now());
        $id = DB::table('event_broadcasts')->insertGetId(['event_id' => $this->event->id, 'kind' => 'tea', 'title' => 'Tea', 'body' => 'Now', 'sent_by' => 'admin: A', 'show_until' => now()->addMinutes(30), 'created_at' => now(), 'updated_at' => now()]);
        $job = new class($id) extends SendBroadcastPushJob {
            public array $queued = [];

            protected function webPush(): \Minishlink\WebPush\WebPush
            {
                $outer = $this;

                return new class($outer) extends \Minishlink\WebPush\WebPush {
                    public function __construct(private $outer) {}

                    public function queueNotification(\Minishlink\WebPush\SubscriptionInterface $s, ?string $payload = null, array $options = [], array $auth = []): void
                    {
                        $this->outer->queued[] = json_decode($payload, true);
                    }

                    public function flush(?int $batchSize = null): \Generator
                    {
                        // The push service took one of the queued messages.
                        yield new \Minishlink\WebPush\MessageSentReport(new \GuzzleHttp\Psr7\Request('POST', 'https://push.example/1'));
                    }
                };
            }
        };
        $job->handle();

        $this->assertSame('Tea', $job->queued[0]['title']);
        $this->assertStringContainsString('/event/wc-live', $job->queued[0]['url']);
        $this->assertSame(1, DB::table('event_broadcasts')->value('recipients'));
        $this->assertTrue(Schema::hasTable('event_device_visits'));

        // Never twice (a queue retry), and never late: half an hour on, the moment has passed.
        $again = new class($id) extends SendBroadcastPushJob {
            protected function webPush(): \Minishlink\WebPush\WebPush
            {
                throw new \LogicException('must not push again');
            }
        };
        $again->handle();
        DB::table('event_broadcasts')->where('id', $id)->update(['recipients' => 0, 'show_until' => now()->subMinute()]);
        $again->handle();
        $this->assertSame(1, (new SendBroadcastPushJob($id))->tries);
    }

    public function test_turning_notifications_off_in_the_app_forgets_the_visit(): void
    {
        NativePushToken::create(['event_id' => $this->event->id, 'device_id' => 'phone-12345678', 'platform' => 'android', 'token' => 'tok-9']);
        EventBroadcasts::seen($this->event, 'phone-12345678', 'android');
        $this->assertSame(1, DB::table('event_device_visits')->count());

        $this->deleteJson('/api/v1/events/wc-live/push/native-subscribe', ['device_id' => 'phone-12345678'])->assertNoContent();
        $this->assertSame(0, DB::table('event_device_visits')->count());
    }
}
