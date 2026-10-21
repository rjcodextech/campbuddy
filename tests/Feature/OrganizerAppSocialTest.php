<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventManager;
use App\Models\User;
use App\Support\AppOrganizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The app's Social media tab (OrganizerSocialController): same helpers and rules as the web page. */
class OrganizerAppSocialTest extends TestCase
{
    use RefreshDatabase;

    private Event $mine;

    private Event $theirs;

    private EventManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::preventStrayRequests();
        $make = fn (string $slug) => Event::withoutEvents(fn () => Event::create([
            'slug' => $slug, 'display_name' => 'WordCamp '.ucfirst($slug).' 2026', 'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'active', 'is_visible' => true, 'starts_on' => now('Asia/Kolkata')->addDays(5)->toDateString(), 'ends_on' => now('Asia/Kolkata')->addDays(6)->toDateString(), 'timezone' => 'Asia/Kolkata',
        ]));
        $this->mine = $make('mine');
        $this->theirs = $make('theirs');
        $this->manager = EventManager::factory()->create();
        $this->manager->events()->attach($this->mine->id);
    }

    private function asManager(): array
    {
        return ['Authorization' => 'Bearer '.AppOrganizer::issue('manager', $this->manager->id, 'phone')['token']];
    }

    private function url(Event $event, string $path = ''): string
    {
        return "/api/v1/app/organizer/events/{$event->id}/social".$path;
    }

    public function test_the_kit_has_posts_with_captions_links_colours_and_qr_codes(): void
    {
        $data = $this->getJson($this->url($this->mine), $this->asManager())->assertOk()->json('data');

        $this->assertContains('countdown', collect($data['posts'])->pluck('key')->all());
        $countdown = collect($data['posts'])->firstWhere('key', 'countdown');
        $this->assertSame(5, $countdown['days']);
        $this->assertStringContainsString('utm_content=countdown', $countdown['link']);
        $this->assertNotSame('', $countdown['caption']);
        $this->assertSame(['primary', 'secondary', 'ink', 'paper'], array_keys($data['colors']));
        $this->assertSame(['badge', 'standee', 'slide', 'social'], collect($data['qr'])->pluck('key')->all());
        $this->assertFalse($data['webhook']['set']);
        // Today / Spotlight pick from the schedule; people cards say their role keys.
        // (Today and Spotlight only appear once sessions have times; this event has none.)
        $this->assertTrue(collect($data['posts'])->every(fn ($p) => array_key_exists('picker', $p)));
        $this->assertSame(['days', 'next', 'sessions'], array_keys($data['schedule']));
        $this->assertIsArray($data['tables']);

        $this->getJson($this->url($this->theirs), $this->asManager())->assertNotFound();
    }

    public function test_colours_and_webhook_are_saved_and_logged_for_a_manager(): void
    {
        $headers = $this->asManager();

        $this->postJson($this->url($this->mine, '/colors'), ['primary' => '#112233', 'secondary' => '#445566', 'ink' => '#000000', 'paper' => '#FFFFFF'], $headers)
            ->assertOk()->assertJsonPath('data.paper', '#ffffff');
        $this->postJson($this->url($this->mine, '/colors'), ['primary' => 'red', 'secondary' => '#445566', 'ink' => '#000000', 'paper' => '#ffffff'], $headers)->assertUnprocessable();

        $this->postJson($this->url($this->mine, '/webhook'), ['webhook' => 'https://evil.example.com/hook'], $headers)->assertUnprocessable();
        $this->postJson($this->url($this->mine, '/webhook'), ['webhook' => 'https://hook.eu1.make.com/abcd1234'], $headers)->assertOk();
        $this->assertTrue($this->getJson($this->url($this->mine), $headers)->json('data.webhook.set'));
        $this->assertSame(2, \DB::table('event_manager_changes')->where('section', 'social')->count());
    }

    public function test_publish_sends_the_drawn_card_to_the_webhook(): void
    {
        Storage::fake('public');
        Http::fake(['hook.eu1.make.com/*' => Http::response(['ok' => true])]);
        $this->mine->forceFill(['social_webhook' => 'https://hook.eu1.make.com/abcd1234'])->save();
        $headers = $this->asManager();

        $this->post($this->url($this->mine, '/publish'), [
            'image' => UploadedFile::fake()->image('card.png', 1080, 1080), 'title' => 'Countdown', 'caption' => '5 days to go', 'kind' => 'countdown',
        ], $headers + ['Accept' => 'application/json'])->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(fn ($r) => $r['kind'] === 'countdown' && $r['caption'] === '5 days to go');
        $this->assertDatabaseHas('event_manager_changes', ['section' => 'social', 'action' => 'added']);
    }

    public function test_an_admin_reaches_every_event(): void
    {
        $admin = ['Authorization' => 'Bearer '.AppOrganizer::issue('admin', User::factory()->create()->id, 'phone')['token']];

        $this->getJson($this->url($this->theirs), $admin)->assertOk();
        $this->postJson($this->url($this->theirs, '/art/kind'), ['piece' => 'nope', 'kind' => 'pattern'], $admin)->assertOk();
        $this->postJson($this->url($this->theirs, '/art/kind'), ['piece' => 'nope', 'kind' => 'banana'], $admin)->assertUnprocessable();
    }
}
