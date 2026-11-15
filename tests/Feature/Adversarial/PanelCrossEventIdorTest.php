<?php

namespace Tests\Feature\Adversarial;

use App\Models\AttendeeRoster;
use App\Models\ContributorTable;
use App\Models\DiscoveryProfile;
use App\Models\DiscoveryReport;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\EventManagerChange;
use App\Models\Offer;
use App\Models\Quest;
use App\Models\SharedCampCard;
use App\Models\User;
use App\Support\RosterRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

/**
 * Mismatched parent and child ids: every nested address must check that the
 * thing it changes belongs to the event in the address. Admins can act on
 * every event anyway, so for them this is defence in depth (spec §15.6 named
 * the roster gap); for event managers it is the line between their event and
 * everyone else's.
 */
class PanelCrossEventIdorTest extends TestCase
{
    use RefreshDatabase;

    private Event $mine;

    private Event $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake();
        Http::fake();
        $this->travelTo('2026-11-07 10:00:00');

        $this->mine = $this->event('wc-mine');
        $this->theirs = $this->event('wc-theirs');
    }

    private function event(string $slug): Event
    {
        return Event::create([
            'slug' => $slug, 'display_name' => 'WordCamp '.$slug, 'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'active', 'is_visible' => true, 'country_code' => 'IN',
            'starts_on' => now()->addDays(5)->toDateString(), 'ends_on' => now()->addDays(6)->toDateString(),
        ]);
    }

    private function entry(Event $event, string $name, bool $suppressed = false): AttendeeRoster
    {
        return AttendeeRoster::create(['event_id' => $event->id, 'name' => $name, 'content_hash' => sha1($name.$event->id), 'is_suppressed' => $suppressed]);
    }

    private function profile(Event $event, ?AttendeeRoster $entry = null): DiscoveryProfile
    {
        return DiscoveryProfile::create([
            'discovery_id' => hash('sha256', 'profile-'.$event->id.'-'.($entry?->id ?? 'x')), 'owner_token_hash' => str_repeat('0', 64),
            'event_id' => $event->id, 'attendee_roster_id' => $entry?->id, 'fields' => ['display_name' => 'Someone'], 'expires_at' => now()->addWeek(),
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        return $admin;
    }

    private function signInAsManagerOfMine(): EventManager
    {
        $manager = EventManager::factory()->create();
        // Two events, so the dashboard does not jump straight to one.
        $manager->events()->attach([$this->mine->id, $this->event('wc-extra')->id]);
        Auth::guard('manager')->setUser($manager);

        return $manager;
    }

    // ---- Admin: the attendee list --------------------------------------------

    public function test_hiding_an_attendee_through_another_events_address_is_not_found_and_hides_nobody(): void
    {
        $this->admin();
        $victim = $this->entry($this->theirs, 'Asha Rao');

        $this->post(route('admin.events.roster.suppress', [$this->mine, $victim]))->assertNotFound();

        $this->assertFalse($victim->fresh()->is_suppressed);
    }

    public function test_restoring_an_attendee_through_another_events_address_is_not_found_and_restores_nobody(): void
    {
        $this->admin();
        // Someone who asked to be taken off the list must stay off it.
        $removed = $this->entry($this->theirs, 'Ravi Kumar', suppressed: true);

        $this->post(route('admin.events.roster.unsuppress', [$this->mine, $removed]))->assertNotFound();

        $this->assertTrue($removed->fresh()->is_suppressed);
    }

    public function test_hiding_and_restoring_through_the_entrys_own_event_still_works(): void
    {
        $this->admin();
        $entry = $this->entry($this->theirs, 'Asha Rao');

        $this->post(route('admin.events.roster.suppress', [$this->theirs, $entry]))->assertRedirect(route('admin.events.roster.index', $this->theirs));
        $this->assertTrue($entry->fresh()->is_suppressed);

        $this->post(route('admin.events.roster.unsuppress', [$this->theirs, $entry]))->assertRedirect(route('admin.events.roster.index', $this->theirs));
        $this->assertFalse($entry->fresh()->is_suppressed);
    }

    public function test_every_attendee_list_action_refuses_an_entry_of_another_event(): void
    {
        $this->admin();
        $victim = $this->entry($this->theirs, 'Asha Rao');
        $profile = $this->profile($this->theirs, $victim);
        SharedCampCard::create([
            'share_id' => 'share-1', 'owner_token_hash' => str_repeat('1', 64), 'event_id' => $this->theirs->id,
            'attendee_roster_id' => $victim->id, 'fields' => ['name' => 'Asha Rao'], 'expires_at' => now()->addWeek(),
        ]);

        $actions = ['suppress', 'unsuppress', 'release-claim', 'roles', 'remove-card'];
        foreach ($actions as $action) {
            $this->post(route("admin.events.roster.{$action}", [$this->mine, $victim]), ['roles' => ['sponsor']])
                ->assertNotFound();
        }

        $this->assertFalse($victim->fresh()->is_suppressed);
        $this->assertSame($victim->id, $profile->fresh()->attendee_roster_id);
        $this->assertSame(1, SharedCampCard::where('attendee_roster_id', $victim->id)->count());
    }

    // ---- Admin: quests, deals, tables, reports --------------------------------

    public function test_a_quest_of_another_event_cannot_be_changed_or_removed_through_this_event(): void
    {
        $this->admin();
        $foreign = Quest::create(['event_id' => $this->theirs->id, 'source' => 'event', 'title' => 'Say hi', 'description' => 'x', 'sort_order' => 1, 'is_active' => true]);

        $this->put(route('admin.events.quests.update', [$this->mine, $foreign]), ['title' => 'Hijacked', 'is_active' => '0'])->assertNotFound();
        $this->delete(route('admin.events.quests.destroy', [$this->mine, $foreign]))->assertNotFound();

        $foreign->refresh();
        $this->assertSame('Say hi', $foreign->title);
        $this->assertTrue($foreign->is_active);
    }

    public function test_the_shared_things_to_do_cards_cannot_be_changed_or_removed_through_any_event(): void
    {
        $this->admin();
        // Seeded, cross-event content (event_id null): not something an event's quest page edits.
        $shared = Quest::create(['event_id' => null, 'source' => 'default', 'title' => 'Shared card', 'description' => 'x', 'sort_order' => 1, 'is_active' => true]);

        $this->put(route('admin.events.quests.update', [$this->mine, $shared]), ['title' => 'Hijacked'])->assertNotFound();
        $this->delete(route('admin.events.quests.destroy', [$this->mine, $shared]))->assertNotFound();

        $this->assertSame('Shared card', $shared->fresh()->title);
    }

    public function test_a_quest_is_still_edited_and_removed_through_its_own_event(): void
    {
        $this->admin();
        $quest = Quest::create(['event_id' => $this->mine->id, 'source' => 'event', 'title' => 'Say hi', 'description' => 'x', 'sort_order' => 1, 'is_active' => true]);

        $this->put(route('admin.events.quests.update', [$this->mine, $quest]), ['title' => 'Say hello'])->assertRedirect();
        $this->assertSame('Say hello', $quest->fresh()->title);

        $this->delete(route('admin.events.quests.destroy', [$this->mine, $quest]))->assertRedirect();
        $this->assertNull(Quest::find($quest->id));
    }

    public function test_a_deal_of_another_event_cannot_be_opened_changed_hidden_or_removed_through_this_event(): void
    {
        $this->admin();
        $foreign = Offer::create(['event_id' => $this->theirs->id, 'title' => 'Their deal', 'description' => 'd', 'url' => 'https://a.example', 'is_active' => true]);
        $valid = ['title' => 'Hijacked', 'description' => 'd', 'url' => 'https://evil.example'];

        $this->get(route('admin.events.offers.edit', [$this->mine, $foreign]))->assertNotFound();
        $this->put(route('admin.events.offers.update', [$this->mine, $foreign]), $valid)->assertNotFound();
        $this->post(route('admin.events.offers.visibility', [$this->mine, $foreign]), ['hidden' => '1'])->assertNotFound();
        $this->delete(route('admin.events.offers.destroy', [$this->mine, $foreign]))->assertNotFound();

        $foreign->refresh();
        $this->assertSame('Their deal', $foreign->title);
        $this->assertSame($this->theirs->id, $foreign->event_id);
        $this->assertSame(0, $foreign->hiddenAtEvents()->count());
    }

    public function test_a_contributor_table_of_another_event_is_not_found_through_this_event(): void
    {
        $this->admin();
        $table = ContributorTable::create(['event_id' => $this->theirs->id, 'team' => 'polyglots', 'leads' => []]);

        $this->put(route('admin.events.tables.update', [$this->mine, $table]), ['team' => 'core'])->assertNotFound();
        $this->delete(route('admin.events.tables.destroy', [$this->mine, $table]))->assertNotFound();

        $this->assertSame('polyglots', $table->fresh()->team);
    }

    public function test_a_reported_profile_of_another_event_is_untouched_by_this_events_report_actions(): void
    {
        $this->admin();
        $profile = $this->profile($this->theirs);
        $report = DiscoveryReport::create(['event_id' => $this->theirs->id, 'discovery_id' => $profile->discovery_id, 'reporter_device_id' => 'd1', 'reason' => 'spam', 'snapshot' => [], 'status' => 'open']);

        $this->post(route('admin.events.reports.remove', [$this->mine, $profile->discovery_id]));
        $this->post(route('admin.events.reports.dismiss', [$this->mine, $profile->discovery_id]));

        $this->assertNotNull(DiscoveryProfile::find($profile->id));
        $this->assertSame('open', $report->fresh()->status);
    }

    // ---- Event managers ---------------------------------------------------------

    /** @return list<Route> */
    private function managerWriteRoutesWithAnEvent(): array
    {
        return collect(Router::getRoutes()->getRoutes())
            ->filter(fn (Route $r) => str_starts_with($r->uri(), 'manager/events/{eventId}') && array_diff($r->methods(), ['GET', 'HEAD']) !== [])
            ->values()->all();
    }

    public function test_every_manager_action_aimed_at_someone_elses_event_is_not_found_and_changes_nothing(): void
    {
        $this->signInAsManagerOfMine();
        $entry = $this->entry($this->theirs, 'Asha Rao');
        $quest = $this->theirs->quests()->create(['source' => 'event', 'title' => 'Their quest', 'sort_order' => 1, 'is_active' => true]);
        $table = ContributorTable::create(['event_id' => $this->theirs->id, 'team' => 'polyglots', 'leads' => []]);
        $profile = $this->profile($this->theirs);
        $before = $this->theirs->fresh()->toArray();

        $params = ['eventId' => $this->theirs->id, 'questId' => $quest->id, 'tableId' => $table->id, 'entryId' => $entry->id, 'discoveryId' => $profile->discovery_id, 'kind' => 'photos'];
        // Enough to pass most validation, so a missing check would show as a change, not as a 422.
        $payload = [
            'display_name' => 'Hijacked', 'slug' => 'hijacked', 'source_site_url' => 'https://hijacked.wordcamp.org/2026', 'venue' => 'Hijacked',
            'title' => 'Hijacked', 'team' => 'core', 'roles' => ['sponsor'], 'minutes' => 15, 'from' => '2026-11-07T10:00',
            'primary' => '#000000', 'secondary' => '#000000', 'ink' => '#000000', 'paper' => '#ffffff', 'webhook' => 'https://hook.make.com/x',
            'kind' => 'general', 'body' => 'Hijacked', 'photo_album_url' => 'https://photos.example/x', 'piece' => 'x',
        ];

        $routes = $this->managerWriteRoutesWithAnEvent();
        $this->assertGreaterThan(15, count($routes), 'the manager write routes were found');

        foreach ($routes as $route) {
            $url = '/'.$route->uri();
            foreach ($route->parameterNames() as $name) {
                $url = str_replace('{'.$name.'}', (string) $params[$name], $url);
            }
            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
            $status = $this->call($method, $url, $payload)->status();
            $this->assertSame(404, $status, "{$method} {$route->uri()} on someone else's event answered {$status}");
        }

        $after = $this->theirs->fresh()->toArray();
        unset($before['updated_at'], $after['updated_at']);
        $this->assertSame($before, $after, 'their event was changed');
        $this->assertSame('Their quest', $quest->fresh()->title);
        $this->assertSame('polyglots', $table->fresh()->team);
        $this->assertNotNull(DiscoveryProfile::find($profile->id));
        $this->assertSame(0, EventManagerChange::where('event_id', $this->theirs->id)->count());
    }

    public function test_a_child_of_another_event_is_not_found_through_the_managers_own_event(): void
    {
        $this->signInAsManagerOfMine();
        $entry = $this->entry($this->theirs, 'Asha Rao');
        $table = ContributorTable::create(['event_id' => $this->theirs->id, 'team' => 'polyglots', 'leads' => []]);
        $profile = $this->profile($this->theirs);
        $report = DiscoveryReport::create(['event_id' => $this->theirs->id, 'discovery_id' => $profile->discovery_id, 'reporter_device_id' => 'd1', 'reason' => 'spam', 'snapshot' => [], 'status' => 'open']);

        $this->post(route('manager.events.attendees.roles', [$this->mine->id, $entry->id]), ['roles' => ['sponsor']])->assertNotFound();
        $this->put(route('manager.events.tables.update', [$this->mine->id, $table->id]), ['team' => 'core'])->assertNotFound();
        $this->delete(route('manager.events.tables.destroy', [$this->mine->id, $table->id]))->assertNotFound();
        $this->post(route('manager.events.reports.remove', [$this->mine->id, $profile->discovery_id]));
        $this->post(route('manager.events.reports.dismiss', [$this->mine->id, $profile->discovery_id]));

        $this->assertSame('polyglots', $table->fresh()->team);
        $this->assertNotNull(DiscoveryProfile::find($profile->id));
        $this->assertSame('open', $report->fresh()->status);
        $this->assertSame([], RosterRoles::withAutomatic($this->theirs)['marked'][$entry->id]['roles'] ?? []);
    }

    public function test_a_hidden_attendee_cannot_be_given_roles_by_a_manager(): void
    {
        $this->signInAsManagerOfMine();
        $hidden = $this->entry($this->mine, 'Asked to be removed', suppressed: true);

        $this->post(route('manager.events.attendees.roles', [$this->mine->id, $hidden->id]), ['roles' => ['sponsor']])->assertNotFound();
    }

    public function test_a_manager_whose_event_is_taken_away_mid_session_can_no_longer_change_it(): void
    {
        $manager = $this->signInAsManagerOfMine();
        $this->post(route('manager.events.quests.store', $this->mine->id), ['title' => 'Allowed'])->assertRedirect();

        $manager->events()->detach($this->mine->id);

        $this->post(route('manager.events.quests.store', $this->mine->id), ['title' => 'After removal'])->assertNotFound();
        $this->put(route('manager.events.information.update', $this->mine->id), ['venue' => 'After removal'])->assertNotFound();
        $this->assertSame(0, $this->mine->quests()->where('title', 'After removal')->count());
        $this->assertNull($this->mine->fresh()->info);
    }
}
