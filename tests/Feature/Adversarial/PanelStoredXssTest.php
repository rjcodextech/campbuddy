<?php

namespace Tests\Feature\Adversarial;

use App\Models\AppRelease;
use App\Models\AttendeeRoster;
use App\Models\ContributorTable;
use App\Models\DiscoveryProfile;
use App\Models\DiscoveryReport;
use App\Models\Event;
use App\Models\EventFeedback;
use App\Models\EventManager;
use App\Models\EventManagerChange;
use App\Models\FetchLog;
use App\Models\FreeSteal;
use App\Models\FreeStealSuggestion;
use App\Models\LegalDocument;
use App\Models\MediaAsset;
use App\Models\Offer;
use App\Models\OfferLead;
use App\Models\Quest;
use App\Models\SponsorDealSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Stored XSS, swept across every admin and event-manager page: hostile text in
 * every field that reaches them — what admins and managers type, and what
 * attendees, sponsors and scraped WordCamp sites send in (deal leads, feedback,
 * profile reports, Free Steal suggestions, sponsor submissions, the attendee
 * list, fetch errors) — must never come out as markup, and must never break
 * out of a JavaScript string inside an event-handler or Alpine attribute
 * (HTML-escaping alone does not stop that: the browser decodes &#039; back to
 * a quote before the script runs).
 */
class PanelStoredXssTest extends TestCase
{
    use RefreshDatabase;

    /** Markup that must never reach a page as markup. */
    private const MARKUP = ['<img src=x onerror=alert(91)>', '<svg onload=alert(92)>', '<script>alert(93)</script>'];

    /** Breaks out of a '…' or "…" JavaScript string once an attribute value is decoded. */
    private const JS_BREAKOUTS = ["'-alert(94)-'", '"-alert(95)-"'];

    private const HOSTILE = 'Evil<img src=x onerror=alert(91)>"><svg onload=alert(92)></script><script>alert(93)</script>\'-alert(94)-\'"-alert(95)-"';

    private Event $event;

    /** @var array<string, int|string> */
    private array $params;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake();
        Http::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->travelTo('2026-11-07 10:00:00');

