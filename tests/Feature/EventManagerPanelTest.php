<?php

namespace Tests\Feature;

use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The event manager's own area (/manager): sign-in on its own page and guard,
 * "My events", and the three sections they may edit — never an event's status,
 * never an event that isn't theirs.
 */
class EventManagerPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function event(string $slug, array $overrides = []): Event
    {
        return Event::create($overrides + [
            'slug' => $slug,
            'display_name' => 'WordCamp '.ucfirst($slug),
            'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'draft',
            'is_visible' => true,
        ]);
    }

    /** A manager assigned to the given events. */
    private function manager(array $events, array $attributes = []): EventManager
    {
        $manager = EventManager::factory()->create($attributes);
        $manager->events()->attach(collect($events)->map->id->all());

        return $manager;
    }

    /**
     * Signs the manager in on its own guard — without making that guard the
     * default one, which is what actingAs($m, 'manager') would do and the app never does.
     */
    private function signInAs(EventManager $manager): void
    {
        Auth::guard('manager')->setUser($manager);
    }

    // ---- Sign-in ---------------------------------------------------------

    public function test_the_sign_in_page_is_its_own_and_branded_for_managers(): void
    {
        $this->get(route('manager.login'))
            ->assertOk()
            ->assertSee('Event manager sign in')
            ->assertSee('Keep me signed in')
            ->assertSee('Show password')
            ->assertSee('noindex, nofollow', false)
            ->assertSee('autocomplete="username"', false)
            ->assertSee('Ask an admin')
            ->assertDontSee('Register')
            ->assertDontSee('Organizer console');

        $this->assertFalse(Route::has('manager.register'), 'Nobody registers: an admin creates the account.');
    }

    public function test_a_manager_signs_in_and_lands_on_their_events(): void
    {
        $a = $this->event('alpha');
        $b = $this->event('beta');
        $manager = $this->manager([$a, $b], ['email' => 'asha@example.com', 'password' => 'a-strong-password']);

        $this->post(route('manager.login.store'), ['email' => 'Asha@Example.com ', 'password' => 'a-strong-password'])
            ->assertRedirect(route('manager.dashboard'));

        $this->assertAuthenticatedAs($manager, 'manager');
        // Signed in as a manager is not signed in as an admin.
        $this->assertGuest('web');
        $this->assertNotNull($manager->fresh()->last_login_at);
    }

    public function test_a_wrong_password_says_so_and_keeps_the_email(): void
    {
        $manager = EventManager::factory()->create(['password' => 'a-strong-password']);

        $this->from(route('manager.login'))
            ->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'nope'])
            ->assertRedirect(route('manager.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest('manager');

        $this->get(route('manager.login'))
            ->assertSee('These credentials do not match our records.')
            ->assertSee('value="'.$manager->email.'"', false);
    }

    public function test_a_switched_off_manager_can_not_sign_in_and_is_not_told_why(): void
    {
        $manager = EventManager::factory()->disabled()->create(['password' => 'a-strong-password']);

        $this->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'a-strong-password'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

        $this->assertGuest('manager');
    }

    public function test_an_admin_login_does_not_work_on_the_manager_page(): void
    {
        $admin = User::factory()->create(['password' => 'a-strong-password']);

        $this->post(route('manager.login.store'), ['email' => $admin->email, 'password' => 'a-strong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('manager');
        $this->assertGuest('web');
    }

    public function test_a_manager_login_does_not_work_on_the_admin_page(): void
    {
        $manager = EventManager::factory()->create(['password' => 'a-strong-password']);

        $this->post(route('login'), ['email' => $manager->email, 'password' => 'a-strong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
        $this->assertGuest('manager');
    }

    public function test_too_many_wrong_guesses_lock_the_form_for_a_while(): void
    {
        $manager = EventManager::factory()->create(['password' => 'a-strong-password']);

        foreach (range(1, 5) as $i) {
            $this->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'wrong-'.$i]);
        }

        // Even the right password is refused now — by the per-email limiter, with its own message.
        $this->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'a-strong-password'])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
        $this->assertGuest('manager');
    }

    public function test_signing_out_ends_the_session(): void
    {
        $manager = $this->manager([$this->event('alpha')]);
        $this->signInAs($manager);

        $this->post(route('manager.logout'))->assertRedirect(route('manager.login'));

        $this->assertGuest('manager');
    }

    public function test_guests_are_sent_to_the_manager_login_not_the_admin_one(): void
    {
        $event = $this->event('alpha');

        foreach ([
            route('manager.dashboard'),
            route('manager.events.details', $event),
            route('manager.events.information', $event),
            route('manager.events.quests', $event),
        ] as $url) {
            $this->get($url)->assertRedirect(route('manager.login'));
        }

        $this->put(route('manager.events.details.update', $event), ['display_name' => 'Hacked'])->assertRedirect(route('manager.login'));
        $this->assertSame('WordCamp Alpha', $event->fresh()->display_name);
    }

    public function test_after_signing_in_a_manager_goes_back_to_the_page_they_asked_for(): void
    {
        $a = $this->event('alpha');
        $manager = $this->manager([$a, $this->event('beta')], ['password' => 'a-strong-password']);

        $this->get(route('manager.events.quests', $a))->assertRedirect(route('manager.login'));

        $this->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'a-strong-password'])
            ->assertRedirect(route('manager.events.quests', $a));
    }

    public function test_a_signed_in_manager_who_opens_the_login_page_is_sent_on(): void
    {
        $this->signInAs($this->manager([$this->event('alpha'), $this->event('beta')]));

        $this->get(route('manager.login'))->assertRedirect(route('manager.dashboard'));
    }

    public function test_a_manager_who_is_switched_off_is_signed_out_at_once(): void
    {
        $manager = $this->manager([$this->event('alpha'), $this->event('beta')]);
        $this->signInAs($manager);

        $this->get(route('manager.dashboard'))->assertOk();

        $manager->update(['is_active' => false]);
        Auth::guard('manager')->setUser($manager->fresh());

        $this->get(route('manager.dashboard'))->assertRedirect(route('manager.login'));
        $this->assertGuest('manager');
    }

    public function test_changing_a_managers_password_signs_out_the_sessions_that_used_the_old_one(): void
    {
        $manager = $this->manager([$this->event('alpha'), $this->event('beta')], ['password' => 'a-strong-password']);

        $this->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'a-strong-password']);
        $this->get(route('manager.dashboard'))->assertOk();

        // An admin sets a new password (from another request); this session still holds the old one.
        EventManager::find($manager->id)->update(['password' => 'brand-new-password']);
        Auth::guard('manager')->forgetUser();

        $this->get(route('manager.dashboard'))->assertRedirect(route('manager.login'));
        $this->assertGuest('manager');
    }

    // ---- My events -------------------------------------------------------

    public function test_my_events_lists_only_the_assigned_events_soonest_first(): void
    {
        $later = $this->event('later', ['starts_on' => '2027-03-01', 'status' => 'active']);
        $sooner = $this->event('sooner', ['starts_on' => '2027-01-01', 'status' => 'approved']);
        $this->event('not-mine', ['starts_on' => '2027-02-01']);
        $this->signInAs($this->manager([$later, $sooner]));

        $this->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['WordCamp Sooner', 'WordCamp Later'])
            ->assertDontSee('WordCamp Not-mine')
            ->assertSee(route('manager.events.details', $sooner), false)
            ->assertSee(route('manager.events.information', $sooner), false)
            ->assertSee(route('manager.events.quests', $sooner), false)
            ->assertSee('Event details')->assertSee('Event information')->assertSee('Quests &amp; checklist', false)
            ->assertSee('Sign out');
    }

    public function test_a_manager_with_one_event_goes_straight_to_it(): void
    {
        $only = $this->event('alpha');
        $this->signInAs($this->manager([$only]));

        $this->get(route('manager.dashboard'))->assertRedirect(route('manager.events.details', $only));
    }

    public function test_a_manager_with_no_events_is_told_to_wait(): void
    {
        $this->signInAs($this->manager([]));

        $this->get(route('manager.dashboard'))->assertOk()->assertSee('No events yet');
    }

    // ---- Event details ---------------------------------------------------

    public function test_the_details_page_has_no_status_control_and_says_who_changes_it(): void
    {
        $event = $this->event('alpha', ['status' => 'active']);
        $this->signInAs($this->manager([$event]));

        $html = $this->get(route('manager.events.details', $event))
            ->assertOk()
            ->assertSee('Event details')
            ->assertSee('Only an admin can change an event')
            ->assertSee('Status: Active.')
            ->assertSee('View in app')
            ->getContent();

        $this->assertStringNotContainsString('name="status"', $html);
        // The three sections are the tabs; deals, roster and leads are not there.
        $this->assertStringContainsString('aria-label="Event sections"', $html);
        $this->assertStringNotContainsString('Deal leads', $html);
        $this->assertStringNotContainsString('/admin/', $html);
    }

    public function test_a_manager_edits_the_details(): void
    {
        $event = $this->event('alpha', ['status' => 'approved', 'is_visible' => true]);
        $this->signInAs($this->manager([$event]));

        $this->put(route('manager.events.details.update', $event), [
            'display_name' => 'WordCamp Alpha 2026',
            'slug' => 'wc-alpha-2026',
            'short_name' => '#WCAlpha',
            'source_site_url' => 'https://alpha.wordcamp.org/2026',
            'starts_on' => '2026-10-03',
            'ends_on' => '2026-10-04',
            'timezone' => 'Asia/Kolkata',
            'is_visible' => '0',
        ])->assertRedirect(route('manager.events.details', $event))->assertSessionHasNoErrors();

        $event->refresh();
        $this->assertSame('WordCamp Alpha 2026', $event->display_name);
        $this->assertSame('wc-alpha-2026', $event->slug);
        $this->assertSame('#WCAlpha', $event->short_name);
        $this->assertSame('2026-10-03', $event->starts_on->toDateString());
        $this->assertSame('2026-10-04', $event->ends_on->toDateString());
        $this->assertSame('Asia/Kolkata', $event->timezone);
        $this->assertTrue($event->timezone_locked);
        $this->assertFalse($event->is_visible);
        $this->assertSame('approved', $event->status, 'Status is untouched.');

        $this->get(route('manager.events.details', $event))->assertSee('Event details saved.')->assertSee('value="wc-alpha-2026"', false);
    }

    public function test_the_status_can_not_be_changed_by_a_manager_whatever_they_send(): void
    {
        $event = $this->event('alpha', ['status' => 'approved']);
        $this->signInAs($this->manager([$event]));

        foreach (['active', 'archived', 'draft'] as $status) {
            $this->put(route('manager.events.details.update', $event), [
                'display_name' => 'WordCamp Alpha',
                'slug' => 'alpha',
                'source_site_url' => 'https://alpha.wordcamp.org/2026',
                'is_visible' => '1',
                'status' => $status,
            ])->assertSessionHasNoErrors();

            $this->assertSame('approved', $event->fresh()->status, "Sending status={$status} must not change it.");
        }

        // Nor by any of the other forms.
        $this->put(route('manager.events.information.update', $event), ['venue' => 'Hall', 'status' => 'active']);
        $this->post(route('manager.events.quests.store', $event), ['title' => 'Bring a pen', 'status' => 'active']);
        $this->assertSame('approved', $event->fresh()->status);
    }

    public function test_the_details_are_validated_like_the_admins(): void
    {
        $event = $this->event('alpha');
        $other = $this->event('beta');
        $this->signInAs($this->manager([$event, $other]));

        $valid = ['display_name' => 'WordCamp Alpha', 'slug' => 'alpha', 'source_site_url' => 'https://alpha.wordcamp.org/2026', 'is_visible' => '1'];
        $put = fn (array $changes) => $this->put(route('manager.events.details.update', $event), $changes + $valid);

        $put(['display_name' => ''])->assertSessionHasErrors('display_name');
        $put(['slug' => 'not a slug!'])->assertSessionHasErrors('slug');
        $put(['slug' => 'beta'])->assertSessionHasErrors('slug');                  // another event's address
        $put(['source_site_url' => 'not-a-url'])->assertSessionHasErrors('source_site_url');
        $put(['source_site_url' => 'http://127.0.0.1/admin'])->assertSessionHasErrors('source_site_url'); // private network
        $put(['starts_on' => '2026-10-05', 'ends_on' => '2026-10-01'])->assertSessionHasErrors('ends_on');
        $put(['timezone' => 'Mars/Olympus'])->assertSessionHasErrors('timezone');

        $this->assertSame('WordCamp Alpha', $event->fresh()->display_name);
    }

    public function test_an_events_own_slug_is_not_a_clash(): void
    {
        $event = $this->event('alpha');
        $this->signInAs($this->manager([$event]));

        $this->put(route('manager.events.details.update', $event), [
            'display_name' => 'WordCamp Alpha', 'slug' => 'alpha', 'source_site_url' => 'https://alpha.wordcamp.org/2026', 'is_visible' => '1',
        ])->assertSessionHasNoErrors();
    }

    public function test_a_blank_time_zone_unlocks_it_and_keeps_the_last_known_one(): void
    {
        $event = $this->event('alpha', ['timezone' => 'Asia/Dhaka', 'timezone_locked' => true]);
        $this->signInAs($this->manager([$event]));

        $this->put(route('manager.events.details.update', $event), [
            'display_name' => 'WordCamp Alpha', 'slug' => 'alpha', 'source_site_url' => 'https://alpha.wordcamp.org/2026', 'is_visible' => '1', 'timezone' => '',
        ])->assertSessionHasNoErrors();

        $event->refresh();
        $this->assertFalse($event->timezone_locked);
    }

    public function test_changing_the_time_zone_of_a_live_event_refreshes_its_schedule(): void
    {
        $event = $this->event('alpha', ['status' => 'active', 'timezone' => 'Asia/Dhaka', 'timezone_locked' => true]);
        $this->signInAs($this->manager([$event]));
        Queue::fake();

        $this->put(route('manager.events.details.update', $event), [
            'display_name' => 'WordCamp Alpha', 'slug' => 'alpha', 'source_site_url' => 'https://alpha.wordcamp.org/2026', 'is_visible' => '1', 'timezone' => 'Asia/Kolkata',
        ]);

        Queue::assertPushed(FetchSpeakersSponsorsSessionsJob::class);
    }

    // ---- Event information ------------------------------------------------

    public function test_a_manager_edits_the_event_information(): void
    {
        $event = $this->event('alpha');
        $this->signInAs($this->manager([$event]));

        $this->get(route('manager.events.information', $event))->assertOk()->assertSee('Event information')->assertSee('Venue');

        $this->put(route('manager.events.information.update', $event), [
            'venue' => "  Jaipur Exhibition Centre \r\n",
            'wifi' => 'CampBuddy / wp2026',
            'emergency_contact' => '+91 98765 43210',
            'important_links' => "https://a.example\r\nhttps://b.example",
            'code_of_conduct_url' => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/code-of-conduct/',
            'nearby_venue_info' => '',
        ])->assertRedirect(route('manager.events.information', $event))->assertSessionHasNoErrors();

        $info = $event->fresh()->info;
        $this->assertSame('Jaipur Exhibition Centre', $info['venue']);
        $this->assertSame("https://a.example\nhttps://b.example", $info['important_links']);
        $this->assertArrayNotHasKey('nearby_venue_info', $info, 'Blank fields are dropped, not stored empty.');

        $this->get(route('manager.events.information', $event))->assertSee('Event information saved.')->assertSee('Jaipur Exhibition Centre');
    }

    public function test_the_event_information_is_validated(): void
    {
        $event = $this->event('alpha', ['info' => ['venue' => 'Old venue']]);
        $this->signInAs($this->manager([$event]));

        $this->put(route('manager.events.information.update', $event), ['code_of_conduct_url' => 'javascript:alert(1)'])->assertSessionHasErrors('code_of_conduct_url');
        $this->put(route('manager.events.information.update', $event), ['venue' => str_repeat('x', 256)])->assertSessionHasErrors('venue');

        $this->assertSame(['venue' => 'Old venue'], $event->fresh()->info);
    }

    // ---- Quests & checklist ----------------------------------------------

    public function test_the_checklist_page_shows_the_events_default_items(): void
    {
        $event = $this->event('alpha');
        $this->signInAs($this->manager([$event]));

        $response = $this->get(route('manager.events.quests', $event))->assertOk()->assertSee('Add a checklist item')->assertSee('Quests &amp; checklist', false);

        foreach (Quest::DEFAULT_CHECKLIST as $title) {
            $response->assertSee(e($title), false);
        }
    }

    public function test_a_manager_adds_edits_hides_and_removes_checklist_items(): void
    {
        $event = $this->event('alpha');
        $this->signInAs($this->manager([$event]));
        $last = $event->quests()->max('sort_order');

        // Add: goes to the end.
        $this->post(route('manager.events.quests.store', $event), ['title' => 'Bring a pen', 'description' => 'Any colour'])
            ->assertRedirect(route('manager.events.quests', $event))->assertSessionHasNoErrors();
        $quest = $event->quests()->where('title', 'Bring a pen')->firstOrFail();
        $this->assertSame($last + 10, $quest->sort_order);
        $this->assertSame('event', $quest->source);

        // Edit + hide.
        $this->put(route('manager.events.quests.update', [$event, $quest->id]), ['title' => 'Bring two pens', 'description' => '', 'sort_order' => 5, 'is_active' => '0'])
            ->assertRedirect(route('manager.events.quests', $event));
        $quest->refresh();
        $this->assertSame('Bring two pens', $quest->title);
        $this->assertSame(5, $quest->sort_order);
        $this->assertFalse($quest->is_active);

        // Show again.
        $this->put(route('manager.events.quests.update', [$event, $quest->id]), ['title' => 'Bring two pens', 'sort_order' => 5, 'is_active' => '1']);
        $this->assertTrue($quest->fresh()->is_active);

        // Remove.
        $this->delete(route('manager.events.quests.destroy', [$event, $quest->id]))->assertRedirect(route('manager.events.quests', $event));
        $this->assertNull(Quest::find($quest->id));
    }

    public function test_a_blank_title_is_reported_at_the_top_of_the_page(): void
    {
        $event = $this->event('alpha');
        $this->signInAs($this->manager([$event]));

        $this->from(route('manager.events.quests', $event))
            ->post(route('manager.events.quests.store', $event), ['title' => ''])
            ->assertRedirect(route('manager.events.quests', $event))
            ->assertSessionHasErrors('title');

        $this->get(route('manager.events.quests', $event))
            ->assertSee('Please fix the following and try again.')
            ->assertSee('The title field is required.');
    }

    // ---- Only their own events -------------------------------------------

    public function test_another_managers_event_is_not_found_on_every_page_and_action(): void
    {
        $mine = $this->event('mine');
        $theirs = $this->event('theirs', ['display_name' => 'Secret WordCamp', 'info' => ['venue' => 'Secret venue']]);
        $this->signInAs($this->manager([$mine, $this->event('extra')]));

        $this->get(route('manager.events.details', $theirs))->assertNotFound();
        $this->get(route('manager.events.information', $theirs))->assertNotFound();
        $this->get(route('manager.events.quests', $theirs))->assertNotFound();

        $this->put(route('manager.events.details.update', $theirs), ['display_name' => 'Hacked', 'slug' => 'theirs', 'source_site_url' => 'https://x.example', 'is_visible' => '1'])->assertNotFound();
        $this->put(route('manager.events.information.update', $theirs), ['venue' => 'Hacked'])->assertNotFound();
        $this->post(route('manager.events.quests.store', $theirs), ['title' => 'Hacked'])->assertNotFound();

        // Even a request that would fail validation says "not found", not "invalid" — nothing leaks.
        $this->put(route('manager.events.details.update', $theirs), ['display_name' => ''])->assertNotFound();

        $theirs->refresh();
        $this->assertSame('Secret WordCamp', $theirs->display_name);
        $this->assertSame(['venue' => 'Secret venue'], $theirs->info);
        $this->assertSame(0, $theirs->quests()->where('title', 'Hacked')->count());
    }

    public function test_an_event_that_does_not_exist_is_not_found_too(): void
    {
        $this->signInAs($this->manager([$this->event('alpha'), $this->event('beta')]));

        $this->get(route('manager.events.details', 999999))->assertNotFound();
        $this->get('/manager/events/not-a-number')->assertNotFound();
    }

    public function test_a_quest_of_another_event_can_not_be_reached_through_my_event(): void
    {
        $mine = $this->event('mine');
        $theirs = $this->event('theirs');
        $this->signInAs($this->manager([$mine, $this->event('extra')]));

        $foreign = $theirs->quests()->firstOrFail();
        $title = $foreign->title;

        $this->put(route('manager.events.quests.update', [$mine, $foreign->id]), ['title' => 'Hijacked', 'sort_order' => 1])->assertNotFound();
        $this->delete(route('manager.events.quests.destroy', [$mine, $foreign->id]))->assertNotFound();

        $foreign->refresh();
        $this->assertSame($title, $foreign->title);
        $this->assertNotNull(Quest::find($foreign->id));
    }

    public function test_the_event_the_manager_lost_is_gone_from_their_view(): void
    {
        $a = $this->event('alpha');
        $b = $this->event('beta');
        $manager = $this->manager([$a, $b]);
        $this->signInAs($manager);

        $this->get(route('manager.events.details', $b))->assertOk();

        $manager->events()->detach($b->id);

        $this->get(route('manager.events.details', $b))->assertNotFound();
        $this->get(route('manager.events.details', $a))->assertOk();
    }

    // ---- Nothing of the admin panel --------------------------------------

    public function test_a_manager_can_not_open_any_admin_page(): void
    {
        $event = $this->event('alpha');
        $quest = $event->quests()->firstOrFail();
        $this->signInAs($this->manager([$event]));

        $urls = [
            route('dashboard'),
            route('admin.events.index'),
            route('admin.events.edit', $event),
            route('admin.errors.index'),
            route('admin.event-managers.index'),
            route('admin.events.quests.index', $event),
            route('admin.events.roster.index', $event),
            route('admin.media.index'),
            route('profile.edit'),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        // Nor can they act there: the event's status stays what it was.
        $this->put(route('admin.events.update', $event), ['slug' => 'alpha', 'display_name' => 'X', 'source_site_url' => 'https://x.example', 'status' => 'active', 'is_visible' => '1'])
            ->assertRedirect(route('login'));
        $this->put(route('admin.events.quests.update', [$event, $quest]), ['title' => 'X'])->assertRedirect(route('login'));

        $this->assertSame('draft', $event->fresh()->status);
    }

    public function test_an_admin_is_not_a_manager(): void
    {
        $event = $this->event('alpha');
        $this->actingAs(User::factory()->create());

        $this->get(route('manager.dashboard'))->assertRedirect(route('manager.login'));
        $this->get(route('manager.events.details', $event))->assertRedirect(route('manager.login'));
    }

    public function test_manager_pages_are_not_indexed_and_carry_no_admin_links(): void
    {
        $event = $this->event('alpha');
        $this->signInAs($this->manager([$event]));

        foreach ([route('manager.events.details', $event), route('manager.events.information', $event), route('manager.events.quests', $event)] as $url) {
            $this->get($url)->assertOk()->assertSee('noindex, nofollow', false)->assertDontSee(route('admin.events.index'), false);
        }
    }

    // ---- The manager and the admin write the same thing ------------------

    public function test_the_manager_and_the_admin_store_event_information_the_same_way(): void
    {
        $viaAdmin = $this->event('via-admin');
        $viaManager = $this->event('via-manager');

        $payload = [
            'venue' => "  A hall \r\n",
            'wifi' => '',
            'important_links' => "https://a.example\r\nhttps://b.example",
            'emergency_contact' => '  +91 98765 43210  ',
            'code_of_conduct_url' => 'https://example.org/coc',
        ];

        $this->actingAs(User::factory()->create());
        $this->put(route('admin.events.update-info', $viaAdmin), $payload)->assertSessionHasNoErrors();

        Auth::guard('web')->logout();
        $this->signInAs($this->manager([$viaManager, $this->event('extra')]));
        $this->put(route('manager.events.information.update', $viaManager), $payload)->assertSessionHasNoErrors();

        $this->assertSame($viaAdmin->fresh()->info, $viaManager->fresh()->info);
    }

    public function test_the_manager_and_the_admin_treat_the_time_zone_the_same_way(): void
    {
        $viaAdmin = $this->event('via-admin', ['timezone' => 'Asia/Dhaka', 'timezone_locked' => true]);
        $viaManager = $this->event('via-manager', ['timezone' => 'Asia/Dhaka', 'timezone_locked' => true]);
        $details = fn (string $slug, string $zone) => [
            'display_name' => 'WordCamp', 'slug' => $slug, 'source_site_url' => "https://{$slug}.wordcamp.org/2026", 'is_visible' => '1', 'status' => 'draft', 'timezone' => $zone,
        ];

        foreach (['Asia/Kolkata', '+05:30', ''] as $zone) {
            $this->actingAs(User::factory()->create());
            $this->put(route('admin.events.update', $viaAdmin), $details('via-admin', $zone))->assertSessionHasNoErrors();

            Auth::guard('web')->logout();
            $this->signInAs($this->manager([$viaManager, $this->event('extra-'.md5($zone))]));
            $this->put(route('manager.events.details.update', $viaManager), $details('via-manager', $zone))->assertSessionHasNoErrors();

            $a = $viaAdmin->fresh();
            $m = $viaManager->fresh();
            $this->assertSame([$a->timezone, $a->timezone_locked], [$m->timezone, $m->timezone_locked], "Zone \"{$zone}\" is stored differently.");
        }
    }
}
