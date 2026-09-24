<?php

namespace Tests\Feature;

use App\Jobs\FetchBrandingAssetsJob;
use App\Models\Event;
use App\Models\User;
use App\Support\SvgGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Event logos and favicons: that their URLs work on any host, that they're
 * served even where the public/storage symlink doesn't exist (the production
 * failure), that a broken one degrades to CampBuddy's icon, and that the
 * fetch/upload paths store them safely.
 */
class EventBrandingTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://test.wordcamp.org/2026';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        Http::preventStrayRequests();
    }

    private function event(array $overrides = []): Event
    {
        return Event::create($overrides + [
            'slug' => 'wc-test',
            'display_name' => 'WordCamp Test 2026',
            'source_site_url' => self::SITE,
            'status' => 'active',
            'is_visible' => true,
        ]);
    }

    // ---- URLs -------------------------------------------------------------

    public function test_branding_urls_are_root_relative_and_versioned(): void
    {
        Storage::disk('public')->put('branding/1/logo.png', 'png-bytes');
        Storage::disk('public')->put('branding/1/favicon.png', 'png-bytes');
        $event = $this->event(['logo_path' => 'branding/1/logo.png', 'favicon_path' => 'branding/1/favicon.png']);

        // No host, no scheme: correct whatever APP_URL says or which domain served the page.
        $this->assertMatchesRegularExpression('#^/storage/branding/1/logo\.png\?v=\d+$#', $event->logoUrl());
        $this->assertMatchesRegularExpression('#^/storage/branding/1/favicon\.png\?v=\d+$#', $event->faviconUrl());
    }

    public function test_no_branding_means_null_not_a_broken_url(): void
    {
        $event = $this->event();

        $this->assertNull($event->logoUrl());
        $this->assertNull($event->faviconUrl());
        $this->assertNull($event->markUrl());
    }

    public function test_the_mark_prefers_the_square_favicon_over_the_wide_logo(): void
    {
        Storage::disk('public')->put('branding/1/logo.png', 'x');
        $event = $this->event(['logo_path' => 'branding/1/logo.png']);
        $this->assertSame($event->logoUrl(), $event->markUrl());

        Storage::disk('public')->put('branding/1/favicon.png', 'x');
        $event->update(['favicon_path' => 'branding/1/favicon.png']);
        $this->assertSame($event->faviconUrl(), $event->markUrl());
    }

    public function test_replacing_a_file_changes_the_url_so_caches_cant_serve_the_old_one(): void
    {
        Storage::disk('public')->put('branding/1/logo.png', 'old');
        $event = $this->event(['logo_path' => 'branding/1/logo.png']);
        $before = $event->logoUrl();

        touch(Storage::disk('public')->path('branding/1/logo.png'), time() + 120);
        clearstatcache();

        $this->assertNotSame($before, $event->fresh()->logoUrl());
    }

    // ---- Serving: the production 404 ---------------------------------------

    public function test_storage_files_are_served_without_a_public_storage_symlink(): void
    {
        Storage::disk('public')->put('branding/1/logo.png', 'png-bytes');

        $response = $this->get('/storage/branding/1/logo.png');

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('max-age=', $response->headers->get('Cache-Control'));
    }

    public function test_a_missing_file_is_a_plain_404(): void
    {
        $this->get('/storage/branding/9/logo.png')->assertNotFound();
    }

    public function test_only_image_files_are_ever_served(): void
    {
        Storage::disk('public')->put('branding/1/notes.txt', 'secret');
        Storage::disk('public')->put('branding/1/page.html', '<script>alert(1)</script>');
        Storage::disk('public')->put('branding/1/run.php', '<?php echo 1;');

        $this->get('/storage/branding/1/notes.txt')->assertNotFound();
        $this->get('/storage/branding/1/page.html')->assertNotFound();
        $this->get('/storage/branding/1/run.php')->assertNotFound();
    }

    public function test_path_traversal_cannot_escape_the_public_disk(): void
    {
        $this->get('/storage/branding/../../../.env')->assertNotFound();
        $this->get('/storage/../../composer.json')->assertNotFound();
        $this->get('/storage/branding/1/../../../../public/index.php')->assertNotFound();
    }

    public function test_an_svg_is_served_with_a_script_blocking_policy(): void
    {
        Storage::disk('public')->put('branding/1/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $this->get('/storage/branding/1/logo.svg')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
    }

    // ---- What the pages render ----------------------------------------------

    public function test_the_picker_shows_the_events_own_icon_with_a_fallback_if_it_fails_to_load(): void
    {
        Storage::disk('public')->put('branding/1/favicon.png', 'x');
        $this->event(['favicon_path' => 'branding/1/favicon.png']);

        $this->get('/')
            ->assertOk()
            ->assertSee('src="/storage/branding/1/favicon.png?v=', false)
            ->assertSee('data-fallback="/media/icons/icon-192.png"', false);
    }

    public function test_the_picker_uses_the_logo_when_an_event_has_no_favicon(): void
    {
        Storage::disk('public')->put('branding/1/logo.png', 'x');
        $this->event(['logo_path' => 'branding/1/logo.png']);

        $this->get('/')->assertSee('src="/storage/branding/1/logo.png?v=', false);
    }

    public function test_the_picker_falls_back_to_campbuddys_icon_when_an_event_has_no_branding(): void
    {
        $this->event();

        $this->get('/')->assertOk()->assertSee('src="/media/icons/icon-192.png"', false);
    }

    public function test_the_home_hero_shows_the_logo_and_falls_back_cleanly(): void
    {
        $event = $this->event();
        $this->get(route('event.home', $event))
            ->assertSee('class="home-hero__logo" src="/media/icons/icon-192.png"', false);

        Storage::disk('public')->put('branding/1/logo.png', 'x');
        $event->update(['logo_path' => 'branding/1/logo.png']);
        $this->get(route('event.home', $event))
            ->assertSee('class="home-hero__logo" src="/storage/branding/1/logo.png?v=', false)
            ->assertSee('data-fallback="/media/icons/icon-192.png"', false);
    }

    public function test_pages_no_longer_ship_the_multi_megabyte_originals(): void
    {
        $event = $this->event();

        foreach (['/', route('event.home', $event), route('event.camp-card', $event)] as $url) {
            $html = $this->get($url)->getContent();

            foreach (['/media/logo.svg', '/media/logo.png', '/media/icon.svg', '/media/favicon.png'] as $heavy) {
                $this->assertStringNotContainsString($heavy, $html, "{$url} still loads {$heavy}");
            }
        }
    }

    // ---- Storing branding -----------------------------------------------------

    public function test_storing_a_new_logo_removes_the_old_one_even_with_a_different_extension(): void
    {
        Storage::disk('public')->put('branding/1/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        Storage::disk('public')->put('branding/1/favicon.png', 'keep me');
        $event = $this->event(['logo_path' => 'branding/1/logo.svg', 'favicon_path' => 'branding/1/favicon.png']);

        $event->storeBranding('logo', 'png', 'new-png-bytes');

        Storage::disk('public')->assertMissing('branding/1/logo.svg');
        Storage::disk('public')->assertExists('branding/1/logo.png');
        Storage::disk('public')->assertExists('branding/1/favicon.png');
        $this->assertSame('branding/1/logo.png', $event->fresh()->logo_path);
    }

    public function test_svg_guard_refuses_anything_executable(): void
    {
        $this->assertTrue(SvgGuard::isSafe('<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>'));

        foreach ([
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><rect/></a></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><div/></foreignObject></svg>',
            '<html><body>not an svg</body></html>',
        ] as $bad) {
            $this->assertFalse(SvgGuard::isSafe($bad), $bad);
        }
    }

    // ---- Auto-fetch --------------------------------------------------------------

    /** A WordCamp site: homepage with a custom logo, and a site icon in the REST root. */
    private function fakeWordCampSite(string $faviconType = 'image/png', string $faviconBody = 'FAVICON'): void
    {
        Http::fake([
            self::SITE.'/wp-json/' => Http::response(['site_icon_url' => self::SITE.'/files/icon.png']),
            self::SITE.'/files/icon.png' => Http::response($faviconBody, 200, ['Content-Type' => $faviconType]),
            self::SITE.'/files/logo.png' => Http::response('FETCHED-LOGO', 200, ['Content-Type' => 'image/png']),
            self::SITE.'/' => Http::response('<html><body><img class="custom-logo" src="'.self::SITE.'/files/logo.png"></body></html>'),
        ]);
    }

    public function test_fetching_stores_the_logo_and_favicon_on_our_own_storage(): void
    {
        $event = $this->event();
        $this->fakeWordCampSite();

        FetchBrandingAssetsJob::dispatchSync($event);

        $event->refresh();
        $this->assertSame('branding/'.$event->id.'/logo.png', $event->logo_path);
        $this->assertSame('branding/'.$event->id.'/favicon.png', $event->favicon_path);
        $this->assertSame('FETCHED-LOGO', Storage::disk('public')->get($event->logo_path));
    }

    public function test_a_fill_in_run_never_overwrites_an_admin_uploaded_logo(): void
    {
        Storage::disk('public')->put('branding/1/logo.png', 'ADMIN-UPLOAD');
        $event = $this->event(['logo_path' => 'branding/1/logo.png']);
        $this->fakeWordCampSite();

        FetchBrandingAssetsJob::dispatchSync($event, true);

        $event->refresh();
        $this->assertSame('ADMIN-UPLOAD', Storage::disk('public')->get('branding/1/logo.png'));
        $this->assertNotNull($event->favicon_path, 'the missing favicon should still be filled in');
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/files/logo.png'));
    }

    public function test_the_admin_refetch_button_does_replace_the_logo(): void
    {
        Storage::disk('public')->put('branding/1/logo.png', 'OLD');
        $event = $this->event(['logo_path' => 'branding/1/logo.png']);
        $this->fakeWordCampSite();

        FetchBrandingAssetsJob::dispatchSync($event);

        $this->assertSame('FETCHED-LOGO', Storage::disk('public')->get($event->fresh()->logo_path));
    }

    public function test_a_fetched_svg_carrying_a_script_is_refused(): void
    {
        $event = $this->event();
        $this->fakeWordCampSite('image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>');

        FetchBrandingAssetsJob::dispatchSync($event);

        $this->assertNull($event->fresh()->favicon_path);
        $this->assertNotNull($event->fresh()->logo_path, 'a harmless logo is unaffected');
    }

    public function test_one_unreachable_asset_does_not_stop_the_other(): void
    {
        $event = $this->event();
        Http::fake([
            self::SITE.'/wp-json/' => Http::response(['site_icon_url' => self::SITE.'/files/icon.png']),
            self::SITE.'/files/icon.png' => Http::response('FAVICON', 200, ['Content-Type' => 'image/png']),
            self::SITE.'/files/logo.png' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'),
            self::SITE.'/' => Http::response('<img class="custom-logo" src="'.self::SITE.'/files/logo.png">'),
        ]);

        FetchBrandingAssetsJob::dispatchSync($event);

        $this->assertNull($event->fresh()->logo_path);
        $this->assertNotNull($event->fresh()->favicon_path);
    }

    // ---- Admin upload -----------------------------------------------------------------

    public function test_an_admin_can_upload_a_logo(): void
    {
        $event = $this->event();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.events.upload-branding', $event), [
                'logo' => UploadedFile::fake()->image('mylogo.png', 200, 80),
            ])
            ->assertRedirect(route('admin.events.edit', $event));

        $event->refresh();
        $this->assertSame('branding/'.$event->id.'/logo.png', $event->logo_path);
        Storage::disk('public')->assertExists($event->logo_path);
    }

    public function test_an_uploaded_svg_with_a_script_is_rejected_with_a_message(): void
    {
        $event = $this->event();
        $evil = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>'
        );

        $this->actingAs(User::factory()->create())
            ->from(route('admin.events.edit', $event))
            ->post(route('admin.events.upload-branding', $event), ['logo' => $evil])
            ->assertSessionHasErrors('logo');

        $this->assertNull($event->fresh()->logo_path);
        Storage::disk('public')->assertMissing('branding/'.$event->id.'/logo.svg');
    }
}
