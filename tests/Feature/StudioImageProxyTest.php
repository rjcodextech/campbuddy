<?php

namespace Tests\Feature;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\User;
use App\Support\EventData;
use App\Support\SocialImageProxy;
use App\Support\SocialKit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verifies that the Studio Image Proxy accurately allowlists, fetches,
 * serves with CORS headers, caches, and prevents SSRF for posts, people cards, and reels.
 */
class StudioImageProxyTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

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
            'timezone' => 'Asia/Kolkata',
            'starts_on' => '2026-10-03',
            'ends_on' => '2026-10-04',
        ]);
    }

    public function test_proxy_allowed_hosts_whitelist_and_ssrf_rejection(): void
    {
        // Allowed WordCamp / WordPress / Gravatar hosts
        $this->assertTrue(SocialImageProxy::allowed('https://rajasthan.wordcamp.org/2026/files/logo.png'));
        $this->assertTrue(SocialImageProxy::allowed('https://secure.gravatar.com/avatar/abc123def456?s=600&d=404'));
        $this->assertTrue(SocialImageProxy::allowed('https://gravatar.com/avatar/test'));
        $this->assertTrue(SocialImageProxy::allowed('https://s.w.org/images/core/emoji/15.0.3/svg/1f389.svg'));
        $this->assertTrue(SocialImageProxy::allowed('https://i0.wp.com/rajasthan.wordcamp.org/2026/files/photo.webp'));
        $this->assertTrue(SocialImageProxy::allowed('https://wordpress.org/favicon.ico'));
        $this->assertTrue(SocialImageProxy::allowed('https://myblog.wordpress.com/header.jpg'));

        // App host
        config(['app.url' => 'https://campbuddy.club']);
        $this->assertTrue(SocialImageProxy::allowed('https://campbuddy.club/media/icons/icon-192.png'));
        $this->assertTrue(SocialImageProxy::allowed('https://sub.campbuddy.club/storage/art.png'));

        // Localhost in testing environment
        $this->assertTrue(SocialImageProxy::allowed('http://localhost:8000/media/logo.png'));
        $this->assertTrue(SocialImageProxy::allowed('http://127.0.0.1:8000/storage/banner.jpg'));

        // Prohibited malicious / SSRF / local network targets
        $this->assertFalse(SocialImageProxy::allowed('https://evil.example.com/malicious.png'));
        $this->assertFalse(SocialImageProxy::allowed('https://169.254.169.254/latest/meta-data/'));
        $this->assertFalse(SocialImageProxy::allowed('file:///etc/passwd'));
        $this->assertFalse(SocialImageProxy::allowed('data:image/png;base64,iVBORw0KGgo='));
        $this->assertFalse(SocialImageProxy::allowed('javascript:alert(1)'));
        $this->assertFalse(SocialImageProxy::allowed(''));
        $this->assertFalse(SocialImageProxy::allowed('not-a-valid-url'));
    }

    public function test_proxy_serves_image_with_cors_headers_and_caching(): void
    {
        Storage::fake('local');
        $imgData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        Http::fake([
            'https://rajasthan.wordcamp.org/*' => Http::response($imgData, 200, ['Content-Type' => 'image/png']),
            'https://secure.gravatar.com/*' => Http::response($imgData, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $admin = User::factory()->create();
        $this->actingAs($admin);

        // WordCamp image request
        $wcUrl = route('admin.events.social.image', $this->event).'?u='.urlencode('https://rajasthan.wordcamp.org/2026/files/logo.png');
        $res = $this->get($wcUrl);

        $res->assertOk();
        $res->assertHeader('Content-Type', 'image/png');
        $res->assertHeader('Access-Control-Allow-Origin', '*');
        $res->assertHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $this->assertTrue(str_contains($res->headers->get('Cache-Control', ''), 'max-age=86400'));
        $this->assertTrue(str_contains($res->headers->get('Cache-Control', ''), 'private'));
        $this->assertSame($imgData, $res->getContent());

        // Gravatar image request (critical for speaker photos on cards and reels)
        $gravatarUrl = route('admin.events.social.image', $this->event).'?u='.urlencode('https://secure.gravatar.com/avatar/abc123def456?s=600&d=404');
        $resGravatar = $this->get($gravatarUrl);

        $resGravatar->assertOk();
        $resGravatar->assertHeader('Content-Type', 'image/jpeg');
        $resGravatar->assertHeader('Access-Control-Allow-Origin', '*');
        $resGravatar->assertHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');

        // Disk cache test: Requesting the same URL again serves from disk without new HTTP calls
        $this->get($wcUrl)->assertOk();
        Http::assertSentCount(2); // exactly 1 for WC + 1 for Gravatar
    }

    public function test_proxy_blocks_non_images_oversized_payloads_and_evil_domains(): void
    {
        Storage::fake('local');

        Http::fake([
            'https://rajasthan.wordcamp.org/html' => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'text/html']),
            'https://rajasthan.wordcamp.org/large' => Http::response(str_repeat('A', 5 * 1024 * 1024), 200, ['Content-Type' => 'image/png']),
            'https://evil.example.com/*' => Http::response('EVIL', 200, ['Content-Type' => 'image/png']),
        ]);

        $admin = User::factory()->create();
        $this->actingAs($admin);

        // Evil domain -> 404
        $evilUrl = route('admin.events.social.image', $this->event).'?u='.urlencode('https://evil.example.com/logo.png');
        $this->get($evilUrl)->assertNotFound();

        // Non-image content type -> 404
        $htmlUrl = route('admin.events.social.image', $this->event).'?u='.urlencode('https://rajasthan.wordcamp.org/html');
        $this->get($htmlUrl)->assertNotFound();

        // Oversized (>4MB) -> 404
        $largeUrl = route('admin.events.social.image', $this->event).'?u='.urlencode('https://rajasthan.wordcamp.org/large');
        $this->get($largeUrl)->assertNotFound();
    }

    public function test_proxy_prewarming_caches_remote_images_locally(): void
    {
        Storage::fake('local');
        $imgData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        Http::fake([
            'https://rajasthan.wordcamp.org/s1.png' => Http::response($imgData, 200, ['Content-Type' => 'image/png']),
            'https://rajasthan.wordcamp.org/s2.png' => Http::response($imgData, 200, ['Content-Type' => 'image/png']),
            'https://evil.example.com/bad.png' => Http::response($imgData, 200, ['Content-Type' => 'image/png']),
        ]);

        $urls = [
            'https://rajasthan.wordcamp.org/s1.png',
            'https://rajasthan.wordcamp.org/s2.png',
            'https://evil.example.com/bad.png', // skipped (not allowed)
        ];

        $cached = SocialImageProxy::warm($urls);
        $this->assertSame(2, $cached);

        // Verify stored in local storage
        $disk = Storage::disk('local');
        $this->assertTrue($disk->exists('social-cache/'.sha1('https://rajasthan.wordcamp.org/s1.png')));
        $this->assertTrue($disk->exists('social-cache/'.sha1('https://rajasthan.wordcamp.org/s2.png')));
        $this->assertFalse($disk->exists('social-cache/'.sha1('https://evil.example.com/bad.png')));
    }

    public function test_admin_and_manager_social_studio_kit_contains_valid_proxy_and_media_urls(): void
    {
        EventData::put($this->event->id, 'speakers', [
            ['id' => 101, 'name' => 'Pooja Sharma', 'avatar_url' => 'https://secure.gravatar.com/avatar/aaa111?s=96&d=mm'],
        ]);
        EventData::put($this->event->id, 'sponsors', [
            ['id' => 201, 'name' => 'Gold Sponsor', 'logo_url' => 'https://rajasthan.wordcamp.org/2026/files/gold.png'],
        ]);

        $admin = User::factory()->create();
        $this->actingAs($admin);

        // Admin Social studio page
        $res = $this->get(route('admin.events.social', $this->event));
        $res->assertOk();
        $res->assertSee('cb-studio');

        $kit = SocialKit::data($this->event) + ['imageUrl' => route('admin.events.social.image', $this->event)];

        $this->assertSame(route('admin.events.social.image', $this->event), $kit['imageUrl']);
        $this->assertNotEmpty($kit['speakers']);
        $this->assertSame('Pooja Sharma', $kit['speakers'][0]['name']);
        // Verify bigPhoto transforms gravatar with d=404 for clean transparent/absent handling
        $this->assertStringContainsString('d=404', $kit['speakers'][0]['photo']);
        $this->assertStringContainsString('s=300', $kit['speakers'][0]['photo']);

        $this->assertNotEmpty($kit['sponsors']);
        $this->assertSame('https://rajasthan.wordcamp.org/2026/files/gold.png', $kit['sponsors'][0]['logo']);

        // Manager Social studio page
        $manager = EventManager::create([
            'name' => 'Mona Sharma',
            'email' => 'mona@example.test',
            'phone' => '+919800000000',
            'password' => 'secret-password-1',
            'is_active' => true,
        ]);
        $manager->events()->sync([$this->event->id]);

        $this->actingAs($manager, 'manager');
        $resManager = $this->get(route('manager.events.social', $this->event));
        $resManager->assertOk();
        $resManager->assertSee('cb-studio');

        $managerKit = SocialKit::data($this->event) + ['imageUrl' => route('manager.events.social.image', $this->event)];
        $this->assertSame(route('manager.events.social.image', $this->event), $managerKit['imageUrl']);
    }
}
