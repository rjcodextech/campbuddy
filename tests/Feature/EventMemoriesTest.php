<?php

namespace Tests\Feature;

use App\Jobs\SendMemoriesPushJob;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\EventManagerChange;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\DataVersion;
use App\Support\EventMemories;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Memories (10 Oct 2026): an admin or the event's managers add the photo album
 * and the WordPress.tv link, attendees see them on Home and on the Memories
 * page (which stays open once the event is archived), and each link's push
 * goes once to the phones with reminders on.
 */
class EventMemoriesTest extends TestCase
{
    use RefreshDatabase;

    // Throwaway keys (same as SessionRemindersTest).
    private const VAPID_PUBLIC = 'BM_hD8Ox0zWMrOtvvXHVl2PpzSL_jvcW7c5ixy1SXlwKASmhCr0ayk6GQmfqDcWyKx9YYWkih-SM7mC9kTYt_fo';

    private const VAPID_PRIVATE = 'AULfVYPjtSQFR4LPspAb4868ogRmAGiZc7asCBe5LvU';

    private const P256DH = 'BB7uFWhMjz8fkzCOi2BiE0LeMo_tsF561P9bUp-9FYMrRzkCrLWKHkpP5J0JDDx588DToAnhGE5AXduw_EeVH0c';

    private const AUTH = 'aYKRF_SWLrZhFgJMZ5Gixw';

    private const ALBUM = 'https://photos.app.goo.gl/abc123';

