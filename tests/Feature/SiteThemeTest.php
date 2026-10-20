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

    private function png(int $w, int $h, bool $alpha = true): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, $alpha);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, $alpha ? 127 : 0));
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    public function test_artwork_is_judged_by_size_words_and_shape_and_copies_count_once(): void
    {
        $file = fn (string $name, ?int $w = null, ?int $h = null, string $title = '', string $alt = '') => ['url' => "https://jaipur.wordcamp.org/2026/files/2026/09/{$name}", 'width' => $w, 'height' => $h, 'title' => $title, 'alt' => $alt];
        $html = '<html><body><div class="wp-block-cover"><img class="wp-block-cover__image-background wp-image-9" alt="" src="https://i0.wp.com/jaipur.wordcamp.org/2026/files/2026/05/amber-fort-1024x512.jpg?resize=1024%2C512&amp;ssl=1"></div></body></html>';
        $theme = (new SiteThemeScraper)->parse($html, [], [
            $file('pink-skyline-white.png', 3000, 600),
            $file('pink-skyline-black-scaled.png', 2560, 512),
            $file('cropped-pink-skyline-1.png', 1200, 240),
            $file('bee-illustration.png', 20, 28),
            $file('jali-tile.png', 96, 96),
            $file('Frame-1000015039.png', 1600, 900),
            $file('IMG_2201.jpg', 1600, 900, 'Wapuu 2026'),
            $file('hero-banner.png', 1200, 630),
            $file('call.webp', 1600, 800, 'Call for Wapuu', 'Wapuu with the text Call For Wapuu'),
            $file('DSC_0042.jpg', 2000, 1333, 'Venue hall', 'The venue hall'),
            $file('55314129638_55a52de455_k.jpg', 2048, 1365, 'wapuu 2026'),
        ]);

        $art = collect($theme['art'])->keyBy('name');
        $this->assertEqualsCanonicalizing(['pink-skyline-white', 'jali-tile', 'amber-fort', 'DSC_0042'], $art->keys()->all(),
            'one skyline for its three copies; no sprite, frame, mascot photo, link-preview graphic, words or photo-sharing upload');
        $this->assertSame('illustration', $art['pink-skyline-white']['kind']);
        $this->assertSame(3000, $art['pink-skyline-white']['width'], 'the biggest copy is kept');
        $this->assertSame('pattern', $art['jali-tile']['kind'], 'a small tile repeats, so it stays');
        $this->assertSame('photo', $art['amber-fort']['kind'], 'a Cover block backdrop');
        $this->assertSame('https://jaipur.wordcamp.org/2026/files/2026/05/amber-fort.jpg', $art['amber-fort']['url'], "the site's own full-size file, not the image CDN's copy");
        $this->assertSame('photo', $art['DSC_0042']['kind'], 'a photo of the venue, found by its title');
        $this->assertSame('pink-skyline-white', $theme['art'][0]['name'], 'a named skyline first');
    }

    public function test_any_of_the_sites_other_artwork_can_be_put_in_use_and_its_kind_corrected(): void
    {
        Storage::fake('public');
        $event = Event::create(['slug' => 'wordcamp-jaipur-2026', 'display_name' => 'WordCamp Jaipur 2026', 'source_site_url' => self::SITE, 'status' => 'active', 'is_visible' => true]);
        $media = array_map(fn ($n) => ['source_url' => "https://jaipur.wordcamp.org/2026/files/2026/09/{$n}-pattern.png", 'media_details' => ['width' => 800, 'height' => 800]], ['amber', 'blue', 'coral', 'dune', 'ember', 'fern', 'gold']);
        Http::fake([self::SITE.'/' => Http::response('<html></html>'), self::SITE.'/wp-json/wp/v2/media*' => Http::response($media), self::SITE.'/wp-json/*' => Http::response([]), '*' => Http::response('', 404)]);
        SiteTheme::refresh($event);
        $theme = SiteTheme::get($event->fresh());
        $this->assertCount(SiteTheme::SLOTS, $theme['art']);
        $this->assertCount(2, $theme['more']);

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.events.social', $event))->assertOk()->assertSee('More from the site (2)')->assertSee('Use this');

        $pick = $theme['more'][1];
        $this->post(route('admin.events.social.art.use', $event), ['piece' => $pick['id']])->assertRedirect()->assertSessionHas('status', 'Artwork "'.$pick['name'].'" is in use.');
        $theme = SiteTheme::get($event->fresh());
        $this->assertSame($pick['name'], $theme['art'][0]['name'], 'the chosen piece comes first');
        $this->assertCount(SiteTheme::SLOTS, $theme['art']);

        $this->post(route('admin.events.social.art.kind', $event), ['piece' => $theme['art'][0]['id'], 'kind' => 'illustration'])->assertSessionHas('status');
        $this->post(route('admin.events.social.art.kind', $event), ['piece' => $theme['art'][0]['id'], 'kind' => 'banana']);
        $this->assertSame('illustration', SiteTheme::get($event->fresh())['art'][0]['kind']);

        // Both survive reading the site again.
        $this->post(route('admin.events.social.site', $event));
        $after = SiteTheme::get($event->fresh())['art'][0];
        $this->assertSame([$pick['name'], 'illustration'], [$after['name'], $after['kind']]);

        // A manager can do the same for their own event only, logged.
        $manager = EventManager::create(['name' => 'Mona', 'email' => 'mona@example.test', 'phone' => '+919800000000', 'password' => 'secret-password-1', 'is_active' => true]);
        $manager->events()->sync([$event->id]);
        $other = Event::create(['slug' => 'wordcamp-other-2026', 'display_name' => 'Other', 'source_site_url' => 'https://other.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);
        $this->actingAs($manager, EnsureEventManager::GUARD);
        $more = SiteTheme::get($event->fresh())['more'][0];
        $this->post(route('manager.events.social.art.use', $event->id), ['piece' => $more['id']])->assertSessionHas('status');
        $this->post(route('manager.events.social.art.kind', $event->id), ['piece' => $more['id'], 'kind' => 'photo'])->assertSessionHas('status');
        $this->assertDatabaseHas('event_manager_changes', ['event_id' => $event->id, 'section' => 'social', 'action' => 'updated']);
        $this->post(route('manager.events.social.art.use', $other->id), ['piece' => $more['id']])->assertNotFound();
        $this->post(route('manager.events.social.art.kind', $other->id), ['piece' => $more['id'], 'kind' => 'photo'])->assertNotFound();
    }

    public function test_the_attendee_app_shows_the_sites_artwork_faintly_behind_the_event_name(): void
    {
        Storage::fake('public');
        $this->withoutVite();
        $event = Event::create(['slug' => 'wordcamp-jaipur-2026', 'display_name' => 'WordCamp Jaipur 2026', 'source_site_url' => self::SITE, 'status' => 'active', 'is_visible' => true]);
        Event::create(['slug' => 'wordcamp-plain-2026', 'display_name' => 'WordCamp Plain 2026', 'source_site_url' => 'https://plain.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);
        Http::fake([
            self::SITE.'/' => Http::response($this->html()),
            self::SITE.'/wp-json/*' => Http::response([]),
            'jaipur.wordcamp.org/2026/files/2026/05/jaipur-skyline.png' => Http::response($this->png(1200, 300)),
            'jaipur.wordcamp.org/2026/files/2026/09/jali-tile.png' => Http::response($this->png(96, 96, false)),
            '*' => Http::response('', 404),
        ]);
        SiteTheme::refresh($event);

        $hero = SiteTheme::hero($event->fresh());
        $this->assertSame('illustration', $hero['kind'], 'the skyline, not the pattern');
        $this->assertTrue($hero['alpha'], 'see-through: drawn as a silhouette');
        Storage::disk('public')->assertExists(substr($hero['url'], strlen('/storage/')));

        foreach (['/event/wordcamp-jaipur-2026', '/event/wordcamp-jaipur-2026/memories'] as $url) {
            $this->get($url)->assertOk()->assertSee('home-hero home-hero--art home-hero--illustration home-hero--mask', false)->assertSee("--hero-art: url('{$hero['url']}')", false);
        }
        $this->get('/event/wordcamp-plain-2026')->assertOk()->assertDontSee('home-hero--art', false);

        // Taking the skyline off moves the backdrop to the pattern and deletes the old copy.
        $this->actingAs(User::factory()->create());
        $skyline = collect(SiteTheme::get($event->fresh())['art'])->firstWhere('name', 'jaipur-skyline');
        $this->post(route('admin.events.social.art.remove', $event), ['piece' => $skyline['id']]);
        $now = SiteTheme::hero($event->fresh());
        $this->assertSame('pattern', $now['kind']);
        $this->assertFalse($now['alpha']);
        Storage::disk('public')->assertMissing(substr($hero['url'], strlen('/storage/')));
    }

    public function test_page_pictures_carry_their_size_and_crafts_cut_outs_and_older_files_are_judged(): void
    {
        $base = 'https://jaipur.wordcamp.org/2026/files/2026/09/';
        $html = '<html><body>'
            .'<img src="'.$base.'gate-illustration-1024x341.png" width="1024" height="341" srcset="'.$base.'gate-illustration-1024x341.png 1024w, '.$base.'gate-illustration.png 3000w">'
            .'<img src="'.$base.'tiny-skyline.png" width="120" height="40">'
            .'<img src="'.$base.'rangoli.png" width="800" height="800">'
            .'<img src="'.$base.'warli-dancers.png" width="1600" height="500">'
            .'<img src="'.$base.'WCJ-2026-website-Banner.jpg" width="1920" height="600">'
            .'<img src="'.$base.'fort-motif-removebg-preview.png" width="500" height="500">'
            .'<img src="https://jaipur.wordcamp.org/2025/files/2025/09/old-motif.png" width="900" height="900">'
            .'<div style="background:url(\''.$base.'venue-hero.png\')"></div>'
            .'<img src="'.$base.'Fort_Gate_Frieze-removebg-preview.png" width="866" height="288">'
            .'<img src="'.$base.'fort-gate-frieze.png" width="2400" height="800">'
            .'</body></html>';
        $scraper = new SiteThemeScraper;
        $art = collect($scraper->parse($html, [], [], [], self::SITE)['art'])->keyBy('name');

        $this->assertSame(3000, $art['gate-illustration']['width'], "the srcset's widest copy is the file's size");
        $this->assertSame(999, $art['gate-illustration']['height']);
        $this->assertArrayNotHasKey('tiny-skyline', $art->all(), 'too small to print, by the size the page gives');
        $this->assertSame('pattern', $art['rangoli']['kind'], 'a rangoli repeats: a pattern');
        $this->assertSame('illustration', $art['warli-dancers']['kind'], 'a Warli scene is drawn art');
        $this->assertArrayNotHasKey('WCJ-2026-website-Banner', $art->all(), 'a website banner carries words');
        $names = $art->keys()->all();
        $this->assertGreaterThan(array_search('rangoli', $names), array_search('fort-motif-removebg-preview', $names), "a background-remover's preview comes after");
        $this->assertGreaterThan(array_search('rangoli', $names), array_search('old-motif', $names), "last year's file comes after");
        $this->assertArrayNotHasKey('Fort_Gate_Frieze-removebg-preview', $art->all(), 'a preview and its full file are one piece: the full one is kept');
        $this->assertSame(['illustration', 2400], [$art['fort-gate-frieze']['kind'], $art['fort-gate-frieze']['width']]);
        $this->assertSame('photo', $art['venue-hero']['kind'], 'a backdrop with nothing more to go on reads as a photo…');

        $probed = $scraper->parse($html, [], [], [$base.'venue-hero.png' => ['width' => 2400, 'height' => 800, 'alpha' => true]], self::SITE)['art'];
        $hero = collect($probed)->firstWhere('name', 'venue-hero');
        $this->assertSame(['illustration', 2400], [$hero['kind'], $hero['width']], '…until opening it shows a cut-out');
    }

    public function test_reading_the_site_takes_in_its_own_css_searches_the_library_and_measures_pictures(): void
    {
        Storage::fake('public');
        $event = Event::create(['slug' => 'wordcamp-jaipur-2026', 'display_name' => 'WordCamp Jaipur 2026', 'source_site_url' => self::SITE, 'status' => 'active', 'is_visible' => true]);
        $css = self::SITE.'/wp-admin/admin-ajax.php?action=wordcamp_remote_css&ver=12';
        $html = '<html><head><link rel="stylesheet" id="wordcamp-remote-css-css" href="'.htmlspecialchars($css).'" media="all">'
            .'<link rel="stylesheet" href="https://evil.example/custom-css.css"></head><body></body></html>';
        Http::fake([
            self::SITE.'/' => Http::response($html),
            self::SITE.'/wp-admin/admin-ajax.php*' => Http::response('.hero{background:#7a1f5c url("https://jaipur.wordcamp.org/2026/files/2026/09/lehariya-pattern.png") repeat}.band{color:#7a1f5c;border-color:#7a1f5c}'),
            self::SITE.'/wp-json/wp/v2/media*' => function ($request) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

                return Http::response(($q['search'] ?? '') === 'skyline' ? [['source_url' => 'https://jaipur.wordcamp.org/2026/files/2024/01/pink-city-skyline.png', 'title' => ['rendered' => 'Pink City skyline']]] : []);
            },
            'jaipur.wordcamp.org/2026/files/2024/01/pink-city-skyline.png' => Http::response($this->png(2400, 600)),
            'jaipur.wordcamp.org/2026/files/2026/09/lehariya-pattern.png' => Http::response($this->png(300, 300, false)),
            self::SITE.'/wp-json/*' => Http::response([]),
            '*' => Http::response('', 404),
        ]);

        $theme = SiteTheme::refresh($event);

        $this->assertContains('#7a1f5c', array_column($theme['colors'], 'hex'), 'colours from its Remote CSS');
        $art = collect($theme['art'])->keyBy('name');
        $this->assertSame('pattern', $art['lehariya-pattern']['kind'], 'a backdrop from its Remote CSS');
        $this->assertSame([2400, 600], [$art['pink-city-skyline']['width'], $art['pink-city-skyline']['height']], 'found by a library search, measured by opening it');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'evil.example'));
        Http::assertSent(fn ($request) => $request->hasHeader('Range', 'bytes=0-65535'));
    }
}
