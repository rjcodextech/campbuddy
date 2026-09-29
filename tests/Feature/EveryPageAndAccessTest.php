<?php

namespace Tests\Feature;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\FreeSteal;
use App\Models\FreeStealSuggestion;
use App\Models\Offer;
use App\Models\OfferLead;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

/**
 * Every screen, read straight from the router so a new page can't be left out:
 *
 *  - every admin page opens for an admin, with real data on it, and every admin
 *    route (any method) turns a guest — and a signed-in event manager — away;
 *  - every manager page opens for a manager's own event, 404s for anyone else's,
 *    and turns a guest and an admin away;
 *  - every attendee page opens for a live event and is a 404 for a hidden or
 *    draft one.
 */
class EveryPageAndAccessTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    /** @var array<string, int|string> route parameter => value */
    private array $params;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake();

        $this->event = Event::create([
            'slug' => 'wc-jaipur', 'display_name' => 'WordCamp Jaipur 2026', 'source_site_url' => 'https://jaipur.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true, 'country_code' => 'IN',
            'starts_on' => now()->addDays(5)->toDateString(), 'ends_on' => now()->addDays(6)->toDateString(),
            'info' => ['venue' => 'RIC, Jaipur', 'wifi' => 'WCJ / camp', 'emergency_contact' => '+91 98765 43210', 'important_links' => 'https://jaipur.wordcamp.org/2026/'],
        ]);
        $offer = Offer::create(['event_id' => $this->event->id, 'title' => 'Own deal', 'description' => 'd', 'url' => 'https://a.example', 'is_active' => true, 'capture_leads' => true]);
        $deal = Offer::create(['event_id' => null, 'countries' => ['IN'], 'brand' => 'Knit Pay Pro', 'title' => 'Default deal', 'description' => "Line one\nLine two", 'url' => 'https://b.example', 'is_active' => true, 'capture_leads' => true]);
        OfferLead::create(['event_id' => $this->event->id, 'offer_id' => $deal->id, 'name' => 'Asha', 'email' => 'asha@example.com']);
        OfferLead::create(['event_id' => $this->event->id, 'offer_id' => $offer->id, 'name' => 'Ravi', 'email' => 'ravi@example.com']);
        $steal = FreeSteal::create(['name' => 'GoDAM', 'description' => 'Folders.', 'maker' => 'rtCamp', 'category' => 'Media', 'url' => 'https://github.com/rtCamp/godam', 'is_active' => true, 'is_featured' => true]);
        FreeStealSuggestion::create(['event_id' => $this->event->id, 'name' => 'Cool', 'url' => 'https://c.example']);
        $entry = AttendeeRoster::create(['event_id' => $this->event->id, 'name' => 'Asha Rao', 'links' => [['url' => 'https://github.com/asha', 'type' => 'website']], 'content_hash' => 'h1', 'is_suppressed' => false]);
        $quest = Quest::create(['event_id' => $this->event->id, 'source' => 'event', 'title' => 'Say hi', 'description' => 'To someone', 'sort_order' => 1, 'is_active' => true]);
        $manager = EventManager::factory()->create(['is_active' => true]);
        $manager->events()->attach($this->event->id);

        $this->params = [
            'event' => $this->event->id,
            'eventId' => $this->event->id,
            'offer' => $offer->id,
            'deal' => $deal->id,
            'free_steal' => $steal->id,
            'suggestion' => FreeStealSuggestion::value('id'),
            'event_manager' => $manager->id,
            'entry' => $entry->id,
            'quest' => $quest->id,
            'questId' => $quest->id,
            // Breeze's e-mail verification link.
            'id' => 1,
            'hash' => sha1('x'),
        ];
    }

    /** @return list<Route> */
    private function routes(callable $filter): array
    {
        return collect(Router::getRoutes()->getRoutes())->filter($filter)->values()->all();
    }

    private function url(Route $route, array $params = []): string
    {
        $params += $this->params;
        $url = '/'.ltrim($route->uri(), '/');

        foreach ($route->parameterNames() as $name) {
            $this->assertArrayHasKey($name, $params, "No test value for {{$name}} in {$route->uri()}");
            $url = str_replace(['{'.$name.'}', '{'.$name.'?}'], (string) $params[$name], $url);
        }

        return $url;
    }

    private static function isGet(Route $route): bool
    {
        return in_array('GET', $route->methods(), true);
    }

    private static function needsAdmin(Route $route): bool
    {
        return str_starts_with($route->uri(), 'admin') && in_array('auth', $route->gatherMiddleware(), true);
    }

    private static function needsManager(Route $route): bool
    {
        return str_starts_with($route->uri(), 'manager') && collect($route->gatherMiddleware())->contains(fn ($m) => str_contains((string) $m, 'EnsureEventManager'));
    }

    private static function firstMethod(Route $route): string
    {
        return collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
    }

    // ---- Admin --------------------------------------------------------------

    public function test_every_admin_page_opens_for_an_admin_with_data_on_it(): void
    {
        $this->actingAs(User::factory()->create());

        // Pages that are a one-off step of Breeze's own flows, not a screen of the panel.
        $flows = ['password.confirm', 'verification.notice', 'verification.verify'];
        $pages = $this->routes(fn (Route $r) => self::isGet($r) && self::needsAdmin($r) && ! in_array($r->getName(), $flows, true));

        $this->assertGreaterThan(20, count($pages), 'the admin pages were found');
        foreach ($pages as $route) {
            $response = $this->get($this->url($route));
            $this->assertSame(200, $response->baseResponse->getStatusCode(), "{$route->uri()} ({$route->getName()})");
        }
    }

    public function test_every_admin_route_turns_a_guest_away(): void
    {
        $routes = $this->routes(fn (Route $r) => self::needsAdmin($r));

        foreach ($routes as $route) {
            $response = $this->call(self::firstMethod($route), $this->url($route));
            $this->assertTrue($response->isRedirect(route('login')), "{$route->uri()} [".self::firstMethod($route)."] lets a guest in ({$response->status()})");
        }
    }

    public function test_a_signed_in_event_manager_is_a_guest_to_the_admin_panel(): void
    {
        Auth::guard('manager')->setUser(EventManager::first());

        foreach ($this->routes(fn (Route $r) => self::needsAdmin($r)) as $route) {
            $response = $this->call(self::firstMethod($route), $this->url($route));
            $this->assertTrue($response->isRedirect(route('login')), "{$route->uri()} lets a manager in ({$response->status()})");
        }
    }

    // ---- Event managers ------------------------------------------------------

    public function test_every_manager_page_opens_for_its_own_event_and_404s_for_another(): void
    {
        $other = Event::create(['slug' => 'wc-other', 'display_name' => 'Other', 'source_site_url' => 'https://other.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);
        $otherQuest = Quest::create(['event_id' => $other->id, 'source' => 'event', 'title' => 'x', 'description' => 'y', 'sort_order' => 1, 'is_active' => true]);
        Auth::guard('manager')->setUser(EventManager::first());

        $pages = $this->routes(fn (Route $r) => self::isGet($r) && self::needsManager($r));
        $this->assertGreaterThanOrEqual(4, count($pages));

        foreach ($pages as $route) {
            // A manager with one event lands straight on it.
            $response = $route->getName() === 'manager.dashboard' ? $this->followingRedirects()->get($this->url($route)) : $this->get($this->url($route));
            $this->assertSame(200, $response->status(), $route->uri());

            if (in_array('eventId', $route->parameterNames(), true)) {
                $this->assertSame(404, $this->get($this->url($route, ['eventId' => $other->id, 'questId' => $otherQuest->id]))->status(), "{$route->uri()} for someone else's event");
            }
        }
    }

    public function test_every_manager_route_turns_a_guest_and_an_admin_away(): void
    {
        $routes = $this->routes(fn (Route $r) => self::needsManager($r));

        foreach ($routes as $route) {
            $response = $this->call(self::firstMethod($route), $this->url($route));
            $this->assertTrue($response->isRedirect(route('manager.login')), "{$route->uri()} lets a guest in ({$response->status()})");
        }

        $this->actingAs(User::factory()->create());
        foreach ($routes as $route) {
            $response = $this->call(self::firstMethod($route), $this->url($route));
            $this->assertTrue($response->isRedirect(route('manager.login')), "{$route->uri()} lets an admin in ({$response->status()})");
        }
    }

    // ---- Attendees -------------------------------------------------------------

    /** @return list<Route> */
    private function eventPages(): array
    {
        return $this->routes(fn (Route $r) => self::isGet($r) && str_starts_with($r->uri(), 'event/{event}'));
    }

    public function test_every_attendee_page_opens_for_a_live_event(): void
    {
        $pages = $this->eventPages();
        $this->assertGreaterThanOrEqual(9, count($pages));

        foreach ($pages as $route) {
            $query = $route->getName() === 'event.roster-removal.search' ? '?name=Asha' : '';
            $this->assertSame(200, $this->get($this->url($route, ['event' => $this->event->slug]).$query)->status(), $route->uri());
        }

        foreach (['/', '/guide', '/robots.txt', '/sitemap.xml', '/llms.txt', '/manifest.webmanifest', '/admin/login', '/manager/login'] as $url) {
            $this->assertSame(200, $this->get($url)->status(), $url);
        }
    }

    public function test_a_hidden_or_draft_event_has_no_pages(): void
    {
        foreach ([['is_visible' => false], ['status' => 'draft']] as $i => $state) {
            $event = Event::create($state + ['slug' => "wc-closed-{$i}", 'display_name' => 'Closed', 'source_site_url' => "https://closed{$i}.wordcamp.org/2026", 'status' => 'active', 'is_visible' => true]);

            foreach ($this->eventPages() as $route) {
                $this->assertSame(404, $this->get($this->url($route, ['event' => $event->slug]))->status(), $route->uri().' '.json_encode($state));
            }
        }
    }

    public function test_every_attendee_page_carries_the_line_icon_sprite_and_no_emoji_controls(): void
    {
        foreach ($this->eventPages() as $route) {
            if (str_ends_with($route->uri(), 'manifest.json') || str_contains($route->uri(), 'roster-removal/search')) {
                continue;
            }
            $html = $this->get($this->url($route, ['event' => $this->event->slug]))->getContent();

            $this->assertStringContainsString('<symbol id="li-x"', $html, $route->uri());
            // Symbols that used to be icons, now line icons (↗ becomes a colour emoji on iPhones).
            $this->assertDoesNotMatchRegularExpression('/>\s*[×★☆‹›❚↗]\s*</u', $html, $route->uri());
        }
    }
}
