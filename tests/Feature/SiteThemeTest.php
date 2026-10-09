<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Services\SiteThemeScraper;
use App\Support\EventData;
use App\Support\SiteTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