    private const TV = 'https://wordpress.tv/event/wordcamp-memories-2026/';

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-10T06:30:00Z'));
        $this->event = Event::withoutEvents(fn () => Event::create([
            'slug' => 'wc-memories', 'display_name' => 'WordCamp Memories', 'source_site_url' => 'https://memories.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true, 'timezone' => 'Asia/Kolkata',
            'starts_on' => '2026-10-07', 'ends_on' => '2026-10-07',
        ]));
    }

    private function subscribe(string $device, ?Event $event = null): void
    {
        PushSubscription::create(['event_id' => ($event ?? $this->event)->id, 'device_id' => $device, 'endpoint' => 'https://push.example.test/send/'.$device, 'p256dh_key' => self::P256DH, 'auth_key' => self::AUTH]);
    }

    private function pushService(array &$history): void
    {
        $stack = HandlerStack::create(fn () => new FulfilledPromise(new Response(201)));
        $stack->push(Middleware::history($history));
        $this->app->instance(Client::class, new Client(['handler' => $stack]));
        config(['services.vapid' => ['subject' => 'mailto:test@example.com', 'public_key' => self::VAPID_PUBLIC, 'private_key' => self::VAPID_PRIVATE]]);
    }

    public function test_nothing_shows_until_a_link_is_added(): void
    {
        $this->get('/event/wc-memories')->assertOk()->assertDontSee('memories-heading');
        $this->get('/event/wc-memories/memories')->assertOk()->assertSee('Nothing here yet')->assertSee('<symbol id="li-x"', false);
    }

    public function test_admin_saves_the_links_and_attendees_see_them_on_home_and_memories(): void
    {
        $before = DataVersion::for($this->event);
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.events.memories', $this->event))->assertOk()->assertSee('Photo album')->assertSee('WordPress.tv speaker sessions');

        $this->post(route('admin.events.memories.store', $this->event), ['photo_album_url' => self::ALBUM, 'wordpress_tv_url' => self::TV])
            ->assertRedirect(route('admin.events.memories', $this->event));

        $event = $this->event->fresh();
        $this->assertSame(self::ALBUM, $event->photo_album_url);
        $this->assertSame(self::TV, $event->wordpress_tv_url);
        // Open apps refresh to pick the card up.
        $this->assertNotSame($before, DataVersion::for($event));
        // No phone has reminders on yet: no Send button (it would use up the one send).
        $this->get(route('admin.events.memories', $this->event))->assertSee('No phone has reminders on for this event yet.')->assertDontSee('Send to 0 phones');

        $this->get('/event/wc-memories')->assertOk()->assertSee('memories-heading', false)->assertSee(self::ALBUM, false)->assertSee(self::TV, false);
        $this->get('/event/wc-memories/memories')->assertOk()->assertSee('Speaker sessions on WordPress.tv')->assertSee(self::ALBUM, false);

        // Emptying a field removes that link.
        $this->post(route('admin.events.memories.store', $this->event), ['photo_album_url' => '', 'wordpress_tv_url' => self::TV]);
        $this->assertNull($this->event->fresh()->photo_album_url);
        $this->get('/event/wc-memories')->assertDontSee(self::ALBUM, false)->assertSee(self::TV, false);
    }

    public function test_only_safe_links_are_accepted(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.events.memories.store', $this->event), ['photo_album_url' => 'javascript:alert(1)'])->assertSessionHasErrors('photo_album_url');
        $this->post(route('admin.events.memories.store', $this->event), ['photo_album_url' => 'http://photos.example.com/x'])->assertSessionHasErrors('photo_album_url');
        $this->post(route('admin.events.memories.store', $this->event), ['wordpress_tv_url' => 'https://youtube.com/watch?v=1'])->assertSessionHasErrors('wordpress_tv_url');
        $this->post(route('admin.events.memories.store', $this->event), ['wordpress_tv_url' => 'https://evil-wordpress.tv/x'])->assertSessionHasErrors('wordpress_tv_url');
        $this->assertNull($this->event->fresh()->photo_album_url);
        $this->assertNull($this->event->fresh()->wordpress_tv_url);
    }

    public function test_each_push_goes_once_to_this_events_phones_and_opens_the_memories_page(): void
    {
        $history = [];
        $this->pushService($history);
        $this->subscribe('a');
        $this->subscribe('b');
        $other = Event::withoutEvents(fn () => Event::create(['slug' => 'wc-other', 'display_name' => 'Other', 'source_site_url' => 'https://other.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]));
        $this->subscribe('c', $other);

        $this->event->forceFill(['photo_album_url' => self::ALBUM])->save();
        $this->actingAs(User::factory()->create());
        // The job runs on the spot here; live it waits for the next queue drain (a minute at most).
        config(['queue.default' => 'sync']);

        // No link yet: nothing to send.
        $this->post(route('admin.events.memories.notify', [$this->event, 'videos']))->assertSessionHasErrors('notify');

        $this->post(route('admin.events.memories.notify', [$this->event, 'photos']))->assertRedirect()->assertSessionHas('status');
        $this->assertCount(2, $history);
        $this->assertSame(['https://push.example.test/send/a', 'https://push.example.test/send/b'], collect($history)->map(fn ($h) => (string) $h['request']->getUri())->sort()->values()->all());
        $this->assertNotNull($this->event->fresh()->photo_album_notified_at);

        // A second press (double click, a second organizer): nothing more.
        $this->post(route('admin.events.memories.notify', [$this->event, 'photos']))->assertSessionHasErrors('notify');
        $this->assertCount(2, $history);
        $this->get(route('admin.events.memories', $this->event))->assertSee('Sent ');

        // The WordPress.tv one is its own, separate notification.
        $this->event->forceFill(['wordpress_tv_url' => self::TV])->save();
        $this->post(route('admin.events.memories.notify', [$this->event, 'videos']))->assertRedirect();
        $this->assertCount(4, $history);

        $this->post('/admin/events/'.$this->event->id.'/memories/notify/nope')->assertNotFound();
    }

    public function test_notify_queues_one_job_for_that_kind_and_marks_it_sent(): void
    {
        Queue::fake();
        $this->event->forceFill(['wordpress_tv_url' => self::TV])->save();

        // No phone to send to: refused, and the one send is not used up.
        try {
            EventMemories::notify($this->event, 'videos');
            $this->fail('sent to nobody');
        } catch (\Illuminate\Validation\ValidationException) {
        }
        $this->assertNull($this->event->fresh()->wordpress_tv_notified_at);
        Queue::assertNothingPushed();

        $this->subscribe('a');
        EventMemories::notify($this->event, 'videos');

        Queue::assertPushed(SendMemoriesPushJob::class, fn ($job) => $job->eventId === $this->event->id && $job->kind === 'videos');
        $this->assertSame(1, Event::whereNotNull('wordpress_tv_notified_at')->count());
    }

    public function test_the_memories_page_stays_open_once_archived_but_not_for_a_draft_or_hidden_event(): void
    {
        $this->event->forceFill(['status' => 'archived', 'wordpress_tv_url' => self::TV])->save();

        $this->get('/event/wc-memories')->assertNotFound();
        $this->get('/event/wc-memories/memories')->assertOk()->assertSee(self::TV, false)->assertSee('Find your next WordCamp');

        $this->event->forceFill(['is_visible' => false])->save();
        $this->get('/event/wc-memories/memories')->assertNotFound();

        $this->event->forceFill(['is_visible' => true, 'status' => 'draft'])->save();
        $this->get('/event/wc-memories/memories')->assertNotFound();
    }

    public function test_a_manager_adds_links_and_sends_for_their_own_event_only_and_is_logged(): void
    {
        Queue::fake();
        $manager = EventManager::factory()->create(['is_active' => true]);
        $manager->events()->attach($this->event->id);
        $other = Event::withoutEvents(fn () => Event::create(['slug' => 'wc-not-theirs', 'display_name' => 'Not Theirs', 'source_site_url' => 'https://nottheirs.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]));
        Auth::guard('manager')->setUser($manager);
        $this->subscribe('a');

        $this->get(route('manager.events.memories', $this->event->id))->assertOk()->assertSee('Photo album');
        $this->post(route('manager.events.memories.store', $this->event->id), ['photo_album_url' => self::ALBUM])->assertRedirect();
        $this->assertSame(self::ALBUM, $this->event->fresh()->photo_album_url);
        $this->assertStringContainsString('Photo album set to', EventManagerChange::latest('id')->first()->summary);

        $this->post(route('manager.events.memories.notify', [$this->event->id, 'photos']))->assertRedirect();
        Queue::assertPushed(SendMemoriesPushJob::class, 1);
        $this->assertSame('notified', EventManagerChange::latest('id')->first()->action);

        $this->get(route('manager.events.memories', $other->id))->assertNotFound();
        $this->post(route('manager.events.memories.store', $other->id), ['photo_album_url' => self::ALBUM])->assertNotFound();
        $this->post(route('manager.events.memories.notify', [$other->id, 'photos']))->assertNotFound();
        $this->assertNull($other->fresh()->photo_album_url);
    }

    public function test_a_finished_wordcamps_picker_card_and_thank_you_card_show_its_memories(): void
    {
        // 10 Oct: the event (7 Oct) is over and listed as Completed.
        $this->event->forceFill(['photo_album_url' => self::ALBUM, 'wordpress_tv_url' => self::TV])->save();

        $this->get('/')->assertOk()
            ->assertSee('event-card__memories', false)
            ->assertSee(self::ALBUM, false)->assertSee('Talks on WordPress.tv')
            ->assertSee('Watch the talks on WordPress.tv');

        $this->get('/event/wc-memories')->assertOk()->assertSee('See the photos');

        // No links, no row.
        $this->event->forceFill(['photo_album_url' => null, 'wordpress_tv_url' => null])->save();
        $this->get('/')->assertOk()->assertDontSee('event-card__memories', false)->assertDontSee('Relive it');
    }
}
