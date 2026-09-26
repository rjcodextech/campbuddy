<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventManager;
use App\Models\Quest;
use App\Models\User;
use App\Rules\WordCampUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Trying to break the event manager feature — the ways a lower-trust account,
 * or someone with no account, could do more than they should: forge requests,
 * guess accounts, over-post fields, reach other events, or put script into
 * text that ten thousand attendees will open.
 */
class EventManagerSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** Text a browser must never treat as markup or script. */
    private const PAYLOADS = [
        '<script>alert(1)</script>',
        '"><img src=x onerror=alert(1)>',
        '</script><script>alert(1)</script>',
        "'; alert(1); //",
        '<svg/onload=alert(1)>',
        '{{ 7*7 }}',
        '{!! "injected" !!}',
        '@php echo "injected"; @endphp',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake();
    }

    private function event(string $slug, array $overrides = []): Event
    {
        return Event::create($overrides + [
            'slug' => $slug,
            'display_name' => 'WordCamp '.ucfirst(str_replace('wc-', '', $slug)),
            'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'active',
            'is_visible' => true,
            'starts_on' => now()->addDays(5)->toDateString(),
        ]);
    }

    /** @param  array<int, Event>  $events */
    private function manager(array $events, array $attributes = []): EventManager
    {
        $manager = EventManager::factory()->create($attributes);
        $manager->events()->attach(collect($events)->map->id->all());

        return $manager;
    }

    private function signInAs(EventManager $manager): void
    {
        Auth::guard('manager')->setUser($manager);
    }

    /** Makes the framework enforce CSRF, which it skips while running tests. */
    private function enforceCsrf(): void
    {
        $this->app['env'] = 'production';
    }

    private function details(array $overrides = []): array
    {
        return $overrides + [
            'display_name' => 'WordCamp Alpha', 'slug' => 'wc-alpha', 'short_name' => null,
            'source_site_url' => 'https://wc-alpha.wordcamp.org/2026', 'is_visible' => '1',
        ];
    }

    // ---- Forged requests --------------------------------------------------

    public function test_a_post_without_the_csrf_token_is_refused_everywhere(): void
    {
        $event = $this->event('wc-alpha');
        $manager = $this->manager([$event, $this->event('wc-beta')], ['password' => 'a-strong-password']);
        $quest = $event->quests()->firstOrFail();

        $this->enforceCsrf();

        // The sign-in form itself.
        $this->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'a-strong-password'])->assertStatus(419);
        $this->assertGuest('manager');

        // Every action of a signed-in manager.
        $this->signInAs($manager);
        $this->post(route('manager.logout'))->assertStatus(419);
        $this->put(route('manager.events.details.update', $event), $this->details(['display_name' => 'Forged']))->assertStatus(419);
        $this->put(route('manager.events.information.update', $event), ['venue' => 'Forged'])->assertStatus(419);
        $this->post(route('manager.events.quests.store', $event), ['title' => 'Forged'])->assertStatus(419);
        $this->put(route('manager.events.quests.update', [$event, $quest->id]), ['title' => 'Forged'])->assertStatus(419);
        $this->delete(route('manager.events.quests.destroy', [$event, $quest->id]))->assertStatus(419);

        $this->assertSame('WordCamp Alpha', $event->fresh()->display_name);
        $this->assertNull($event->fresh()->info);
        $this->assertNotNull(Quest::find($quest->id));
        $this->assertSame(0, $event->quests()->where('title', 'Forged')->count());
    }

    public function test_the_same_requests_with_the_token_go_through(): void
    {
        $event = $this->event('wc-alpha');
        $manager = $this->manager([$event, $this->event('wc-beta')]);

        $this->enforceCsrf();
        $this->signInAs($manager);

        $this->withSession(['_token' => 'known-token'])
            ->post(route('manager.events.quests.store', $event), ['title' => 'Real one', '_token' => 'known-token'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(1, $event->quests()->where('title', 'Real one')->count());
    }

    public function test_every_manager_route_is_behind_the_web_group_and_the_manager_gate(): void
    {
        $public = ['manager.login', 'manager.login.store'];
        $seen = 0;

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! is_string($name) || ! str_starts_with($name, 'manager.')) {
                continue;
            }
            $seen++;
            $middleware = $route->gatherMiddleware();

            $this->assertContains('web', $middleware, "{$name} must be in the web group (sessions + CSRF).");
            $this->assertNotContains('web', $route->excludedMiddleware(), "{$name} must not opt out of sessions/CSRF.");
            $this->assertNotContains('auth', $middleware, "{$name} must use the manager's own gate, not the admin's.");

            if (in_array($name, $public, true)) {
                continue;
            }

            $this->assertContains(\App\Http\Middleware\EnsureEventManager::class, $middleware, "{$name} needs a signed-in manager.");

            // Anything that changes something is not a GET and is throttled per manager.
            $changes = array_diff($route->methods(), ['GET', 'HEAD']) !== [];
            if ($changes) {
                $this->assertContains(\App\Http\Middleware\ThrottleManagerWrites::class, $middleware, "{$name} changes data and must be throttled.");
            }
        }

        $this->assertGreaterThanOrEqual(10, $seen);

        // …and the web group really carries the CSRF check.
        $group = collect($this->app->make(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web'])->implode(' ');
        $this->assertStringContainsString('ValidateCsrfToken', $group);
    }

    public function test_wrong_http_methods_are_refused_not_run(): void
    {
        $event = $this->event('wc-alpha');
        $this->signInAs($this->manager([$event, $this->event('wc-beta')]));

        $this->get(route('manager.logout'))->assertStatus(405);
        $this->post("/manager/events/{$event->id}")->assertStatus(405);
        $this->patch("/manager/events/{$event->id}", ['display_name' => 'Patched'])->assertStatus(405);
        $this->delete("/manager/events/{$event->id}")->assertStatus(405);
        $this->get("/manager/events/{$event->id}/quests/1")->assertStatus(405);

        $this->assertSame('WordCamp Alpha', $event->fresh()->display_name);
        $this->assertNotNull($event->fresh());
    }

    // ---- Signing in -------------------------------------------------------

    public function test_signing_in_starts_a_new_session(): void
    {
        $manager = $this->manager([], ['password' => 'a-strong-password']);

        $this->get(route('manager.login'));
        $before = session()->getId();

        $this->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'a-strong-password'])->assertRedirect();

        $this->assertNotSame($before, session()->getId(), 'The id from before signing in is useless afterwards (session fixation).');
    }

    public function test_the_login_answers_the_same_for_no_account_a_wrong_password_and_a_switched_off_account(): void
    {
        $real = EventManager::factory()->create(['email' => 'real@example.com', 'password' => 'a-strong-password']);
        $off = EventManager::factory()->disabled()->create(['email' => 'off@example.com', 'password' => 'a-strong-password']);

        $answers = [];
        foreach ([
            'unknown' => ['nobody@example.com', 'a-strong-password'],
            'wrong password' => [$real->email, 'not-the-password'],
            'switched off' => [$off->email, 'a-strong-password'],
        ] as $case => [$email, $password]) {
            $response = $this->from(route('manager.login'))->post(route('manager.login.store'), ['email' => $email, 'password' => $password]);
            $answers[$case] = [$response->getStatusCode(), $response->headers->get('Location'), session('errors')->first('email')];
            $this->assertGuest('manager');
        }

        $this->assertCount(1, array_unique(array_map('json_encode', $answers)), 'Nothing in the answer tells which case it was: '.json_encode($answers));
        $this->assertSame('These credentials do not match our records.', $answers['unknown'][2]);
    }

    public function test_an_address_that_has_no_account_is_hashed_for_too_so_the_time_it_takes_says_nothing(): void
    {
        $off = EventManager::factory()->disabled()->create(['email' => 'off@example.com', 'password' => 'a-strong-password']);
        $real = EventManager::factory()->create(['email' => 'real@example.com', 'password' => 'a-strong-password']);

        $hash = Hash::spy();

        $this->post(route('manager.login.store'), ['email' => 'nobody@example.com', 'password' => 'guess']);
        $hash->shouldHaveReceived('make')->times(1);

        $this->post(route('manager.login.store'), ['email' => $off->email, 'password' => 'guess']);
        $hash->shouldHaveReceived('make')->times(2);

        // A real, active account is checked against its own hash instead — no extra work.
        $this->post(route('manager.login.store'), ['email' => $real->email, 'password' => 'guess']);
        $hash->shouldHaveReceived('make')->times(2);
    }

    public function test_guessing_an_address_that_has_no_account_is_limited_just_the_same(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post(route('manager.login.store'), ['email' => 'nobody@example.com', 'password' => 'guess-'.$i]);
        }

        $this->post(route('manager.login.store'), ['email' => 'nobody@example.com', 'password' => 'guess-6'])->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
    }

    public function test_one_locked_out_address_does_not_lock_out_the_others_at_the_same_venue(): void
    {
        $venue = ['REMOTE_ADDR' => '203.0.113.9'];

        foreach (range(1, 5) as $i) {
            $this->withServerVariables($venue)->post(route('manager.login.store'), ['email' => 'target@example.com', 'password' => 'guess-'.$i]);
        }

        // A dozen organizers on the same wifi, all signing in within the minute, all get in.
        foreach (range(1, 12) as $i) {
            $manager = EventManager::factory()->create(['email' => "organizer{$i}@example.com", 'password' => 'a-strong-password']);
            $this->app['auth']->forgetGuards();

            $this->withServerVariables($venue)
                ->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'a-strong-password'])
                ->assertRedirect(route('manager.dashboard'));
        }
    }

    public function test_a_wrong_password_is_never_sent_back_in_a_page_or_kept_in_the_session(): void
    {
        $manager = $this->manager([], ['password' => 'a-strong-password']);

        $this->from(route('manager.login'))->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'my-secret-guess-9'])
            ->assertRedirect(route('manager.login'));

        $page = $this->get(route('manager.login'))->assertOk()->getContent();

        $this->assertStringNotContainsString('my-secret-guess-9', $page);
        $this->assertNull(old('password'));
        $this->assertStringNotContainsString('my-secret-guess-9', json_encode(session()->all()));
    }

    public function test_a_sign_in_never_follows_an_address_the_visitor_supplies(): void
    {
        $manager = $this->manager([], ['password' => 'a-strong-password']);

        foreach (['redirect', 'intended', 'return', 'next', 'url', 'redirect_to'] as $key) {
            $this->app['auth']->forgetGuards();

            $this->post(route('manager.login.store').'?'.$key.'='.urlencode('https://evil.example/phish'), [
                'email' => $manager->email, 'password' => 'a-strong-password', $key => 'https://evil.example/phish',
            ])->assertRedirect(route('manager.dashboard'));
        }
    }

    public function test_the_page_a_manager_was_sent_back_to_is_always_one_of_ours(): void
    {
        $event = $this->event('wc-alpha');
        $manager = $this->manager([$event, $this->event('wc-beta')], ['password' => 'a-strong-password']);

        // Asked for while signed out, on a host header someone made up.
        $this->get(route('manager.events.quests', $event), ['Host' => 'evil.example'])->assertRedirect();

        $response = $this->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'a-strong-password']);

        $this->assertStringStartsWith(url('/'), (string) $response->headers->get('Location'));
    }

    // ---- What a manager can and cannot set --------------------------------

    public function test_extra_fields_sent_with_a_form_change_nothing(): void
    {
        $event = $this->event('wc-alpha', ['logo_path' => 'branding/1/logo.png', 'info_fetched' => ['venue' => 'Fetched'], 'timezone_locked' => false]);
        $manager = $this->manager([$event, $this->event('wc-beta')]);
        $this->signInAs($manager);

        $extras = [
            'id' => 999, 'status' => 'archived', 'logo_path' => 'evil.png', 'favicon_path' => 'evil.ico', 'info' => ['venue' => 'Evil'],
            'info_fetched' => ['venue' => 'Evil'], 'info_fetched_at' => '2000-01-01', 'timezone_locked' => '1', 'created_at' => '2000-01-01', 'updated_at' => '2000-01-01',
        ];

        $this->put(route('manager.events.details.update', $event), $this->details(['display_name' => 'Renamed']) + $extras)->assertSessionHasNoErrors();
        $this->put(route('manager.events.information.update', $event), ['venue' => 'Real venue'] + $extras)->assertSessionHasNoErrors();

        $event->refresh();
        $this->assertSame('Renamed', $event->display_name);
        $this->assertSame(['venue' => 'Real venue'], $event->info);
        $this->assertSame('active', $event->status);
        $this->assertSame('branding/1/logo.png', $event->logo_path);
        $this->assertNull($event->favicon_path);
        $this->assertSame(['venue' => 'Fetched'], $event->info_fetched);
        $this->assertNull($event->info_fetched_at);
        $this->assertFalse($event->timezone_locked, 'Only typing a zone locks it.');
        $this->assertNotSame(999, $event->id);
    }

    public function test_a_quest_stays_in_its_event_and_keeps_its_kind_whatever_is_sent(): void
    {
        $mine = $this->event('wc-alpha');
        $theirs = $this->event('wc-beta');
        $this->signInAs($this->manager([$mine, $this->event('wc-extra')]));

        $this->post(route('manager.events.quests.store', $mine), ['title' => 'Mine', 'event_id' => $theirs->id, 'source' => 'default', 'id' => 1])->assertSessionHasNoErrors();

        $created = Quest::where('title', 'Mine')->firstOrFail();
        $this->assertSame($mine->id, $created->event_id);
        $this->assertSame('event', $created->source);
        $this->assertNotSame(1, $created->id);

        $this->put(route('manager.events.quests.update', [$mine, $created->id]), ['title' => 'Mine', 'event_id' => $theirs->id, 'source' => 'default'])->assertSessionHasNoErrors();
        $this->assertSame($mine->id, $created->fresh()->event_id);
        $this->assertSame('event', $created->fresh()->source);
        $this->assertSame(0, $theirs->quests()->where('title', 'Mine')->count());
    }

    public function test_the_shared_things_to_do_cards_cannot_be_reached_through_an_event(): void
    {
        $event = $this->event('wc-alpha');
        $this->signInAs($this->manager([$event, $this->event('wc-beta')]));

        $shared = Quest::whereNull('event_id')->firstOrFail();

        $this->put(route('manager.events.quests.update', [$event, $shared->id]), ['title' => 'Hijacked'])->assertNotFound();
        $this->delete(route('manager.events.quests.destroy', [$event, $shared->id]))->assertNotFound();

        $this->assertNotSame('Hijacked', $shared->fresh()->title);
        $this->assertNotNull(Quest::find($shared->id));
    }

    // ---- The source address ----------------------------------------------

    /** @return array<string, array{0: string, 1: bool}> */
    public static function sourceAddresses(): array
    {
        return [
            'a WordCamp site' => ['https://rajasthan.wordcamp.org/2026', true],
            'the bare domain' => ['https://wordcamp.org/', true],
            'upper case' => ['https://Rajasthan.WordCamp.ORG/2026', true],
            'a deeper subdomain' => ['https://2026.rajasthan.wordcamp.org/', true],
            'with a query string' => ['https://central.wordcamp.org/x?y=1#z', true],
            'the standard port' => ['https://rajasthan.wordcamp.org:443/2026', true],
            'another domain' => ['https://evil.example/2026', false],
            'wordcamp.org as a subdomain of another domain' => ['https://wordcamp.org.evil.example/', false],
            'wordcamp.org at the end of a longer word' => ['https://evilwordcamp.org/', false],
            'wordcamp.org in the path' => ['https://evil.example/wordcamp.org', false],
            'login details that hide the real host' => ['https://rajasthan.wordcamp.org@evil.example/', false],
            'login details on a real host' => ['https://user:pass@rajasthan.wordcamp.org/', false],
            'plain http' => ['http://rajasthan.wordcamp.org/2026', false],
            'another port' => ['https://rajasthan.wordcamp.org:8443/', false],
            'an address of the machine itself' => ['https://127.0.0.1/', false],
            'a private address' => ['https://10.0.0.5/wordcamp.org', false],
            'another scheme' => ['ftp://rajasthan.wordcamp.org/', false],
            'a script' => ['javascript:alert(1)', false],
            'no scheme' => ['//rajasthan.wordcamp.org/', false],
            'no scheme at all' => ['rajasthan.wordcamp.org', false],
            'a trailing dot' => ['https://rajasthan.wordcamp.org./', false],
            'a look-alike letter' => ['https://rajasthan.wordcamp.оrg/', false],
            'nothing' => ['', false],
        ];
    }

    /** @dataProvider sourceAddresses */
    #[\PHPUnit\Framework\Attributes\DataProvider('sourceAddresses')]
    public function test_only_wordcamp_org_addresses_pass_the_manager_rule(string $address, bool $allowed): void
    {
        $failed = false;
        (new WordCampUrl)->validate('source_site_url', $address, function () use (&$failed) {
            $failed = true;
        });

        $this->assertSame($allowed, ! $failed, "“{$address}”");
    }

    public function test_the_rule_lets_an_address_an_admin_set_stay_as_it_is_but_not_change(): void
    {
        $rule = new WordCampUrl('https://custom.example/wc');
        $check = function (string $value) use ($rule): bool {
            $failed = false;
            $rule->validate('source_site_url', $value, function () use (&$failed) {
                $failed = true;
            });

            return ! $failed;
        };

        $this->assertTrue($check('https://custom.example/wc'), 'Saving the form without touching the field.');
        $this->assertFalse($check('https://custom.example/other'));
        $this->assertFalse($check('https://another.example/wc'));
        $this->assertTrue($check('https://rajasthan.wordcamp.org/2026'));
    }

    public function test_a_manager_can_not_point_an_event_at_another_site_but_an_admin_can(): void
    {
        $event = $this->event('wc-alpha');
        $manager = $this->manager([$event, $this->event('wc-beta')]);
        $this->signInAs($manager);

        $this->put(route('manager.events.details.update', $event), $this->details(['source_site_url' => 'https://evil.example/2026']))
            ->assertSessionHasErrors('source_site_url');
        // …refused for the right reason (not merely because this machine can't resolve the name).
        $this->assertStringContainsString('must be an https address on wordcamp.org', session('errors')->first('source_site_url'));
        $this->assertSame('https://wc-alpha.wordcamp.org/2026', $event->fresh()->source_site_url);

        // The admin's own form is not held to it.
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::factory()->create());
        $this->put(route('admin.events.update', $event), [
            'slug' => 'wc-alpha', 'display_name' => 'WordCamp Alpha', 'source_site_url' => 'https://example.org/wc', 'status' => 'active', 'is_visible' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame('https://example.org/wc', $event->fresh()->source_site_url);

        // …and the manager can still save the *other* fields of an event that already has such an address.
        $this->app['auth']->forgetGuards();
        $this->signInAs($manager);
        $this->put(route('manager.events.details.update', $event), $this->details(['display_name' => 'Renamed', 'source_site_url' => 'https://example.org/wc']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $event->fresh()->display_name);
    }

    // ---- Text that reaches attendees -------------------------------------

    public function test_names_with_markup_or_line_breaks_are_refused(): void
    {
        $event = $this->event('wc-alpha');
        $this->signInAs($this->manager([$event, $this->event('wc-beta')]));

        foreach (['<script>alert(1)</script>', 'Name <b>bold</b>', "Two\nlines", "Tab\there", 'Link ](https://evil.example) [x', "Null\0byte"] as $bad) {
            $this->put(route('manager.events.details.update', $event), $this->details(['display_name' => $bad]))->assertSessionHasErrors('display_name');
            $this->put(route('manager.events.details.update', $event), $this->details(['short_name' => $bad]))->assertSessionHasErrors('short_name');
        }

        $this->assertSame('WordCamp Alpha', $event->fresh()->display_name);
    }

    public function test_ordinary_punctuation_in_a_name_is_kept_and_escaped_where_it_is_shown(): void
    {
        $event = $this->event('wc-alpha');
        $this->signInAs($this->manager([$event, $this->event('wc-beta')]));

        $name = 'Tom & Jerry\'s "WordCamp" (2026) — Jaipur';
        $this->put(route('manager.events.details.update', $event), $this->details(['display_name' => $name, 'short_name' => '#WC&Co']))->assertSessionHasNoErrors();
        $this->assertSame($name, $event->fresh()->display_name);

        $home = $this->get(route('event.home', $event))->assertOk()->getContent();
        $this->assertStringContainsString('Tom &amp; Jerry&#039;s &quot;WordCamp&quot; (2026) — Jaipur', $home);
        $this->assertStringNotContainsString('Tom & Jerry\'s "WordCamp"', $home);
    }

    /** @return array<string, array{0: string}> */
    public static function payloads(): array
    {
        return array_combine(self::PAYLOADS, array_map(fn ($p) => [$p], self::PAYLOADS));
    }

    /** @dataProvider payloads */
    #[\PHPUnit\Framework\Attributes\DataProvider('payloads')]
    public function test_script_typed_into_any_event_text_never_runs_on_an_attendee_page(string $payload): void
    {
        $event = $this->event('wc-alpha');
        $this->signInAs($this->manager([$event, $this->event('wc-beta')]));

        $this->put(route('manager.events.information.update', $event), [
            'venue' => $payload, 'wifi' => $payload, 'registration_info' => $payload, 'contributor_day_location' => $payload,
            'social_event_info' => $payload, 'nearby_venue_info' => $payload, 'emergency_contact' => $payload,
            'important_links' => $payload."\nLabel: https://ok.example/page",
        ])->assertSessionHasNoErrors();
        $this->post(route('manager.events.quests.store', $event), ['title' => $payload, 'description' => $payload])->assertSessionHasNoErrors();

        Auth::forgetGuards();

        $pages = [
            route('home'), route('event.home', $event), route('event.my-day', $event), route('event.quest', $event), route('event.contribute', $event),
            route('event.explore', $event), route('event.camp-card', $event), route('event.guide', $event),
        ];

        foreach ($pages as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            foreach (['<script>alert(1)</script>', '<img src=x', '<svg/onload', 'onerror=alert(1)>', '<script>alert', "injected\n"] as $raw) {
                $this->assertStringNotContainsString($raw, $html, "{$url} printed “{$raw}” as markup.");
            }
            // The template engine never runs what a person typed.
            $this->assertDoesNotMatchRegularExpression('/(?<![\w"{])49(?![\w"])(?=.{0,40}\{\{)/s', $html);
            $this->assertDoesNotMatchRegularExpression('/href="\s*(?:javascript|data|vbscript):/i', $html, "{$url} links to a script.");
        }

        // What was typed is still there, as text.
        $explore = $this->get(route('event.explore', $event))->getContent();
        $this->assertStringContainsString(e($payload), $explore);

        // In the Quest page's data block it survives the round trip, and cannot close the block.
        $quest = $this->get(route('event.quest', $event))->getContent();
        preg_match('#<script type="application/json" id="quest-data">(.*?)</script>#s', $quest, $m);
        $this->assertContains($payload, array_column(json_decode($m[1], true, 512, JSON_THROW_ON_ERROR), 'title'));
    }

    public function test_only_web_addresses_and_real_phone_numbers_become_links(): void
    {
        $event = $this->event('wc-alpha');
        $this->signInAs($this->manager([$event, $this->event('wc-beta')]));

        $this->put(route('manager.events.information.update', $event), [
            'important_links' => "javascript:alert(1)\nJS: javascript:alert(2)\ndata:text/html,<b>x</b>\nvbscript:x\nftp://files.example/x\nhttps://safe.example/a\nhttp://also.example/b",
            'emergency_contact' => 'javascript:alert(3)',
            'code_of_conduct_url' => 'https://safe.example/coc',
        ])->assertSessionHasNoErrors();

        Auth::forgetGuards();
        $html = $this->get(route('event.explore', $event))->assertOk()->getContent();

        preg_match_all('#<a class="useful-link" href="([^"]*)"#', $html, $m);
        $hrefs = $m[1];

        $this->assertContains('https://safe.example/a', $hrefs);
        $this->assertContains('http://also.example/b', $hrefs);
        $this->assertContains('https://safe.example/coc', $hrefs);
        foreach ($hrefs as $href) {
            $this->assertMatchesRegularExpression('#^(https?://|tel:|mailto:)#i', $href, "“{$href}” is not a safe link.");
        }
    }

    public function test_a_code_of_conduct_link_must_be_a_web_address(): void
    {
        $event = $this->event('wc-alpha');
        $this->signInAs($this->manager([$event, $this->event('wc-beta')]));

        foreach (['javascript:alert(1)', 'data:text/html,x', 'ftp://x.example/', 'not a url'] as $bad) {
            $this->put(route('manager.events.information.update', $event), ['code_of_conduct_url' => $bad])->assertSessionHasErrors('code_of_conduct_url');
        }
    }

    public function test_a_manager_name_typed_by_an_admin_is_escaped_in_every_admin_page(): void
    {
        $this->actingAs(User::factory()->create());
        $event = $this->event('wc-alpha');
        $manager = $this->manager([$event], ['name' => '<script>alert(1)</script>Asha']);
        \App\Support\ManagerActivity::record($manager, $event, 'quests', 'added', 'Added “<img src=x onerror=alert(1)>”');

        foreach ([route('admin.event-managers.index'), route('admin.event-managers.edit', $manager), route('admin.event-managers.activity')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html, $url);
            $this->assertStringNotContainsString('<img src=x', $html, $url);
        }
    }

    // ---- Reaching things that are not theirs -----------------------------

    public function test_odd_ids_are_not_found_never_an_error(): void
    {
        $this->signInAs($this->manager([$this->event('wc-alpha'), $this->event('wc-beta')]));

        foreach (['0', '-1', '1e3', '1.5', '999999999999999999999', 'abc', "1'%20OR%20'1'='1", '1;DROP%20TABLE%20events', '%00', '..%2F..%2Fadmin', '0x1'] as $id) {
            foreach (['', '/information', '/quests'] as $suffix) {
                $status = $this->get("/manager/events/{$id}{$suffix}")->getStatusCode();
                $this->assertSame(404, $status, "/manager/events/{$id}{$suffix} answered {$status}");
            }
        }

        $this->assertSame(2, Event::count());
    }

    public function test_sql_in_a_sign_in_field_is_just_text(): void
    {
        $manager = $this->manager([], ['password' => 'a-strong-password']);

        foreach (["' OR '1'='1", "admin'--", "x@example.com' OR 1=1 --", "\" OR \"\"=\""] as $attack) {
            $this->post(route('manager.login.store'), ['email' => $attack, 'password' => $attack])->assertSessionHasErrors('email');
            $this->assertGuest('manager');
        }

        $this->assertSame(1, EventManager::count());
    }

    public function test_oversized_input_is_refused_not_stored_and_does_not_hang(): void
    {
        $event = $this->event('wc-alpha');
        $this->signInAs($this->manager([$event, $this->event('wc-beta')]));

        $this->put(route('manager.events.information.update', $event), ['venue' => str_repeat('x', 100000), 'important_links' => str_repeat('https://a.example/x ', 5000)])
            ->assertSessionHasErrors(['venue', 'important_links']);
        $this->post(route('manager.events.quests.store', $event), ['title' => str_repeat('x', 300), 'description' => str_repeat('y', 5000)])
            ->assertSessionHasErrors(['title', 'description']);
        $this->put(route('manager.events.details.update', $event), $this->details(['display_name' => str_repeat('n', 300)]))->assertSessionHasErrors('display_name');

        $started = microtime(true);
        $this->post(route('manager.login.store'), ['email' => 'a@example.com', 'password' => str_repeat('p', 100000)])->assertSessionHasErrors('password');
        $this->assertLessThan(2.0, microtime(true) - $started, 'A huge password is refused before any hashing.');

        $this->assertNull($event->fresh()->info);
        $this->assertSame(0, $event->quests()->whereRaw('length(title) > 255')->count());
    }

    public function test_a_password_an_admin_sets_has_sane_limits(): void
    {
        $this->actingAs(User::factory()->create());
        $event = $this->event('wc-alpha');
        $form = ['name' => 'Asha', 'email' => 'asha@example.com', 'phone' => '+91 98765 43210', 'events' => [$event->id]];

        $this->post(route('admin.event-managers.store'), $form + ['password' => 'short'])->assertSessionHasErrors('password');
        $this->post(route('admin.event-managers.store'), $form + ['password' => str_repeat('a', 73)])->assertSessionHasErrors('password');
        $this->post(route('admin.event-managers.store'), $form + ['password' => str_repeat('a', 72)])->assertSessionHasNoErrors();

        $manager = EventManager::firstOrFail();
        $this->assertStringStartsWith('$2y$', $manager->password, 'Stored hashed, never as typed.');
    }

    public function test_the_admin_form_cannot_be_used_to_set_internal_columns(): void
    {
        $this->actingAs(User::factory()->create());
        $event = $this->event('wc-alpha');

        $this->post(route('admin.event-managers.store'), [
            'name' => 'Asha', 'email' => 'asha@example.com', 'phone' => '+91 98765 43210', 'password' => 'a-strong-password', 'events' => [$event->id],
            'id' => 777, 'remember_token' => 'planted', 'last_login_at' => '2000-01-01', 'created_at' => '2000-01-01',
        ])->assertSessionHasNoErrors();

        $manager = EventManager::firstOrFail();
        $this->assertNotSame(777, $manager->id);
        $this->assertNotSame('planted', $manager->remember_token);
        $this->assertNull($manager->last_login_at);
    }

    // ---- What the browser is told ----------------------------------------

    public function test_manager_pages_are_never_stored_by_the_browser_or_a_proxy(): void
    {
        $event = $this->event('wc-alpha');
        $this->signInAs($this->manager([$event, $this->event('wc-beta')]));

        foreach ([route('manager.dashboard'), route('manager.events.details', $event), route('manager.events.information', $event), route('manager.events.quests', $event)] as $url) {
            $response = $this->get($url);
            $cache = (string) $response->headers->get('Cache-Control');

            $this->assertStringContainsString('no-store', $cache, $url);
            $this->assertStringContainsString('private', $cache, $url);
        }
    }

    public function test_manager_pages_carry_the_site_wide_security_headers(): void
    {
        $event = $this->event('wc-alpha');
        $this->signInAs($this->manager([$event, $this->event('wc-beta')]));

        foreach ([route('manager.login'), route('manager.events.details', $event)] as $url) {
            $this->get($url)
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    }

    public function test_signing_out_ends_it_for_good(): void
    {
        $event = $this->event('wc-alpha');
        $manager = $this->manager([$event, $this->event('wc-beta')], ['password' => 'a-strong-password']);

        $this->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'a-strong-password']);
        $this->get(route('manager.events.details', $event))->assertOk();
        $tokenBefore = session()->token();

        $this->post(route('manager.logout'))->assertRedirect(route('manager.login'));
        $this->app['auth']->forgetGuards();

        $this->assertNotSame($tokenBefore, session()->token(), 'The forms of the old session are useless.');
        $this->get(route('manager.events.details', $event))->assertRedirect(route('manager.login'));
        $this->put(route('manager.events.details.update', $event), $this->details(['display_name' => 'After sign out']))->assertRedirect(route('manager.login'));
        $this->assertSame('WordCamp Alpha', $event->fresh()->display_name);
    }

    // ---- Abuse of the write path -----------------------------------------

    public function test_saving_faster_than_a_person_can_is_slowed_down_per_manager(): void
    {
        RateLimiter::clear('manager-writes:1');
        $event = $this->event('wc-alpha');
        $busy = $this->manager([$event, $this->event('wc-beta')]);
        $calm = $this->manager([$event]);
        $this->signInAs($busy);

        foreach (range(1, 60) as $i) {
            $this->post(route('manager.events.quests.store', $event), ['title' => "Item {$i}"])->assertRedirect();
        }

        $blocked = $this->post(route('manager.events.quests.store', $event), ['title' => 'One too many']);
        $blocked->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(0, $event->quests()->where('title', 'One too many')->count());

        // Reading is free, and someone else at the same address is not held up by it.
        $this->get(route('manager.events.quests', $event))->assertOk();
        $this->app['auth']->forgetGuards();
        $this->signInAs($calm);
        $this->post(route('manager.events.quests.store', $event), ['title' => 'Calm one'])->assertRedirect();
    }

    public function test_changing_the_time_zone_over_and_over_queues_one_refetch_not_a_pile(): void
    {
        $event = $this->event('wc-alpha', ['timezone' => 'Asia/Dhaka', 'timezone_locked' => true]);
        $this->signInAs($this->manager([$event, $this->event('wc-beta')]));
        Queue::fake();

        foreach (['Asia/Kolkata', 'Asia/Dhaka', 'Asia/Kolkata', 'Europe/Berlin', 'Asia/Tokyo'] as $zone) {
            $this->put(route('manager.events.details.update', $event), $this->details(['timezone' => $zone]))->assertSessionHasNoErrors();
        }

        $this->assertSame('Asia/Tokyo', $event->fresh()->timezone, 'Every change is still saved.');
        Queue::assertPushed(\App\Jobs\FetchSpeakersSponsorsSessionsJob::class, 1);
    }

    // ---- Attendees are untouched -----------------------------------------

    public function test_attendee_pages_still_start_no_session_and_set_no_cookie(): void
    {
        $event = $this->event('wc-alpha');

        foreach ([route('home'), route('event.home', $event), route('event.explore', $event), route('event.quest', $event), route('guide')] as $url) {
            $response = $this->get($url)->assertOk();

            $this->assertSame([], $response->headers->getCookies(), "{$url} must not set a cookie.");
        }
    }
}