        $h = self::HOSTILE;
        $this->event = Event::create([
            'slug' => 'wc-evil', 'display_name' => $h, 'short_name' => $h, 'source_site_url' => 'https://evil.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true, 'country_code' => 'IN',
            'starts_on' => now()->addDays(5)->toDateString(), 'ends_on' => now()->addDays(6)->toDateString(),
            'info' => ['venue' => $h, 'wifi' => $h, 'emergency_contact' => $h, 'important_links' => $h, 'social_event_info' => $h,
                'registration_info' => $h, 'contributor_day_location' => $h, 'nearby_venue_info' => $h],
        ]);
        $offerFields = ['brand' => $h, 'title' => $h, 'highlight' => $h, 'description' => $h, 'terms' => $h, 'coupon_code' => $h, 'cta_label' => $h, 'url' => 'https://a.example', 'is_active' => true, 'capture_leads' => true];
        $offer = Offer::create(['event_id' => $this->event->id] + $offerFields);
        $deal = Offer::create(['event_id' => null, 'countries' => ['IN']] + $offerFields);
        foreach ([$offer, $deal] as $o) {
            OfferLead::create(['event_id' => $this->event->id, 'offer_id' => $o->id, 'name' => $h, 'company' => $h, 'email' => $h, 'mobile' => $h, 'choices' => [$h]]);
        }
        $steal = FreeSteal::create(['name' => $h, 'description' => $h, 'maker' => $h, 'maker_links' => $h, 'category' => $h, 'cta_label' => $h, 'url' => 'https://github.com/x/y', 'is_active' => true]);
        $suggestion = FreeStealSuggestion::create(['event_id' => $this->event->id, 'name' => $h, 'maker' => $h, 'why' => $h, 'email' => 'a@example.com', 'url' => 'https://c.example']);
        $submission = SponsorDealSubmission::create([
            'event_id' => $this->event->id, 'sponsor_name' => $h, 'brand' => $h, 'website' => $h, 'title' => $h, 'highlight' => $h, 'description' => $h,
            'terms' => $h, 'coupon_code' => $h, 'cta_label' => $h, 'icon' => $h, 'url' => 'https://hostinger.example/deal',
            'submitter_name' => $h, 'submitter_email' => 'john@example.com', 'submitter_phone' => $h, 'notes' => $h, 'status' => 'pending', 'user_agent' => $h,
        ]);
        $entry = AttendeeRoster::create(['event_id' => $this->event->id, 'name' => $h, 'links' => [['url' => 'javascript:alert(96)', 'type' => 'website']], 'photo_url' => 'javascript:alert(97)', 'content_hash' => 'h1', 'is_suppressed' => false]);
        $quest = Quest::create(['event_id' => $this->event->id, 'source' => 'event', 'title' => $h, 'description' => $h, 'sort_order' => 1, 'is_active' => true]);
        $table = ContributorTable::create(['event_id' => $this->event->id, 'team' => 'other', 'title' => $h, 'track' => $h, 'floor' => $h, 'table_no' => $h, 'note' => $h, 'leads' => [['name' => $h]]]);
        $manager = EventManager::factory()->create(['name' => $h, 'phone' => '+91 98765 43210']);
        $manager->events()->attach($this->event->id);
        EventManagerChange::create(['event_manager_id' => $manager->id, 'manager_name' => $h, 'event_id' => $this->event->id, 'section' => 'details', 'action' => 'updated', 'summary' => $h]);
        $media = MediaAsset::create(['disk' => 'public', 'path' => 'media-library/2026/11/logo.png', 'filename' => $h, 'mime_type' => 'image/png', 'size' => 3]);
        EventFeedback::create(['event_id' => $this->event->id, 'device_hash' => str_repeat('d', 64), 'rating' => 1, 'comment' => $h]);
        $profile = DiscoveryProfile::create(['discovery_id' => str_repeat('b', 64), 'owner_token_hash' => str_repeat('0', 64), 'event_id' => $this->event->id, 'fields' => ['display_name' => $h, 'tags' => [$h]], 'expires_at' => now()->addWeek()]);
        DiscoveryReport::create(['event_id' => $this->event->id, 'discovery_id' => $profile->discovery_id, 'reporter_device_id' => 'd1', 'reason' => 'spam', 'note' => $h, 'snapshot' => ['name' => $h, 'display_name' => $h], 'status' => 'open']);
        FetchLog::create(['event_id' => $this->event->id, 'source' => 'rest', 'job_type' => 'sessions_speakers_sponsors', 'status' => 'failed', 'message' => $h, 'fetched_at' => now()]);
        LegalDocument::create(['slug' => 'privacy', 'title' => $h, 'meta_description' => $h, 'content' => '<p>Fine</p>']);
        Storage::disk('local')->put('app-releases/club.campbuddy.app/13-test.apk', 'apk');
        $release = AppRelease::create([
            'package' => 'club.campbuddy.app', 'version_code' => 13, 'version_name' => $h, 'file' => 'app-releases/club.campbuddy.app/13-test.apk',
            'size' => 3, 'sha256' => str_repeat('a', 64), 'cert_sha256' => str_repeat('b', 64), 'status' => AppRelease::DRAFT, 'notes_en' => $h, 'notes_hi' => $h,
        ]);

