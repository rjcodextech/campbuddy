<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What the browser tab, the install prompt and the home screen say:
 *   site title    "CampBuddy | Your WordCamp companion", then the tab you're on
 *   PWA name      "CampBuddy | Your WordCamp Companion"
 *   PWA short     "CampBuddy"
 */
class PageTitleAndManifestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake();
    }

    private function event(array $overrides = []): Event
    {
        return Event::create($overrides + [
            'slug' => 'wc-test',
            'display_name' => 'WordCamp Test 2026',
            'short_name' => '#WCTest',
            'source_site_url' => 'https://test.wordcamp.org/2026',
            'status' => 'active',
            'is_visible' => true,
        ]);
    }

    // ---- Site title -------------------------------------------------------

    public function test_the_picker_title_is_the_site_title(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>CampBuddy | Your WordCamp companion</title>', false);
    }

    #[DataProvider('tabs')]
    public function test_each_event_tab_puts_its_own_name_first_in_the_title(string $route, string $label): void
    {
        $event = $this->event();

        $this->get(route($route, $event))
            ->assertOk()
            ->assertSee("<title>{$label} | WordCamp Test 2026 | CampBuddy</title>", false);
    }

    public static function tabs(): array
    {
        return [
            'home' => ['event.home', 'Home'],
            'my day' => ['event.my-day', 'My Day'],
            'quest' => ['event.quest', 'Quest'],
            'contribute' => ['event.contribute', 'Contribute'],
            'explore' => ['event.explore', 'Explore'],
            'camp card' => ['event.camp-card', 'Camp Card'],
        ];
    }

    public function test_the_takedown_page_has_its_own_title_too(): void
    {
        $event = $this->event();

        $this->get(route('event.roster-removal.show', $event))
            ->assertOk()
            ->assertSee('<title>Remove me from the attendee list | WordCamp Test 2026 | CampBuddy</title>', false);
    }

    public function test_admin_and_sign_in_titles_use_the_same_separator(): void
    {
        $this->get(route('login'))->assertSee('<title>Sign in | CampBuddy Admin</title>', false);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.events.index'))
            ->assertOk()
            ->assertSee('<title>Events | CampBuddy Admin</title>', false);
    }

    // ---- Install / home-screen metadata ----------------------------------

    public function test_the_picker_links_the_global_manifest_and_names_the_app_for_ios(): void
    {
        $this->get('/')
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
            ->assertSee('<meta name="apple-mobile-web-app-title" content="CampBuddy">', false)
            ->assertSee('<meta name="application-name" content="CampBuddy">', false)
            ->assertSee('rel="apple-touch-icon" href="/media/icons/apple-touch-icon.png"', false);
    }

    public function test_event_pages_link_their_manifest_and_use_the_same_app_name(): void
    {
        $event = $this->event();

        $this->get(route('event.home', $event))
            ->assertSee('<link rel="manifest" href="/event/wc-test/manifest.json">', false)
            ->assertSee('<meta name="apple-mobile-web-app-title" content="CampBuddy">', false)
            ->assertSee('rel="apple-touch-icon" href="/media/icons/apple-touch-icon.png"', false);
    }

    // ---- Manifest ----------------------------------------------------------

    public function test_the_global_manifest_uses_the_product_name_and_short_name(): void
    {
        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJson([
                'name' => 'CampBuddy | Your WordCamp Companion',
                'short_name' => 'CampBuddy',
                'start_url' => '/',
                'scope' => '/',
                'display' => 'standalone',
            ]);
    }

    public function test_an_event_manifest_has_the_same_name_but_opens_that_event(): void
    {
        $event = $this->event(['display_name' => 'WordCamp Somewhere Else']);

        $this->get(route('event.manifest', $event))
            ->assertOk()
            ->assertJson([
                // Never the event's own name — the install is CampBuddy.
                'name' => 'CampBuddy | Your WordCamp Companion',
                'short_name' => 'CampBuddy',
                'start_url' => '/event/wc-test',
                'id' => '/event/wc-test',
                // Whole origin, so the picker and other events stay inside the app.
                'scope' => '/',
            ]);
    }

    public function test_manifest_icons_are_real_pngs_with_the_right_type_and_size(): void
    {
        $icons = $this->getJson('/manifest.webmanifest')->json('icons');

        $this->assertNotEmpty($icons);
        $this->assertContains('maskable', array_column($icons, 'purpose'));

        foreach ($icons as $icon) {
            $file = public_path(ltrim($icon['src'], '/'));

            $this->assertFileExists($file, "Manifest icon {$icon['src']} is missing");
            $this->assertSame('image/png', $icon['type']);

            [$w, $h, $type] = getimagesize($file);
            $this->assertSame(IMAGETYPE_PNG, $type);
            $this->assertSame("{$w}x{$h}", $icon['sizes'], "{$icon['src']} isn't the size it declares");
        }

        // Chrome's installability floor.
        $sizes = array_column($icons, 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
    }

    public function test_the_page_icons_referenced_in_the_head_exist(): void
    {
        foreach (['favicon-32.png', 'apple-touch-icon.png'] as $name) {
            $this->assertFileExists(public_path("media/icons/{$name}"));
        }

        // /favicon.ico used to be an empty placeholder — browsers request it on their own.
        $this->assertGreaterThan(1000, filesize(public_path('favicon.ico')));
    }

    public function test_a_hidden_event_has_no_manifest(): void
    {
        $event = $this->event(['status' => 'draft']);

        $this->get(route('event.manifest', $event))->assertNotFound();
    }
}
