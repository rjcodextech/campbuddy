<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventManager;
use App\Models\EventManagerChange;
use App\Models\Quest;
use App\Models\User;
use App\Support\DataVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The whole use case, start to finish, the way people really do it:
 *
 *   an admin sets a person up → the person signs in and edits their events →
 *   attendees (who never sign in) see the change → the admin sees who did what →
 *   the admin switches, re-keys or removes the person.
 *
 * Every step goes through real requests, real sign-in (not actingAs) for the
 * manager, and the attendee pages as an anonymous visitor.
 */
class EventManagerJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake(); // going live queues fetches from wordcamp.org — not what is under test
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
            'ends_on' => now()->addDays(6)->toDateString(),
        ]);
    }

    /** Forget who is signed in, in memory only — what a fresh request from another person's browser would start from. */
    private function newVisitor(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /** @return array<int, string> the titles the attendee Quest page hands to the app */
    private function questTitlesFor(Event $event): array
    {
        $html = $this->get(route('event.quest', $event))->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<script type="application/json" id="quest-data">(.*?)</script>#s', $html, $m), 'The quest page carries its data.');

        return array_column(json_decode($m[1], true, 512, JSON_THROW_ON_ERROR), 'title');
    }

    private function detailsPayload(array $overrides = []): array
    {
        return $overrides + [
            'display_name' => 'WordCamp Jaipur 2026',
            'slug' => 'wc-rajasthan',
            'short_name' => '#WCJaipur',
            'source_site_url' => 'https://rajasthan.wordcamp.org/2026',
            'starts_on' => now()->addDays(5)->toDateString(),
            'ends_on' => now()->addDays(6)->toDateString(),
            'timezone' => 'Asia/Kolkata',
            'is_visible' => '1',
        ];
    }

    public function test_the_whole_story_from_the_admin_to_the_attendee_and_back(): void
    {
        $rajasthan = $this->event('wc-rajasthan', ['display_name' => 'WordCamp Rajasthan 2026']);
        $sylhet = $this->event('wc-sylhet', ['display_name' => 'WordCamp Sylhet 2026']);
        $nagpur = $this->event('wc-nagpur', ['display_name' => 'WordCamp Nagpur 2026', 'info' => ['venue' => 'Nagpur venue']]);

        // ---- 1. The admin sets a person up ------------------------------------------------------
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $this->post(route('admin.event-managers.store'), [
            'name' => 'Asha Verma',
            'email' => 'Asha@Example.com',
            'phone' => '+91 98765 43210',
            'password' => 'first-password-1',
            'is_active' => '1',
            'events' => [$rajasthan->id, $sylhet->id],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $manager = EventManager::firstOrFail();
        $this->newVisitor();

        // ---- 2. The person signs in and sees exactly their events -------------------------------
        $this->get(route('manager.dashboard'))->assertRedirect(route('manager.login'));

        $this->post(route('manager.login.store'), ['email' => 'asha@example.com', 'password' => 'first-password-1'])
            ->assertRedirect(route('manager.dashboard'));

        $this->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('WordCamp Rajasthan 2026')
            ->assertSee('WordCamp Sylhet 2026')
            ->assertDontSee('WordCamp Nagpur 2026');

        // ---- 3. Attendees see the event as it is now --------------------------------------------
        $this->newVisitor();
        $this->get(route('event.home', $rajasthan))->assertOk()->assertSee('WordCamp Rajasthan 2026');
        $versionBefore = DataVersion::for($rajasthan->fresh());
        $this->travel(30)->seconds(); // so "updated at" can tell the edits apart (well inside the 2-minute cache, which must not do the noticing)

        // ---- 4. The manager renames the event and sets its dates and zone -----------------------
        $this->put(route('manager.events.details.update', $rajasthan), $this->detailsPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('manager.events.details', $rajasthan));

        $rajasthan->refresh();
        $this->assertSame('WordCamp Jaipur 2026', $rajasthan->display_name);
        $this->assertSame('Asia/Kolkata', $rajasthan->timezone);
        $this->assertSame('active', $rajasthan->status, 'The status is not theirs to change.');

        // …and every attendee-facing place that names the event says the new name at once.
        $this->newVisitor();
        $home = $this->get(route('event.home', $rajasthan))->assertOk();
        $home->assertSee('WordCamp Jaipur 2026')->assertSee('#WCJaipur')->assertDontSee('WordCamp Rajasthan 2026');
        $this->get('/')->assertOk()->assertSee('WordCamp Jaipur 2026')->assertDontSee('WordCamp Rajasthan 2026');
        $this->get(route('llms'))->assertOk()->assertSee('[WordCamp Jaipur 2026]', false);
        $versionAfterDetails = DataVersion::for($rajasthan->fresh());
        $this->assertNotSame($versionBefore, $versionAfterDetails, 'Open apps are told there is something new.');

        // ---- 5. The manager fills in the event information -------------------------------------
        $this->put(route('manager.events.information.update', $rajasthan), [
            'venue' => 'Jaipur Exhibition Centre, Tonk Road',
            'wifi' => 'WCJaipur / camp2026',
            'emergency_contact' => '+91 98765 43210',
            'important_links' => "Schedule: https://rajasthan.wordcamp.org/2026/schedule/\njavascript:alert(1)\nhttps://a.example/tickets",
            'code_of_conduct_url' => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/code-of-conduct/',
        ])->assertSessionHasNoErrors();

        $this->newVisitor();
        $explore = $this->get(route('event.explore', $rajasthan))->assertOk();
        $explore->assertSee('Jaipur Exhibition Centre, Tonk Road')
            ->assertSee('WCJaipur / camp2026')
            ->assertSee('href="tel:+919876543210"', false)
            ->assertSee('href="https://rajasthan.wordcamp.org/2026/schedule/"', false)
            ->assertSee('href="https://a.example/tickets"', false)
            ->assertSee('href="https://make.wordpress.org/community/handbook/wordcamp-organizer/code-of-conduct/"', false)
            // The line that is not a web address is text, never a link.
            ->assertSee('javascript:alert(1)')
            ->assertDontSee('href="javascript:', false);
        $this->assertNotSame($versionAfterDetails, DataVersion::for($rajasthan->fresh()));

        // ---- 6. The manager edits the checklist -------------------------------------------------
        $this->newVisitor();
        $titlesBefore = $this->questTitlesFor($rajasthan);
        $this->assertContains('Save venue directions', $titlesBefore);
        $defaultsBefore = Quest::whereNull('event_id')->count();
        $this->assertGreaterThan(0, $defaultsBefore, 'The shared Things to do cards exist.');

        $quests = $rajasthan->quests()->get()->keyBy('title');
        $versionBeforeQuests = DataVersion::for($rajasthan->fresh());
        $this->travel(30)->seconds();

        $this->post(route('manager.events.quests.store', $rajasthan), ['title' => 'Bring a power strip', 'description' => 'Sockets are scarce'])
            ->assertSessionHasNoErrors();
        $this->put(route('manager.events.quests.update', [$rajasthan, $quests['Pack laptop + charger']->id]), ['title' => 'Pack laptop, charger and a mouse', 'sort_order' => 60, 'is_active' => '1']);
        $this->put(route('manager.events.quests.update', [$rajasthan, $quests['Join Make WordPress Slack']->id]), ['title' => 'Join Make WordPress Slack', 'sort_order' => 50, 'is_active' => '0']);
        $this->delete(route('manager.events.quests.destroy', [$rajasthan, $quests['Save venue directions']->id]));

        $this->newVisitor();
        $titlesAfter = $this->questTitlesFor($rajasthan);
        $this->assertContains('Bring a power strip', $titlesAfter);
        $this->assertContains('Pack laptop, charger and a mouse', $titlesAfter);
        $this->assertNotContains('Pack laptop + charger', $titlesAfter);
        $this->assertNotContains('Join Make WordPress Slack', $titlesAfter, 'A hidden item is not shown to attendees.');
        $this->assertNotContains('Save venue directions', $titlesAfter, 'A removed item is gone.');
        $this->assertSame($defaultsBefore, Quest::whereNull('event_id')->count(), 'The shared cards are untouched.');
        $this->assertNotSame($versionBeforeQuests, DataVersion::for($rajasthan->fresh()), 'A checklist change reaches open apps without waiting for the cache to expire.');

        // ---- 7. Nothing outside their two events, and nothing about the status ------------------
        $this->get(route('manager.events.details', $nagpur))->assertNotFound();
        $this->put(route('manager.events.information.update', $nagpur), ['venue' => 'Hacked'])->assertNotFound();
        $this->post(route('manager.events.quests.store', $nagpur), ['title' => 'Hacked'])->assertNotFound();
        $this->assertSame(['venue' => 'Nagpur venue'], $nagpur->fresh()->info);
        $this->put(route('manager.events.details.update', $sylhet), $this->detailsPayload([
            'display_name' => 'WordCamp Sylhet 2026', 'slug' => 'wc-sylhet', 'source_site_url' => 'https://wc-sylhet.wordcamp.org/2026', 'status' => 'archived',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('active', $sylhet->fresh()->status);

        // ---- 8. The admin sees who changed what -------------------------------------------------
        $this->actingAs($admin);

        $activity = $this->get(route('admin.event-managers.activity'))->assertOk();
        $activity->assertSee('Asha Verma')
            ->assertSee('Display name: “WordCamp Rajasthan 2026” → “WordCamp Jaipur 2026”')
            ->assertSee('Short name: “—” → “#WCJaipur”')
            ->assertSee('Time zone: “—” → “Asia/Kolkata”')
            ->assertSee('Updated: Venue, Wifi, Code of conduct URL, Emergency contact, Important links')
            ->assertSee('Added “Bring a power strip”')
            ->assertSee('Renamed “Pack laptop + charger” → “Pack laptop, charger and a mouse”')
            ->assertSee('Hid “Join Make WordPress Slack”')
            ->assertSee('Removed “Save venue directions”');

        $this->assertSame(6, EventManagerChange::where('event_id', $rajasthan->id)->count(), 'One entry per real change: details, information, four checklist edits.');
        $this->assertSame(1, EventManagerChange::where('event_id', $sylhet->id)->count());
        $this->assertSame(0, EventManagerChange::where('event_id', $nagpur->id)->count(), 'Refused requests leave no trace of a change.');

        $this->get(route('admin.event-managers.edit', $manager))->assertSee('Recent activity')->assertSee('Added “Bring a power strip”')->assertSee('All activity');
        $this->get(route('admin.event-managers.activity', ['event' => $sylhet->id]))->assertSee('WordCamp Sylhet 2026')->assertDontSee('Bring a power strip');
        $this->get(route('admin.event-managers.activity', ['event' => $nagpur->id]))->assertSee('No changes match these filters');
        $this->get(route('admin.event-managers.activity', ['manager' => $manager->id]))->assertSee('Bring a power strip');

        // ---- 9. The admin switches the person off: they are out at once, attendees notice nothing -
        $form = ['name' => 'Asha Verma', 'email' => 'asha@example.com', 'phone' => '+91 98765 43210', 'password' => '', 'is_active' => '1', 'events' => [$rajasthan->id, $sylhet->id]];

        $this->put(route('admin.event-managers.update', $manager), ['is_active' => '0'] + $form)->assertSessionHasNoErrors();
        $this->newVisitor();

        $this->get(route('manager.dashboard'))->assertRedirect(route('manager.login'));
        $this->post(route('manager.login.store'), ['email' => 'asha@example.com', 'password' => 'first-password-1'])->assertSessionHasErrors('email');
        $this->assertGuest('manager');
        $this->get(route('event.home', $rajasthan))->assertOk()->assertSee('WordCamp Jaipur 2026');

        // ---- 10. Back on, then re-keyed: a session that used the old password ends -------------
        $this->actingAs($admin);
        $this->put(route('admin.event-managers.update', $manager), $form)->assertSessionHasNoErrors();
        $this->newVisitor();

        $this->post(route('manager.login.store'), ['email' => 'asha@example.com', 'password' => 'first-password-1'])->assertRedirect(route('manager.dashboard'));
        $this->get(route('manager.dashboard'))->assertOk();

        $this->actingAs($admin);
        $this->put(route('admin.event-managers.update', $manager), ['password' => 'second-password-2'] + $form)->assertSessionHasNoErrors();
        $this->newVisitor();

        $this->get(route('manager.dashboard'))->assertRedirect(route('manager.login'));
        $this->post(route('manager.login.store'), ['email' => 'asha@example.com', 'password' => 'first-password-1'])->assertSessionHasErrors('email');
        $this->post(route('manager.login.store'), ['email' => 'asha@example.com', 'password' => 'second-password-2'])->assertRedirect(route('manager.dashboard'));

        // ---- 11. One event taken away: only that one is gone ------------------------------------
        $this->actingAs($admin);
        $this->put(route('admin.event-managers.update', $manager), ['events' => [$rajasthan->id]] + $form)->assertSessionHasNoErrors();
        $this->newVisitor();

        $this->get(route('manager.events.details', $sylhet))->assertNotFound();
        $this->get(route('manager.events.details', $rajasthan))->assertOk();
        $this->get(route('manager.dashboard'))->assertRedirect(route('manager.events.details', $rajasthan));

        // ---- 12. The account is deleted: signed out, history kept, events untouched -------------
        $this->actingAs($admin);
        $this->delete(route('admin.event-managers.destroy', $manager))->assertRedirect(route('admin.event-managers.index'));
        $this->newVisitor();

        $this->get(route('manager.dashboard'))->assertRedirect(route('manager.login'));
        $this->assertSame(0, EventManager::count());
        $this->assertSame('WordCamp Jaipur 2026', $rajasthan->fresh()->display_name);
        $this->assertSame('active', $rajasthan->fresh()->status);

        $this->actingAs($admin);
        $this->get(route('admin.event-managers.activity'))->assertOk()->assertSee('Asha Verma')->assertSee('(account deleted)')->assertSee('Bring a power strip');
        $this->assertSame(7, EventManagerChange::count(), 'The record outlives the account.');
    }

    public function test_two_managers_on_one_event_are_told_apart_in_the_activity(): void
    {
        $event = $this->event('wc-shared');
        $asha = EventManager::factory()->create(['name' => 'Asha Verma', 'email' => 'asha@example.com', 'password' => 'asha-password-1']);
        $ravi = EventManager::factory()->create(['name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'password' => 'ravi-password-1']);
        $asha->events()->attach($event);
        $ravi->events()->attach($event);
        $other = $this->event('wc-other');
        $asha->events()->attach($other);

        $login = fn (string $email, string $password) => $this->post(route('manager.login.store'), ['email' => $email, 'password' => $password])->assertSessionHasNoErrors();

        $login('asha@example.com', 'asha-password-1');
        $this->post(route('manager.events.quests.store', $event), ['title' => 'Asha was here'])->assertSessionHasNoErrors();
        $this->post(route('manager.logout'));
        $this->newVisitor();

        $login('ravi@example.com', 'ravi-password-1');
        $this->post(route('manager.events.quests.store', $event), ['title' => 'Ravi was here'])->assertSessionHasNoErrors();
        $this->get(route('manager.events.details', $other))->assertNotFound('Ravi has only the shared event.');
        $this->post(route('manager.logout'));
        $this->newVisitor();

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.event-managers.activity', ['manager' => $ravi->id]))->assertSee('Ravi was here')->assertDontSee('Asha was here');
        $this->get(route('admin.event-managers.activity', ['manager' => $asha->id]))->assertSee('Asha was here')->assertDontSee('Ravi was here');
        $this->assertSame(['Asha Verma', 'Ravi Kumar'], EventManagerChange::orderBy('id')->pluck('manager_name')->all());

        $this->assertContains('Asha was here', $this->questTitlesFor($event));
        $this->assertContains('Ravi was here', $this->questTitlesFor($event));
    }

    public function test_adding_or_editing_a_checklist_item_each_tell_open_apps_on_their_own(): void
    {
        $event = $this->event('wc-versions');
        $manager = EventManager::factory()->create(['password' => 'a-strong-password']);
        $manager->events()->attach([$event->id, $this->event('wc-extra')->id]);
        $this->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'a-strong-password']);

        $version = fn () => DataVersion::for($event->fresh());

        $before = $version();
        $this->travel(30)->seconds();
        $this->post(route('manager.events.quests.store', $event), ['title' => 'A new item']);
        $afterAdd = $version();
        $this->assertNotSame($before, $afterAdd, 'Adding an item is news.');

        $this->travel(30)->seconds();
        $quest = $event->quests()->where('title', 'A new item')->firstOrFail();
        $this->put(route('manager.events.quests.update', [$event, $quest->id]), ['title' => 'A renamed item', 'sort_order' => $quest->sort_order, 'is_active' => '1']);
        $this->assertNotSame($afterAdd, $version(), 'Editing an item is news.');
    }
    public function test_removing_a_checklist_item_tells_open_apps_at_once(): void
    {
        $event = $this->event('wc-removal');
        $manager = EventManager::factory()->create(['password' => 'a-strong-password']);
        $manager->events()->attach([$event->id, $this->event('wc-extra')->id]);
        $this->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'a-strong-password']);

        $version = fn () => DataVersion::for($event->fresh());
        $before = $version();

        // Not the newest item, so the newest "updated at" stays exactly as it was: only the count can tell.
        $quest = $event->quests()->orderBy('sort_order')->firstOrFail();
        $this->delete(route('manager.events.quests.destroy', [$event, $quest->id]))->assertRedirect();

        $this->assertNotSame($before, $version(), 'Removing an item is news.');
    }
    public function test_saving_without_changing_anything_leaves_no_entry_and_no_new_version(): void
    {
        $event = $this->event('wc-quiet', ['display_name' => 'WordCamp Quiet', 'info' => ['venue' => 'Hall']]);
        $manager = EventManager::factory()->create(['password' => 'a-strong-password']);
        $manager->events()->attach([$event->id, $this->event('wc-extra')->id]);

        $this->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'a-strong-password']);

        $this->put(route('manager.events.details.update', $event), $this->detailsPayload([
            'display_name' => 'WordCamp Quiet', 'slug' => 'wc-quiet', 'short_name' => null, 'source_site_url' => 'https://wc-quiet.wordcamp.org/2026', 'timezone' => '',
        ]))->assertSessionHasNoErrors();
        $this->put(route('manager.events.information.update', $event), ['venue' => 'Hall'])->assertSessionHasNoErrors();
        $quest = $event->quests()->firstOrFail();
        $this->put(route('manager.events.quests.update', [$event, $quest->id]), ['title' => $quest->title, 'description' => '', 'sort_order' => $quest->sort_order, 'is_active' => '1']);

        $this->assertSame(0, EventManagerChange::count());
    }

    public function test_a_change_is_recorded_even_when_the_activity_table_is_not_there_yet(): void
    {
        $event = $this->event('wc-old');
        $manager = EventManager::factory()->create(['password' => 'a-strong-password']);
        $manager->events()->attach([$event->id, $this->event('wc-extra')->id]);
        $this->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'a-strong-password']);

        // Code uploaded before its migration: the log can't be written, the manager's work still is.
        \Illuminate\Support\Facades\Schema::drop('event_manager_changes');

        $this->post(route('manager.events.quests.store', $event), ['title' => 'Still saved'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($event->quests()->where('title', 'Still saved')->exists());
    }
}