        $this->params = [
            'event' => $this->event->id, 'eventId' => $this->event->id, 'offer' => $offer->id, 'deal' => $deal->id, 'submission' => $submission->id,
            'free_steal' => $steal->id, 'media' => $media->id, 'suggestion' => $suggestion->id, 'event_manager' => $manager->id,
            'entry' => $entry->id, 'entryId' => $entry->id, 'quest' => $quest->id, 'questId' => $quest->id, 'table' => $table->id, 'tableId' => $table->id,
            'slug' => 'privacy', 'release' => $release->id,
        ];
    }

    /** @return list<string> */
    private function pages(string $prefix, callable $gate): array
    {
        return collect(Router::getRoutes()->getRoutes())
            ->filter(fn (Route $r) => in_array('GET', $r->methods(), true) && str_starts_with($r->uri(), $prefix) && $gate($r))
            // Endpoints for a canvas or a download, not pages; Breeze's one-off flow steps.
            ->reject(fn (Route $r) => preg_match('/(\.social\.image|\.social\.music|usage-post|\.export|\.download|password\.confirm|verification\.)/', (string) $r->getName()))
            ->map(function (Route $r) {
                $url = '/'.$r->uri();
                foreach ($r->parameterNames() as $name) {
                    $url = str_replace('{'.$name.'}', (string) $this->params[$name], $url);
                }

                return $url;
            })->values()->all();
    }

    private function assertNoScriptCanRun(string $html, string $where): void
    {
        foreach (self::MARKUP as $markup) {
            $this->assertStringNotContainsString($markup, $html, "{$where} prints {$markup} as markup");
        }

        // Attributes whose value the browser (or Alpine) runs as JavaScript, decoded as the browser would.
        preg_match_all('/\s((?:on[a-z]+|x-on:[\w.:-]+|@[\w.:-]+|x-data|x-init|x-effect|x-show|x-text|x-html|x-model|x-bind:[\w.-]+|:[\w.-]+))\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $value = html_entity_decode(($m[3] ?? '') !== '' ? $m[3] : ($m[4] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            foreach (self::JS_BREAKOUTS as $breakout) {
                $this->assertStringNotContainsString($breakout, $value, "{$where}: the {$m[1]} attribute lets text break out of its JavaScript string");
            }
        }

        // Inline scripts: data must be JSON- or @js-encoded, so a quote never arrives as a quote.
        preg_match_all('#<script\b[^>]*>(.*?)</script>#is', $html, $scripts);
        foreach ($scripts[1] as $script) {
            foreach (self::JS_BREAKOUTS as $breakout) {
                $this->assertStringNotContainsString($breakout, $script, "{$where}: an inline script carries the text unencoded");
            }
        }

        // No link or form target built from stored data that runs script (an <img src> never runs one).
        preg_match_all('/\s(?:href|action|formaction)\s*=\s*"([^"]*)"/i', $html, $urls);
        foreach ($urls[1] as $url) {
            $decoded = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
            $this->assertFalse((bool) preg_match('/^\s*(javascript|vbscript|data):/i', $decoded) && str_contains($decoded, 'alert(9'), "{$where} links to {$url}");
        }
    }

    public function test_hostile_text_never_runs_on_any_admin_page(): void
    {
        $this->actingAs(User::factory()->create(['name' => self::HOSTILE]));

        $pages = $this->pages('admin', fn (Route $r) => in_array('auth', $r->gatherMiddleware(), true));
        // Tabs and filters that show more of the same data.
        $pages = [...$pages, '/admin/mobile-app?tab=updates', '/admin/mobile-app?tab=history', '/admin/deal-submissions?status=all',
            '/admin/events?view=table', '/admin/events/'.$this->event->id.'/roster?q=Evil', '/admin/free-steals/create?suggestion='.$this->params['suggestion']];
        $this->assertGreaterThan(40, count($pages));

        foreach ($pages as $url) {
            $response = $this->followingRedirects()->get($url);
            $this->assertSame(200, $response->status(), "{$url} answered {$response->status()}");
            $this->assertNoScriptCanRun((string) $response->getContent(), $url);
        }
    }

    public function test_hostile_text_never_runs_on_any_manager_page(): void
    {
        Auth::guard('manager')->setUser(EventManager::firstOrFail());

        $pages = $this->pages('manager', fn (Route $r) => collect($r->gatherMiddleware())->contains(fn ($m) => str_contains((string) $m, 'EnsureEventManager')));
        $this->assertGreaterThan(10, count($pages));

        foreach ($pages as $url) {
            $response = $this->followingRedirects()->get($url);
            $this->assertSame(200, $response->status(), "{$url} answered {$response->status()}");
            $this->assertNoScriptCanRun((string) $response->getContent(), $url);
        }
    }

    public function test_a_rejected_legal_edit_shows_the_typed_html_cleaned_not_raw(): void
    {
        $this->actingAs(User::factory()->create());

        // The title is missing, so the form comes back with what was typed — which must not run.
        $this->from(route('admin.legal.edit', 'privacy'))
            ->post(route('admin.legal.update', 'privacy'), ['title' => '', 'content' => '<p>Kept</p><img src=x onerror=alert(91)><script>alert(93)</script>'])
            ->assertSessionHasErrors('title');

        $html = (string) $this->get(route('admin.legal.edit', 'privacy'))->assertOk()->getContent();

        $this->assertNoScriptCanRun($html, 'the legal editor after a failed save');
        $this->assertStringContainsString('<p>Kept</p>', $html);
    }

    public function test_admin_written_legal_html_is_stored_and_shown_without_script(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.legal.update', 'privacy'), [
            'title' => 'Privacy <script>alert(93)</script>',
            'meta_description' => '"><svg onload=alert(92)>',
            'content' => '<h2>Who</h2><p onclick="alert(1)">Us</p><a href="javascript:alert(1)">x</a><iframe src="//evil.example"></iframe><style>body{display:none}</style><svg onload=alert(92)></svg>',
        ])->assertRedirect(route('admin.legal.index'));

        $stored = LegalDocument::where('slug', 'privacy')->value('content');
        $this->assertSame('<h2>Who</h2><p>Us</p><a>x</a>', $stored);

        foreach ([route('admin.legal.edit', 'privacy'), route('admin.legal.index')] as $url) {
            $this->assertNoScriptCanRun((string) $this->get($url)->assertOk()->getContent(), $url);
        }

        // The public page, which also cleans on the way out.
        auth()->logout();
        $page = (string) $this->get(route('privacy'))->assertOk()->getContent();
        $this->assertNoScriptCanRun($page, '/privacy');
        $this->assertStringContainsString('<h2>Who</h2>', $page);
    }
}
