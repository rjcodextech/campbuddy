<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureEventManager;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\User;
use App\Services\SiteThemeScraper;
use App\Support\EventData;
use App\Support\SiteTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The WordCamp site's own colours, fonts and artwork, read from its home page
 * for the Social media designs.
 */
class SiteThemeTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://jaipur.wordcamp.org/2026';

    private function html(): string
    {
        return <<<'HTML'
<html><head><style>
:root{--wp--preset--color--black:#000000;--wp--preset--color--base:#f9f9f9;--wp--preset--color--accent-3:#d8613c;
--wp--preset--color--custom-terracota:#c33a19;--wp--preset--color--custom-saffron:#e39d1c;--wp--preset--color--custom-cream:#fbeedd;
--wp--preset--font-family--newsreader: Newsreader, serif;--wp--preset--font-family--poppins: Poppins, sans-serif;}
@font-face{font-family:Newsreader;font-style:normal;font-weight:600;src:url('https://jaipur.wordcamp.org/2026/files/fonts/news.woff2') format('woff2');unicode-range:U+0000-00FF;}
@font-face{font-family:Poppins;font-style:normal;font-weight:400;src:url('https://jaipur.wordcamp.org/2026/files/fonts/pop.woff2') format('woff2');}
@font-face{font-family:Unused;src:url('https://jaipur.wordcamp.org/2026/files/fonts/x.woff2');}
body{font-family:var(--wp--preset--font-family--poppins)}
h1, h2, h3{font-family:var(--wp--preset--font-family--newsreader)}
.hero{background:#2a1810 url('https://jaipur.wordcamp.org/2026/files/2026/09/jali-tile.png') repeat}
.band{color:#2a1810;background-color:#2a1810}
</style></head><body>
<img src="https://jaipur.wordcamp.org/2026/files/2026/05/logo-300x119.png">
<img src="https://jaipur.wordcamp.org/2026/files/2026/05/jaipur-skyline-1024x300.png">
<img src="https://jaipur.wordcamp.org/2026/files/2026/07/sponsor-pattern.png">
<img src="https://jaipur.wordcamp.org/2026/files/2026/08/ajit_profile.jpg">
</body></html>
HTML;
    }

    public function test_it_reads_the_sites_own_colours_fonts_and_artwork(): void
    {
        $theme = (new SiteThemeScraper)->parse($this->html(), ['https://jaipur.wordcamp.org/2026/files/2026/07/sponsor-pattern.png']);

        $hexes = array_column($theme['colors'], 'hex');
        $this->assertSame(['#c33a19', '#e39d1c', '#fbeedd'], array_slice($hexes, 0, 3), 'its own presets first');
        $this->assertNotContains('#000000', $hexes, 'core palette left out');
        $this->assertNotContains('#d8613c', $hexes, 'an unchanged theme default left out');
        $this->assertContains('#2a1810', $hexes, 'colours its CSS uses');

        $this->assertSame('Newsreader', $theme['fonts']['heading']['family']);
        $this->assertSame('Poppins', $theme['fonts']['body']['family']);
        $this->assertSame(['url' => 'https://jaipur.wordcamp.org/2026/files/fonts/news.woff2', 'weight' => '600', 'style' => 'normal', 'range' => 'U+0000-00FF'], $theme['fonts']['heading']['faces'][0]);

        $art = collect($theme['art'])->pluck('kind', 'name')->all();
        $this->assertSame(['jali-tile' => 'pattern', 'jaipur-skyline' => 'illustration'], $art, 'no logos, sponsors or people; full-size files');
        $this->assertSame('https://jaipur.wordcamp.org/2026/files/2026/05/jaipur-skyline.png', collect($theme['art'])->firstWhere('name', 'jaipur-skyline')['url']);
    }

    public function test_it_is_read_once_kept_and_read_again_on_request(): void
    {
        $event = Event::create(['slug' => 'wordcamp-jaipur-2026', 'display_name' => 'WordCamp Jaipur 2026', 'source_site_url' => self::SITE, 'status' => 'active', 'is_visible' => true]);
        EventData::put($event->id, 'sponsors', []);
        Http::fake([self::SITE.'/' => Http::response($this->html())]);

        $theme = SiteTheme::get($event);
        $this->assertSame('Newsreader', $theme['fonts']['heading']['family']);
        $this->assertNotNull($event->fresh()->site_theme['fetched_at']);
        SiteTheme::get($event->fresh());
        Http::assertSentCount(1);

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.events.social', $event))->assertOk()
            ->assertSee('From the WordCamp site')->assertSee('#c33a19')->assertSee('Newsreader')->assertSee('jaipur-skyline');
        $this->post(route('admin.events.social.site', $event))->assertRedirect()->assertSessionHas('status');
        Http::assertSentCount(2);
    }

    public function test_artwork_comes_from_the_whole_media_library_and_the_tagline_from_the_site(): void
    {
        $event = Event::create(['slug' => 'wordcamp-jaipur-2026', 'display_name' => 'WordCamp Jaipur 2026', 'source_site_url' => self::SITE, 'status' => 'active', 'is_visible' => true]);
        $file = fn (string $name) => ['source_url' => "https://jaipur.wordcamp.org/2026/files/2026/09/{$name}"];
        Http::fake([
            self::SITE.'/' => Http::response($this->html()),
            self::SITE.'/wp-json/wp/v2/media*' => Http::response([$file('hawa-mahal-illustration.png'), $file('venue-map.png'), $file('tickets-pricing-banner.png'), $file('bandhani-pattern.png'), $file('ajit_profile.jpg')]),
            self::SITE.'/wp-json/*' => Http::response(['description' => 'Oct 3–4, Padharo mhare des']),
        ]);

        $theme = SiteTheme::refresh($event);
        $names = array_column($theme['art'], 'name');
        $this->assertContains('hawa-mahal-illustration', $names, 'from another page, via the media library');
        $this->assertContains('bandhani-pattern', $names);
        $this->assertNotContains('venue-map', $names, 'a map is not artwork');
        $this->assertNotContains('tickets-pricing-banner', $names, 'nor a ticket price graphic');
        $this->assertLessThanOrEqual(SiteTheme::SLOTS, count($theme['art']));
        $this->assertSame('Padharo mhare des', $theme['tagline'], 'the date in front of the tagline is dropped');
        $this->assertSame('Padharo mhare des', SiteTheme::tagline($event->fresh()));
    }

    public function test_a_tagline_that_is_only_a_date_or_the_venue_is_not_one(): void
    {
        $this->assertSame('Dil se Dilli, code se community', SiteTheme::cleanTagline('Dil se Dilli, code se community'));
        $this->assertSame('Namma WordPress', SiteTheme::cleanTagline('Nov 21–22, Namma WordPress'));
        $this->assertNull(SiteTheme::cleanTagline('12 &amp; 13 november 2026'));
        $this->assertNull(SiteTheme::cleanTagline('6th November, Whitworth Locke', 'Whitworth Locke — Manchester'));
    }

    public function test_wordpress_placeholder_taglines_are_ignored(): void
    {
        $event = Event::create(['slug' => 'wordcamp-jaipur-2026', 'display_name' => 'WordCamp Jaipur 2026', 'source_site_url' => self::SITE, 'status' => 'active', 'is_visible' => true]);
        Http::fake([self::SITE.'/' => Http::response($this->html()), self::SITE.'/wp-json/wp/v2/media*' => Http::response([]), self::SITE.'/wp-json/*' => Http::response(['description' => 'Just another WordCamp'])]);

        $this->assertNull(SiteTheme::refresh($event)['tagline']);
    }

    public function test_empty_places_take_an_upload_and_artwork_can_be_taken_off(): void
    {
        Storage::fake('public');
        $event = Event::create(['slug' => 'wordcamp-jaipur-2026', 'display_name' => 'WordCamp Jaipur 2026', 'source_site_url' => self::SITE, 'status' => 'active', 'is_visible' => true]);
        Http::fake([self::SITE.'/' => Http::response($this->html()), self::SITE.'/wp-json/*' => Http::response([])]);
        SiteTheme::refresh($event);
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.events.social', $event))->assertOk()->assertSee('Artwork (2 of 5)')->assertSee('Empty place 3');

        $this->post(route('admin.events.social.art', $event), ['art' => UploadedFile::fake()->image('camel.png', 800, 300), 'kind' => 'illustration'])
            ->assertRedirect()->assertSessionHas('status', 'Artwork added.');
        $theme = SiteTheme::get($event->fresh());
        $uploaded = collect($theme['art'])->firstWhere('uploaded', true);
        $this->assertSame('camel', $uploaded['name']);
        Storage::disk('public')->assertExists(substr($uploaded['url'], strlen('/storage/')));

        $this->post(route('admin.events.social.art', $event), ['art' => UploadedFile::fake()->image('tiny.png', 50, 50), 'kind' => 'pattern'])->assertSessionHasErrors('art');
        $this->post(route('admin.events.social.art', $event), ['art' => UploadedFile::fake()->create('x.svg', 10, 'image/svg+xml'), 'kind' => 'pattern'])->assertSessionHasErrors('art');

        // An uploaded piece is deleted; one from the site stays off after reading the site again.
        $this->post(route('admin.events.social.art.remove', $event), ['piece' => $uploaded['id']])->assertSessionHas('status', 'Artwork taken off.');
        Storage::disk('public')->assertMissing(substr($uploaded['url'], strlen('/storage/')));
        $skyline = collect(SiteTheme::get($event->fresh())['art'])->firstWhere('name', 'jaipur-skyline');
        $this->post(route('admin.events.social.art.remove', $event), ['piece' => $skyline['id']]);
        $this->post(route('admin.events.social.site', $event));
        $this->assertNotContains('jaipur-skyline', array_column(SiteTheme::get($event->fresh())['art'], 'name'));
    }

    public function test_a_manager_uploads_for_their_own_event_only_and_it_is_logged(): void
    {
        Storage::fake('public');
        $event = Event::create(['slug' => 'wordcamp-jaipur-2026', 'display_name' => 'WordCamp Jaipur 2026', 'source_site_url' => self::SITE, 'status' => 'active', 'is_visible' => true]);
        $other = Event::create(['slug' => 'wordcamp-other-2026', 'display_name' => 'WordCamp Other 2026', 'source_site_url' => 'https://other.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);
        $manager = EventManager::create(['name' => 'Mona', 'email' => 'mona@example.test', 'phone' => '+919800000000', 'password' => 'secret-password-1', 'is_active' => true]);
        $manager->events()->sync([$event->id]);
        $this->actingAs($manager, EnsureEventManager::GUARD);

        $this->post(route('manager.events.social.art', $event->id), ['art' => UploadedFile::fake()->image('fort.png', 800, 300), 'kind' => 'illustration'])->assertSessionHas('status', 'Artwork added.');
        $this->assertDatabaseHas('event_manager_changes', ['event_id' => $event->id, 'section' => 'social', 'action' => 'added']);
        $this->post(route('manager.events.social.art', $other->id), ['art' => UploadedFile::fake()->image('x.png', 800, 300), 'kind' => 'illustration'])->assertNotFound();
    }

    public function test_a_site_that_cannot_be_read_leaves_the_page_working(): void
    {
        $event = Event::create(['slug' => 'wordcamp-down-2026', 'display_name' => 'WordCamp Down 2026', 'source_site_url' => 'https://down.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);
        Http::fake(['down.wordcamp.org/*' => Http::response('nope', 500)]);

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.events.social', $event))->assertOk()->assertSee('could not be read just now');
    }

    public function test_the_sites_font_files_come_through_the_proxy_as_fonts(): void
    {
        Storage::fake('local');
        $event = Event::create(['slug' => 'wordcamp-jaipur-2026', 'display_name' => 'WordCamp Jaipur 2026', 'source_site_url' => self::SITE, 'status' => 'active', 'is_visible' => true]);
        Http::fake(['jaipur.wordcamp.org/2026/files/fonts/*' => Http::response('wOF2data', 200, ['Content-Type' => 'application/octet-stream'])]);
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.events.social.image', $event).'?u='.urlencode('https://jaipur.wordcamp.org/2026/files/fonts/news.woff2'))
            ->assertOk()->assertHeader('Content-Type', 'font/woff2');
        $this->get(route('admin.events.social.image', $event).'?u='.urlencode('https://jaipur.wordcamp.org/2026/files/fonts/evil.html'))->assertNotFound();
    }
}
