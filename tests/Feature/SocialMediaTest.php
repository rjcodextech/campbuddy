<?php

namespace Tests\Feature;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\EventManagerChange;
use App\Models\User;
use App\Support\EventData;
use App\Support\SocialKit;
use App\Support\SocialPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin / manager → Social media: captions from the event's facts, brand
 * colours, people for the cards, and Publish through an allowed webhook only.
 */
class SocialMediaTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'slug' => 'wordcamp-rajasthan-2026', 'display_name' => 'WordCamp Rajasthan 2026', 'short_name' => '#WCRajasthan',
            'source_site_url' => 'https://rajasthan.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true,
            'timezone' => 'Asia/Kolkata', 'starts_on' => '2026-10-03', 'ends_on' => '2026-10-04',
            'info' => ['venue' => 'Rajasthan International Centre — JLN Marg, Jaipur'],
        ]);
    }

    public function test_captions_say_the_events_facts(): void
    {
        EventData::put($this->event->id, 'sessions', [['id' => 1, 'title' => 'A'], ['id' => 2, 'title' => 'B']]);
        EventData::put($this->event->id, 'speakers', [['id' => 7, 'name' => 'Rahul']]);

        $this->assertSame('3–4 Oct 2026', SocialKit::dates($this->event));
        $this->assertSame('Rajasthan International Centre', SocialKit::venue($this->event));
        $this->assertSame('#WordCamp #WordPress #WCRajasthan #WCRajasthan2026', SocialKit::hashtags($this->event));

        $posts = collect(SocialKit::posts($this->event, CarbonImmutable::parse('2026-09-21 10:00', 'Asia/Kolkata')))->keyBy('key');
        $this->assertSame('12 days to go', $posts['countdown']['headline']);
        $this->assertSame('2 sessions · 1 speakers', $posts['schedule']['line']);
        $this->assertStringContainsString('https://rajasthan.wordcamp.org/2026', $posts['save-the-date']['caption']);
        $this->assertStringContainsString('utm_source=social', $posts['app']['caption']);
    }

    public function test_more_posts_get_the_schedule_speakers_sponsors_and_tables(): void
    {
        EventData::put($this->event->id, 'speakers', [['id' => 7, 'name' => 'Rahul', 'avatar_url' => 'https://secure.gravatar.com/avatar/bbb?s=96&d=mm']]);
        EventData::put($this->event->id, 'sponsors', [['id' => 1, 'name' => 'Hosting.com', 'logo_url' => 'https://rajasthan.wordcamp.org/2026/files/logo.png', 'tier_names' => ['Gold']]]);
        EventData::put($this->event->id, 'sessions', [
            ['id' => 1, 'title' => 'Opening', 'starts_at' => '2026-10-03T03:30:00+00:00', 'speaker_ids' => [], 'track_names' => ['Track 1']],
            ['id' => 2, 'title' => 'Blocks', 'starts_at' => '2026-10-03T05:00:00+00:00', 'speaker_ids' => [7], 'track_names' => ['Track 1']],
            ['id' => 3, 'title' => 'Day two', 'starts_at' => '2026-10-04T04:00:00+00:00', 'speaker_ids' => [7], 'track_names' => []],
        ]);
        \App\Models\ContributorTable::create(['event_id' => $this->event->id, 'team' => 'polyglots', 'floor' => '2']);

        $kit = SocialKit::data($this->event);

        $this->assertSame(['save-the-date', 'countdown', 'schedule', 'app', 'thank-you', 'speakers', 'sponsors', 'today', 'spotlight', 'contributor', 'volunteers'], array_column($kit['posts'], 'key'));
        $this->assertSame([['key' => '2026-10-03', 'label' => 'Sat 3 Oct'], ['key' => '2026-10-04', 'label' => 'Sun 4 Oct']], $kit['schedule']['days']);
        $blocks = collect($kit['schedule']['sessions'])->firstWhere('id', 2);
        $this->assertSame('10:30 AM', $blocks['time'], 'the venue time, not the server time');
        $this->assertSame(['Rahul'], $blocks['speakers']);
        $this->assertStringContainsString('s=600', $blocks['photos'][0]);
        $this->assertSame(2, $kit['schedule']['next'], 'the next session with a speaker');
        $this->assertSame('Hosting.com', $kit['sponsors'][0]['name']);
        $this->assertSame('Polyglots', $kit['tables'][0]['name']);
        $this->assertSame('WordCamp Rajasthan', $kit['short']);

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.events.social', $this->event))->assertOk()
            ->assertSee('Story 1080 × 1920', false)->assertSee('data-social-style="ticket"', false)
            ->assertSee('data-people-design="polaroid"', false)->assertSee('Sat 3 Oct');
    }

    public function test_sponsor_logos_come_through_an_allowlisted_proxy(): void
    {
        Storage::fake('local');
        Http::fake([
            'rajasthan.wordcamp.org/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png']),
            'evil.example/*' => Http::response('x', 200, ['Content-Type' => 'image/png']),
        ]);
        $this->actingAs(User::factory()->create());
        $url = fn ($u) => route('admin.events.social.image', $this->event).'?u='.urlencode($u);

        $this->get($url('https://rajasthan.wordcamp.org/2026/files/logo.png'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($url('https://rajasthan.wordcamp.org/2026/files/logo.png'))->assertOk();
        Http::assertSentCount(1);
        $this->get($url('https://evil.example/a.png'))->assertNotFound();
        $this->get($url('http://127.0.0.1/a.png'))->assertNotFound();
        $this->get($url('file:///etc/passwd'))->assertNotFound();
    }

    public function test_people_cards_list_only_people_with_a_role(): void
    {
        $rahul = AttendeeRoster::create(['event_id' => $this->event->id, 'name' => 'Rahul', 'links' => [], 'gravatar_url' => 'https://secure.gravatar.com/avatar/bbb222bbb222bbb222bbb222bbb222bb?s=96', 'content_hash' => 'r', 'is_suppressed' => false]);
        AttendeeRoster::create(['event_id' => $this->event->id, 'name' => 'Plain', 'links' => [], 'content_hash' => 'p', 'is_suppressed' => false]);
        EventData::put($this->event->id, 'speakers', [['id' => 7, 'name' => 'Rahul', 'avatar_url' => 'https://secure.gravatar.com/avatar/bbb222bbb222bbb222bbb222bbb222bb']]);
        EventData::put($this->event->id, 'sessions', [['id' => 1, 'title' => 'Blocks', 'speaker_ids' => [7]]]);

        $people = SocialKit::people($this->event);

        $this->assertCount(1, $people);
        $this->assertSame($rahul->id, $people[0]['id']);
        $this->assertStringContainsString('s=600', $people[0]['photo'], 'a sharper photo for print');
        $this->assertStringContainsString('Meet our speaker Rahul', $people[0]['caption']);
    }

    public function test_the_page_renders_and_colours_are_checked(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.events.social', $this->event))->assertOk()
            ->assertSee('Event posts')->assertSee('People cards')->assertSee('Brand colours')->assertSee('One-click Publish')
            ->assertSee('data-qr-kit', false)->assertDontSee('data-action="publish"', false);

        $this->post(route('admin.events.social.colors', $this->event), ['primary' => 'red', 'secondary' => '#000000', 'ink' => '#000000', 'paper' => '#ffffff'])->assertSessionHasErrors('primary');
        $this->post(route('admin.events.social.colors', $this->event), ['primary' => '#C43E1E', 'secondary' => '#0c2343', 'ink' => '#231f20', 'paper' => '#fff8ee'])->assertRedirect();
        $this->assertSame('#c43e1e', SocialKit::colors($this->event->fresh())['primary']);
    }

    public function test_only_automation_service_webhooks_are_accepted_and_kept_secret(): void
    {
        $this->assertTrue(SocialPublisher::allowed('https://hook.eu1.make.com/abc123'));
        $this->assertTrue(SocialPublisher::allowed('https://hooks.zapier.com/hooks/catch/1/abc/'));
        $this->assertFalse(SocialPublisher::allowed('http://hook.eu1.make.com/abc'), 'https only');
        $this->assertFalse(SocialPublisher::allowed('https://evil.example/make.com'));
        $this->assertFalse(SocialPublisher::allowed('https://notmake.com/x'));
        $this->assertFalse(SocialPublisher::allowed('https://169.254.169.254/latest'));

        $this->actingAs(User::factory()->create());
        $this->post(route('admin.events.social.webhook', $this->event), ['webhook' => 'https://example.com/hook'])->assertSessionHasErrors('webhook');
        $this->post(route('admin.events.social.webhook', $this->event), ['webhook' => 'https://hook.eu1.make.com/secret-abcd'])->assertRedirect();

        $event = $this->event->fresh();
        $this->assertSame('https://hook.eu1.make.com/secret-abcd', $event->social_webhook);
        $this->assertArrayNotHasKey('social_webhook', $event->toArray());
        $this->assertStringNotContainsString('secret-abcd', (string) \DB::table('events')->where('id', $event->id)->value('social_webhook'), 'stored encrypted');

        $this->get(route('admin.events.social', $this->event))->assertOk()->assertSee('data-action="publish"', false)->assertDontSee('secret-abcd');
    }

    public function test_publish_saves_the_image_and_posts_to_the_webhook(): void
    {
        Storage::fake('public');
        Http::fake(['hook.eu1.make.com/*' => Http::response(['accepted' => true])]);
        SocialPublisher::setWebhook($this->event, 'https://hook.eu1.make.com/secret-abcd');
        $this->actingAs(User::factory()->create(['name' => 'Asha']));

        $this->post(route('admin.events.social.publish', $this->event), [
            'image' => UploadedFile::fake()->image('post.png', 1080, 1350),
            'title' => 'Save the date', 'caption' => 'See you there', 'kind' => 'save-the-date',
        ], ['Accept' => 'application/json'])->assertOk()->assertJson(['ok' => true]);

        $this->assertCount(1, Storage::disk('public')->files('social/'.$this->event->id));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://hook.eu1.make.com/secret-abcd'
            && $request['caption'] === 'See you there'
            && str_contains($request['image_url'], '/storage/social/')
            && $request['event']['name'] === 'WordCamp Rajasthan 2026'
            && $request['sent_by'] === 'admin: Asha');
    }

    public function test_a_manager_uses_it_for_their_own_events_and_it_is_logged(): void
    {
        Storage::fake('public');
        Http::fake(['hooks.zapier.com/*' => Http::response('ok')]);
        $other = Event::create(['slug' => 'wc-other', 'display_name' => 'Other', 'source_site_url' => 'https://o.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);
        $manager = EventManager::factory()->create(['is_active' => true]);
        $manager->events()->attach($this->event->id);
        Auth::guard('manager')->setUser($manager);

        $this->get(route('manager.events.social', $this->event->id))->assertOk()->assertSee('Event posts');
        $this->get(route('manager.events.social', $other->id))->assertNotFound();
        $this->post(route('manager.events.social.webhook', $this->event->id), ['webhook' => 'https://hooks.zapier.com/hooks/catch/1/x/'])->assertRedirect();
        $this->post(route('manager.events.social.publish', $this->event->id), [
            'image' => UploadedFile::fake()->image('post.png'), 'caption' => 'Hi', 'kind' => 'countdown',
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertStringContainsString('countdown', EventManagerChange::latest('id')->first()->summary);
        $this->post(route('manager.events.social.publish', $other->id), ['caption' => 'x', 'kind' => 'x'])->assertNotFound();
    }
}
