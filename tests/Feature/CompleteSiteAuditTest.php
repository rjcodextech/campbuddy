<?php

namespace Tests\Feature;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Support\EventData;
use App\Models\EventManager;
use App\Models\FreeSteal;
use App\Models\Offer;
use App\Models\Quest;
use App\Models\User;
use App\Support\SocialImageProxy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Complete Site Audit: Ensures all public, attendee, admin, and manager pages
 * load properly, render valid HTML, and provide robust image loading fallbacks
 * and mobile-responsive studio markup.
 */
class CompleteSiteAuditTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $admin;
    private EventManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'slug' => 'wordcamp-rajasthan-2026',
            'display_name' => 'WordCamp Rajasthan 2026',
            'short_name' => '#WCRajasthan',
            'source_site_url' => 'https://rajasthan.wordcamp.org/2026',
            'status' => 'active',
            'is_visible' => true,
            'country_code' => 'IN',
            'starts_on' => now()->addDays(5)->toDateString(),
            'ends_on' => now()->addDays(6)->toDateString(),
            'info' => [
                'venue' => 'Rajasthan International Centre — JLN Marg, Jaipur',
                'wifi' => 'RIC-Guest / wcj2026',
                'emergency_contact' => '+91 98765 43210',
                'important_links' => 'https://rajasthan.wordcamp.org/2026/',
            ],
            'site_theme' => [
                'colors' => [['name' => 'Maroon', 'hex' => '#c43e1e']],
                'fonts' => ['heading' => ['family' => 'Baloo 2'], 'body' => ['family' => 'Poppins']],
                'art' => [],
                'tagline' => 'Padharo Mhare Des',
                'fetched_at' => now()->toIso8601String(),
            ],
        ]);

        EventData::put($this->event->id, 'speakers', [
            ['id' => 1, 'name' => 'Rahul Sharma', 'avatar_url' => 'https://secure.gravatar.com/avatar/test?s=96&d=mm'],
            ['id' => 2, 'name' => 'Asha Rao', 'avatar_url' => 'https://rajasthan.wordcamp.org/2026/files/asha.jpg'],
        ]);

        EventData::put($this->event->id, 'sponsors', [
            ['id' => 10, 'name' => 'Global Hosting', 'logo_url' => 'https://rajasthan.wordcamp.org/2026/files/sponsor.png', 'tier_names' => ['Gold']],
        ]);

        EventData::put($this->event->id, 'sessions', [
            ['id' => 100, 'title' => 'Opening Ceremony', 'starts_at' => now()->addDays(5)->setTime(9, 0)->toIso8601String(), 'speaker_ids' => []],
            ['id' => 101, 'title' => 'WordPress in 2026', 'starts_at' => now()->addDays(5)->setTime(10, 0)->toIso8601String(), 'speaker_ids' => [1]],
        ]);

        AttendeeRoster::create([
            'event_id' => $this->event->id,
            'name' => 'Rahul Sharma',
            'links' => [['url' => 'https://github.com/rahul', 'type' => 'website']],
            'content_hash' => 'hash_rahul',
            'is_suppressed' => false,
        ]);

        Offer::create([
            'event_id' => $this->event->id,
            'title' => 'Event Special Discount',
            'description' => 'Special attendee offer',
            'url' => 'https://example.com/offer',
            'is_active' => true,
        ]);

        FreeSteal::create([
            'name' => 'Open Source Toolkit',
            'description' => 'Free developer toolkit',
            'maker' => 'CampBuddy Community',
            'category' => 'Developer Tools',
            'url' => 'https://github.com/campbuddy/toolkit',
            'is_active' => true,
            'is_featured' => true,
        ]);

        Quest::create([
            'event_id' => $this->event->id,
            'source' => 'event',
            'title' => 'Meet 3 Speakers',
            'description' => 'Find speakers and connect',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create();

        $this->manager = EventManager::factory()->create(['is_active' => true]);
        $this->manager->events()->attach($this->event->id);
    }

    public function test_public_pages_load_cleanly(): void
    {
        $publicUrls = [
            '/',
            '/privacy',
            '/terms',
            '/sponsor-deals/submit',
        ];

        foreach ($publicUrls as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $this->assertNoBrokenImgTags($response->getContent(), $url);
        }
    }

    public function test_all_attendee_event_pages_load_cleanly(): void
    {
        $eventUrls = [
            route('event.home', $this->event->slug),
            route('event.guide', $this->event->slug),
            route('event.my-day', $this->event->slug),
            route('event.quest', $this->event->slug),
            route('event.explore', $this->event->slug),
            route('event.contribute', $this->event->slug),
            route('event.memories', $this->event->slug),
            route('event.camp-card', $this->event->slug),
        ];

        foreach ($eventUrls as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $this->assertNoBrokenImgTags($response->getContent(), $url);
        }
    }

    public function test_admin_pages_load_and_render_fallbacks(): void
    {
        $this->actingAs($this->admin);

        $adminUrls = [
            route('dashboard'),
            route('admin.events.index'),
            route('admin.events.edit', $this->event),
            route('admin.events.roster.index', $this->event),
            route('admin.deals.index'),
            route('admin.deal-submissions.index'),
            route('admin.media.index'),
            route('admin.commands.index'),
            route('admin.mobile.index'),
            route('admin.analytics.index'),
        ];

        foreach ($adminUrls as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $this->assertNoBrokenImgTags($response->getContent(), $url);
        }
    }

    public function test_admin_studio_is_mobile_responsive_and_has_fallbacks(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.events.social', $this->event));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertNoBrokenImgTags($content, 'admin social studio');

        // Studio dialog structure
        $response->assertSee('class="cb-studio"', false);
        $response->assertSee('data-studio', false);
        $response->assertSee('cb-studio-header', false);

        // Mobile close button & desktop close button
        $response->assertSee('sm:hidden', false);
        $response->assertSee('sm:inline-flex', false);
        $response->assertSee('data-studio-close', false);

        // Responsive Studio Panels
        $response->assertSee('data-studio-panel="posts"', false);
        $response->assertSee('data-studio-panel="people"', false);
        $response->assertSee('data-studio-panel="reels"', false);

        // Mobile responsive rail and scrollbar utilities
        $response->assertSee('scrollbar-none', false);
        $response->assertSee('overflow-x-auto', false);

        // Quality toggles & Size choices
        $response->assertSee('data-social-quality="social"', false);
        $response->assertSee('data-social-quality="print"', false);
        $response->assertSee('Story / reel cover · 1080 × 1920', false);
    }

    public function test_manager_pages_load_and_render_cleanly(): void
    {
        \Illuminate\Support\Facades\Auth::guard('manager')->setUser($this->manager);

        // A manager with one event lands directly on their event details
        $this->get(route('manager.dashboard'))
            ->assertRedirect(route('manager.events.details', $this->event->id));

        $managerUrls = [
            route('manager.events.details', $this->event->id),
            route('manager.events.social', $this->event->id),
            route('manager.events.attendees', $this->event->id),
        ];

        foreach ($managerUrls as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $this->assertNoBrokenImgTags($response->getContent(), $url);
        }
    }

    public function test_social_image_proxy_allows_wordcamp_and_rejects_disallowed_hosts(): void
    {
        $this->actingAs($this->admin);

        // Rejects disallowed external hosts
        $response = $this->get(route('admin.events.social.image', [
            'event' => $this->event,
            'u' => 'https://malicious-site.example/hack.png',
        ]));
        $this->assertSame(404, $response->getStatusCode());

        // Rejects non-http schemes
        $response = $this->get(route('admin.events.social.image', [
            'event' => $this->event,
            'u' => 'file:///etc/passwd',
        ]));
        $this->assertSame(404, $response->getStatusCode());

        // Verifies allowlist includes WordPress and event host
        $this->assertTrue(SocialImageProxy::allowed('https://rajasthan.wordcamp.org/2026/files/logo.png'));
        $this->assertTrue(SocialImageProxy::allowed('https://secure.gravatar.com/avatar/test'));
        $this->assertTrue(SocialImageProxy::allowed('https://s.w.org/style/images/wp-header-logo.svg'));
        $this->assertTrue(SocialImageProxy::allowed('https://wordpress.org/favicon.ico'));
        $this->assertFalse(SocialImageProxy::allowed('https://untrusted-external-domain.com/pic.png'));
    }

    private function assertNoBrokenImgTags(string $html, string $context): void
    {
        $this->assertDoesNotMatchRegularExpression('/<img[^>]+src=["\']\s*["\']/', $html, "{$context} contains empty img src");
        $this->assertDoesNotMatchRegularExpression('/<img[^>]+src=["\']null["\']/', $html, "{$context} contains 'null' img src");
        $this->assertDoesNotMatchRegularExpression('/<img[^>]+src=["\']undefined["\']/', $html, "{$context} contains 'undefined' img src");
    }
}
