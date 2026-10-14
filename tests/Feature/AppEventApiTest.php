<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\FreeSteal;
use App\Models\Offer;
use App\Models\Quest;
use App\Support\EventData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * GET /api/v1/app/events… : the native app's read-only view of a WordCamp.
 */
class AppEventApiTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->event = $this->makeEvent('wordcamp-test', now()->addDays(10)->toDateString());
    }

    private function makeEvent(string $slug, string $starts, array $over = []): Event
    {
        return Event::withoutEvents(fn () => Event::create($over + [
            'slug' => $slug, 'display_name' => ucwords(str_replace('-', ' ', $slug)), 'short_name' => '#WC',
            'source_site_url' => "https://{$slug}.wordcamp.org/2026", 'status' => 'active', 'is_visible' => true,
            'starts_on' => $starts, 'ends_on' => $starts, 'timezone' => 'Asia/Kolkata', 'country_code' => 'IN',
        ]));
    }

    private function url(string $path = '', ?Event $event = null): string
    {
        return '/api/v1/app/events/'.($event ?? $this->event)->slug.$path;
    }

    public function test_the_list_matches_what_the_picker_page_shows(): void
    {
        $this->makeEvent('wordcamp-later', now()->addDays(60)->toDateString());
        $this->makeEvent('wordcamp-draft', now()->addDays(5)->toDateString(), ['status' => 'draft']);
        $this->makeEvent('wordcamp-hidden', now()->addDays(5)->toDateString(), ['is_visible' => false]);

        $api = collect($this->getJson('/api/v1/app/events')->assertOk()->json('data'))->pluck('slug')->all();
        $page = $this->get('/')->viewData('events')->pluck('slug')->all();

        $this->assertSame($page, $api, 'the app lists the same WordCamps in the same order as the picker page');
        $this->assertSame(['wordcamp-test', 'wordcamp-later'], $api);
    }

    public function test_a_card_has_what_the_picker_needs(): void
    {
        $card = $this->getJson('/api/v1/app/events')->json('data.0');

        $this->assertSame(['slug', 'name', 'short_name', 'logo', 'mark', 'starts_on', 'ends_on', 'first_day', 'last_day', 'timezone', 'country', 'completed', 'website'], array_keys($card));
        $this->assertSame('IN', $card['country']);
        $this->assertSame('Asia/Kolkata', $card['timezone']);
        $this->assertFalse($card['completed']);
    }

    public function test_the_list_can_be_narrowed_by_country(): void
    {
        $this->makeEvent('wordcamp-berlin', now()->addDays(30)->toDateString(), ['timezone' => 'Europe/Berlin', 'country_code' => 'DE']);

        $this->assertSame(['wordcamp-berlin'], collect($this->getJson('/api/v1/app/events?country=de')->json('data'))->pluck('slug')->all());
    }

    public function test_a_draft_or_hidden_event_is_not_found(): void
    {
        $draft = $this->makeEvent('wordcamp-draft', now()->addDays(5)->toDateString(), ['status' => 'draft']);
        $hidden = $this->makeEvent('wordcamp-hidden', now()->addDays(5)->toDateString(), ['is_visible' => false]);

        foreach ([$draft, $hidden] as $event) {
            foreach (['', '/sessions', '/bundle', '/guide'] as $path) {
                $this->getJson($this->url($path, $event))->assertNotFound();
            }
        }
    }

    public function test_the_detail_carries_the_data_version_and_timezone(): void
    {
        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.slug', 'wordcamp-test')
            ->assertJsonPath('data.timezone', 'Asia/Kolkata')
            ->assertJsonStructure(['data' => ['data_version', 'info', 'memories', 'delay', 'hero_art', 'tagline']]);
    }

    public function test_sessions_come_with_the_running_late_delay_applied(): void
    {
        $start = now()->addDays(10)->setTime(10, 0)->utc()->toIso8601String();
        EventData::put($this->event->id, 'sessions', [['id' => 1, 'title' => 'Keynote', 'starts_at' => $start, 'duration_seconds' => 3600, 'track_names' => ['Main'], 'speaker_ids' => [], 'track_ids' => [], 'category_names' => []]]);

        $plain = $this->getJson($this->url('/sessions'))->assertOk()->json('data.0');
        $this->assertSame('Keynote', $plain['title']);
        $this->assertNotNull($plain['ends_at']);
        $this->assertSame(3600, strtotime($plain['ends_at']) - strtotime($plain['starts_at']));

        $this->event->forceFill(['schedule_delays' => [['track' => null, 'minutes' => 20, 'from' => now()->subDay()->toIso8601String(), 'until' => now()->addDays(11)->toIso8601String(), 'note' => 'Lunch ran over']]])->save();
        Cache::flush();
        $late = $this->getJson($this->url('/sessions'))->json('data.0');

        $this->assertSame(1200, strtotime($late['starts_at']) - strtotime($plain['starts_at']), 'every phone sees the moved time');
        $this->getJson($this->url('/delay'))->assertOk()->assertJsonPath('data.0.minutes', 20);
    }

    public function test_speakers_come_as_plain_text_not_markup(): void
    {
        EventData::put($this->event->id, 'speakers', [['id' => 7, 'name' => 'Asha Rao', 'bio_html' => '<h2>Asha Rao</h2><p>Builds <strong>plugins</strong>.</p>', 'avatar_url' => null, 'link' => null, 'social_links' => []]]);

        $speaker = $this->getJson($this->url('/speakers'))->json('data.0');

        $this->assertArrayNotHasKey('bio_html', $speaker);
        $this->assertStringContainsString('Builds plugins.', $speaker['bio_text']);
        $this->assertStringNotContainsString('<', $speaker['bio_text']);
    }

    public function test_deals_free_steals_quests_and_the_guide_are_served(): void
    {
        Offer::create(['event_id' => $this->event->id, 'brand' => 'Acme Hosting', 'title' => '20% off', 'description' => 'Hosting for WordPress', 'url' => 'https://example.com/deal', 'is_active' => true, 'sort_order' => 1]);
        FreeSteal::create(['name' => 'A free plugin', 'description' => 'Makes forms', 'category' => 'forms', 'url' => 'https://example.com/plugin', 'maker' => 'Someone', 'is_active' => true, 'sort_order' => 1]);
        Quest::create(['event_id' => $this->event->id, 'source' => 'event', 'title' => 'Say hi to a speaker', 'is_active' => true, 'sort_order' => 1]);

        $this->assertContains('20% off', collect($this->getJson($this->url('/deals'))->json('data'))->pluck('title')->all());
        $this->assertSame('A free plugin', $this->getJson($this->url('/free-steals'))->json('data.0.name'));
        $this->assertContains('Say hi to a speaker', collect($this->getJson($this->url('/quests'))->json('data'))->pluck('title')->all());
        $this->getJson($this->url('/guide'))->assertOk()->assertJsonStructure(['data' => ['day', 'moments', 'glossary', 'tips', 'bring', 'faq']]);
        $this->getJson($this->url('/contributor-tables'))->assertOk()->assertJsonPath('data', []);
    }

    public function test_info_comes_as_groups_and_only_real_links_become_actions(): void
    {
        $this->event->update(['info' => [
            'venue' => 'Main Hall, Pune', 'wifi' => 'cb-guest / pass123', 'emergency_contact' => '+91 98765 43210',
            'code_of_conduct_url' => 'javascript:alert(1)', 'registration_info' => 'Desk opens at 8',
            'important_links' => "Tickets: https://x.wordcamp.org/2026/tickets/\nBring your own mug",
        ]]);

        $groups = collect($this->getJson($this->url('/info'))->assertOk()->json('data'))->keyBy('title');

        $this->assertSame(['At the venue', 'Help', 'Links'], $groups->keys()->all());
        $venue = collect($groups['At the venue']['rows'])->keyBy('title');
        $this->assertStringContainsString('google.com/maps', $venue['Venue']['action']['value']);
        $this->assertSame('copy', $venue['Wifi']['action']['type']);
        $help = collect($groups['Help']['rows'])->keyBy('title');
        $this->assertSame('tel:+919876543210', $help['Emergency contact']['action']['value']);
        $this->assertArrayNotHasKey('Code of Conduct', $help->all(), 'a javascript: address is never offered');
        $links = $groups['Links']['rows'];
        $this->assertSame('Tickets', $links[0]['title']);
        $this->assertSame('https://x.wordcamp.org/2026/tickets/', $links[0]['action']['value']);
        $this->assertArrayNotHasKey('action', $links[1]);
    }

    public function test_an_event_without_info_has_no_groups(): void
    {
        $this->assertSame([], $this->getJson($this->url('/info'))->assertOk()->json('data'));
    }

    public function test_the_bundle_has_everything_and_a_current_phone_gets_304(): void
    {
        $bundle = $this->getJson($this->url('/bundle'))->assertOk()->json('data');

        $this->assertSame(['event', 'sessions', 'speakers', 'sponsors', 'organizers', 'deals', 'free_steals', 'quests', 'contributor_tables', 'guide', 'data_version'], array_keys($bundle));

        $this->getJson($this->url('/bundle?since='.$bundle['data_version']))->assertStatus(304);
        $this->getJson($this->url('/bundle?since=stale'))->assertOk();
    }

    public function test_answers_carry_an_etag_and_honour_if_none_match(): void
    {
        $etag = $this->getJson($this->url('/sessions'))->headers->get('ETag');

        $this->assertNotEmpty($etag);
        $this->getJson($this->url('/sessions'), ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_memories_are_open_for_a_visible_event_even_after_it_ended(): void
    {
        $past = $this->makeEvent('wordcamp-past', now()->subMonths(2)->toDateString());
        $past->forceFill(['photo_album_url' => 'https://photos.example.com/album'])->save();

        $this->getJson($this->url('/memories', $past))->assertOk()->assertJsonPath('data.photos', 'https://photos.example.com/album');
    }
}
