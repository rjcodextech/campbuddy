<?php

namespace Tests\Feature;

use App\Jobs\DiscoverWordCampsJob;
use App\Models\AttendeeRoster;
use App\Models\ContributorTable;
use App\Models\Event;
use App\Models\EventFeedback;
use App\Models\EventManager;
use App\Models\Offer;
use App\Models\OfferLead;
use App\Models\User;
use App\Support\AppOrganizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The app's organizer side beyond the first tools: every event tab of the web
 * panel (OrganizerManageController) and the admin's platform pages
 * (OrganizerAdminController) — with the web panel's rules: a manager only
 * reaches their own events, never the status, and none of the admin's parts.
 */
class OrganizerAppManageTest extends TestCase
{
    use RefreshDatabase;

    private Event $mine;

    private Event $theirs;

    private EventManager $manager;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake();
        $this->mine = $this->makeEvent('wordcamp-mine', now()->addDays(3));
        $this->theirs = $this->makeEvent('wordcamp-theirs', now()->subDays(30));
        $this->manager = EventManager::factory()->create(['email' => 'mgr@example.com']);
        $this->manager->events()->attach($this->mine->id);
        $this->admin = User::factory()->create();
    }

    private function makeEvent(string $slug, $start): Event
    {
        return Event::withoutEvents(fn () => Event::create([
            'slug' => $slug, 'display_name' => ucwords(str_replace('-', ' ', $slug)), 'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'active', 'is_visible' => true, 'starts_on' => $start->toDateString(), 'ends_on' => $start->copy()->addDay()->toDateString(), 'timezone' => 'Asia/Kolkata',
        ]));
    }

    private function asManager(): array
    {
        return ['Authorization' => 'Bearer '.AppOrganizer::issue('manager', $this->manager->id, 'phone')['token']];
    }

    private function asAdmin(): array
    {
        return ['Authorization' => 'Bearer '.AppOrganizer::issue('admin', $this->admin->id, 'phone')['token']];
    }

    private function url(Event $event, string $path = ''): string
    {
        return "/api/v1/app/organizer/events/{$event->id}".$path;
    }

    private function detailsPayload(array $extra = []): array
    {
        return $extra + [
            'slug' => 'wordcamp-mine', 'display_name' => 'WordCamp Mine', 'short_name' => 'WC Mine',
            'source_site_url' => 'https://wordcamp-mine.wordcamp.org/2026', 'starts_on' => $this->mine->starts_on->toDateString(),
            'ends_on' => $this->mine->ends_on->toDateString(), 'timezone' => 'Asia/Kolkata', 'is_visible' => true,
        ];
    }

    public function test_the_events_list_takes_the_web_panels_filters_for_both_roles(): void
    {
        $admin = $this->asAdmin();

        $this->assertSame(['wordcamp-mine'], collect($this->getJson('/api/v1/app/organizer/events?when=upcoming', $admin)->assertOk()->json('data'))->pluck('slug')->all());
        $this->assertSame(['wordcamp-theirs'], collect($this->getJson('/api/v1/app/organizer/events?when=past', $admin)->json('data'))->pluck('slug')->all());
        $this->assertSame(['wordcamp-theirs'], collect($this->getJson('/api/v1/app/organizer/events?q=theirs', $admin)->json('data'))->pluck('slug')->all());
        // Soonest first, with the timing words and the attendee count.
        $all = $this->getJson('/api/v1/app/organizer/events?order=soon', $admin)->json('data');
        $this->assertSame(['wordcamp-mine', 'wordcamp-theirs'], collect($all)->pluck('slug')->all());
        $this->assertSame('In 3 days', $all[0]['timing']['label']);
        $this->assertSame(0, $all[0]['roster_count']);

        // A manager's filters never reach past their own events.
        $this->assertSame([], $this->getJson('/api/v1/app/organizer/events?q=theirs', $this->asManager())->json('data'));
        $this->assertSame(['wordcamp-mine'], collect($this->getJson('/api/v1/app/organizer/events?order=soon', $this->asManager())->json('data'))->pluck('slug')->all());
    }

    public function test_a_manager_edits_details_without_the_status_and_it_is_logged(): void
    {
        $headers = $this->asManager();

        $this->getJson($this->url($this->mine, '/details'), $headers)->assertOk()
            ->assertJsonPath('data.slug', 'wordcamp-mine')->assertJsonMissingPath('data.recent_fetches');

        $this->postJson($this->url($this->mine, '/details'), $this->detailsPayload(['display_name' => 'WordCamp Mine 2026', 'is_visible' => false, 'status' => 'archived']), $headers)
            ->assertOk()->assertJsonPath('data.display_name', 'WordCamp Mine 2026')->assertJsonPath('data.is_visible', false);

        $this->assertSame('active', $this->mine->fresh()->status, 'a manager never changes the status');
        $this->assertDatabaseHas('event_manager_changes', ['event_id' => $this->mine->id, 'section' => 'details', 'action' => 'updated']);

        // Only a wordcamp.org address, and plain text.
        $this->postJson($this->url($this->mine, '/details'), $this->detailsPayload(['source_site_url' => 'https://example.com']), $headers)->assertUnprocessable();
        $this->postJson($this->url($this->mine, '/details'), $this->detailsPayload(['display_name' => '<b>x</b>']), $headers)->assertUnprocessable();
        $this->postJson($this->url($this->theirs, '/details'), $this->detailsPayload(), $headers)->assertNotFound();
    }

    public function test_an_admin_changes_the_status_and_sees_the_fetch_history(): void
    {
        $this->postJson($this->url($this->theirs, '/details'), $this->detailsPayload(['slug' => 'wordcamp-theirs', 'source_site_url' => 'https://example.org', 'status' => 'archived']), $this->asAdmin())
            ->assertOk()->assertJsonPath('data.status', 'archived')->assertJsonStructure(['data' => ['data_counts', 'recent_fetches']]);
    }

    public function test_the_whole_information_form_saves_and_blank_fields_are_removed(): void
    {
        $this->mine->update(['info' => ['wifi' => 'old', 'venue' => 'Hall']]);

        $this->postJson($this->url($this->mine, '/information'), ['wifi' => 'WCNet / pass', 'venue' => '', 'nearby_venue_info' => "Cafe\r\nPark", 'code_of_conduct_url' => 'https://example.org/coc'], $this->asManager())
            ->assertOk()->assertJsonPath('data.wifi', 'WCNet / pass')->assertJsonPath('data.venue', null)->assertJsonPath('data.nearby_venue_info', "Cafe\nPark");

        $this->assertEqualsCanonicalizing(['wifi', 'nearby_venue_info', 'code_of_conduct_url'], array_keys($this->mine->fresh()->info));
        $this->assertDatabaseHas('event_manager_changes', ['event_id' => $this->mine->id, 'section' => 'information']);
        $this->postJson($this->url($this->mine, '/information'), ['code_of_conduct_url' => 'not a url'], $this->asManager())->assertUnprocessable();
    }

    public function test_quests_are_added_changed_hidden_and_removed(): void
    {
        $headers = $this->asManager();

        $id = $this->postJson($this->url($this->mine, '/quests'), ['title' => 'Visit the sponsor hall'], $headers)->assertCreated()->json('data.id');
        $this->patchJson($this->url($this->mine, "/quests/{$id}"), ['title' => 'Visit every sponsor', 'is_active' => false], $headers)->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertSame(['Visit every sponsor'], collect($this->getJson($this->url($this->mine, '/quests'), $headers)->json('data'))->where('source', 'event')->pluck('title')->values()->all());

        // Another event's quest is not reachable through this one.
        $other = $this->theirs->quests()->create(['title' => 'Theirs', 'source' => 'event', 'sort_order' => 1]);
        $this->patchJson($this->url($this->mine, "/quests/{$other->id}"), ['title' => 'x'], $headers)->assertNotFound();

        $this->deleteJson($this->url($this->mine, "/quests/{$id}"), [], $headers)->assertOk();
        $this->assertDatabaseMissing('quests', ['id' => $id]);
        $this->assertSame(3, DB::table('event_manager_changes')->where('section', 'quests')->count());
    }

    public function test_contributor_tables_are_added_changed_and_removed(): void
    {
        $headers = $this->asManager();
        AttendeeRoster::create(['event_id' => $this->mine->id, 'name' => 'Asha Rao', 'content_hash' => 'h1', 'is_suppressed' => false]);

        $created = $this->postJson($this->url($this->mine, '/tables'), ['team' => 'polyglots', 'floor' => '2', 'table_no' => '5', 'leads' => 'Asha Rao, Someone Else'], $headers)->assertCreated();
        $this->assertSame('Floor 2 · Table 5', $created->json('data.place'));
        $id = $created->json('data.id');

        $list = $this->getJson($this->url($this->mine, '/tables'), $headers)->assertOk();
        $this->assertContains('Asha Rao', $list->json('data.names'));
        $this->assertContains('polyglots', collect($list->json('data.teams'))->pluck('key')->all());

        $this->patchJson($this->url($this->mine, "/tables/{$id}"), ['team' => 'other'], $headers)->assertUnprocessable();
        $this->patchJson($this->url($this->mine, "/tables/{$id}"), ['team' => 'docs', 'note' => 'Bring a laptop'], $headers)->assertOk()->assertJsonPath('data.note', 'Bring a laptop');
        $this->deleteJson($this->url($this->mine, "/tables/{$id}"), [], $headers)->assertOk();
        $this->assertSame(0, ContributorTable::count());
    }

    public function test_attendees_filter_by_role_and_only_an_admin_sees_and_restores_suppressed_ones(): void
    {
        $asha = AttendeeRoster::create(['event_id' => $this->mine->id, 'name' => 'Asha', 'content_hash' => 'h1', 'is_suppressed' => false]);
        AttendeeRoster::create(['event_id' => $this->mine->id, 'name' => 'Ravi', 'content_hash' => 'h2', 'is_suppressed' => false]);
        $hidden = AttendeeRoster::create(['event_id' => $this->mine->id, 'name' => 'Hidden', 'content_hash' => 'h3', 'is_suppressed' => true]);

        $manager = $this->asManager();
        $this->postJson($this->url($this->mine, "/attendees/{$asha->id}/roles"), ['roles' => ['volunteer']], $manager)->assertOk();

        $this->assertSame(['Asha'], collect($this->getJson($this->url($this->mine, '/attendees?role=volunteer'), $manager)->json('data'))->pluck('name')->all());
        $this->assertSame(['Ravi'], collect($this->getJson($this->url($this->mine, '/attendees?role=none'), $manager)->json('data'))->pluck('name')->all());
        $this->assertSame(2, $this->getJson($this->url($this->mine, '/attendees?status=suppressed'), $manager)->json('meta.total'), 'a manager never sees suppressed entries');
        $this->postJson($this->url($this->mine, "/attendees/{$hidden->id}/unsuppress"), [], $manager)->assertForbidden();

        $admin = $this->asAdmin();
        $this->assertSame(['Hidden'], collect($this->getJson($this->url($this->mine, '/attendees?status=suppressed'), $admin)->json('data'))->pluck('name')->all());
        $this->postJson($this->url($this->mine, "/attendees/{$hidden->id}/unsuppress"), [], $admin)->assertOk();
        $this->assertFalse($hidden->fresh()->is_suppressed);
        $this->postJson($this->url($this->mine, "/attendees/{$asha->id}/suppress"), [], $admin)->assertOk();
        $this->assertTrue($asha->fresh()->is_suppressed);
    }

    public function test_the_admin_only_parts_answer_403_to_a_manager(): void
    {
        $headers = $this->asManager();

        foreach (['/deals', '/leads', '/activity'] as $path) {
            $this->getJson($this->url($this->mine, $path), $headers)->assertForbidden();
        }
        $this->postJson($this->url($this->mine, '/deals'), ['title' => 'x', 'description' => 'y', 'url' => 'https://example.org'], $headers)->assertForbidden();
        $this->postJson($this->url($this->mine, '/fetch/schedule'), [], $headers)->assertForbidden();
        $this->deleteJson($this->url($this->mine), ['confirm' => 'wordcamp-mine'], $headers)->assertForbidden();
        $this->assertNotNull($this->mine->fresh());

        foreach (['dashboard', 'errors', 'feedback', 'managers', 'activity', 'event-options'] as $page) {
            $this->getJson("/api/v1/app/organizer/admin/{$page}", $headers)->assertForbidden();
        }
        $this->postJson('/api/v1/app/organizer/admin/cache/purge', [], $headers)->assertForbidden();
        $this->getJson('/api/v1/app/organizer/admin/dashboard')->assertUnauthorized();
    }

    public function test_deals_leads_and_default_deal_visibility(): void
    {
        $admin = $this->asAdmin();

        $id = $this->postJson($this->url($this->mine, '/deals'), ['brand' => 'Acme', 'title' => '20% off', 'description' => 'For attendees', 'url' => 'https://acme.example.org', 'is_active' => true], $admin)
            ->assertCreated()->json('data.id');
        $this->patchJson($this->url($this->mine, "/deals/{$id}"), ['title' => '30% off', 'description' => 'For attendees', 'url' => 'https://acme.example.org', 'is_active' => false], $admin)
            ->assertOk()->assertJsonPath('data.is_active', false);

        $default = Offer::create(['event_id' => null, 'title' => 'Hosting', 'description' => 'Free month', 'url' => 'https://host.example.org', 'is_active' => true]);
        $this->postJson($this->url($this->mine, "/deals/{$default->id}/visibility"), ['hidden' => true], $admin)->assertOk();
        $deals = $this->getJson($this->url($this->mine, '/deals'), $admin)->assertOk();
        $this->assertSame('30% off', $deals->json('data.own.0.title'));
        $this->assertTrue($deals->json('data.defaults.0.hidden_here'));

        OfferLead::create(['event_id' => $this->mine->id, 'offer_id' => $id, 'name' => 'Lead One', 'email' => 'one@example.org']);
        $leads = $this->getJson($this->url($this->mine, "/leads?offer_id={$id}"), $admin)->assertOk();
        $this->assertSame('one@example.org', $leads->json('data.0.email'));
        $this->assertSame(1, $leads->json('meta.total'));

        // The same leads as the web page's CSV export, with the same filters.
        $csv = $this->get($this->url($this->mine, "/leads/export?offer_id={$id}"), $admin)->assertOk();
        $this->assertStringContainsString('one@example.org', $csv->streamedContent());
        $this->get($this->url($this->mine, '/leads/export'), $this->asManager())->assertForbidden();

        $this->deleteJson($this->url($this->mine, "/deals/{$id}"), [], $admin)->assertOk();
        $this->assertNull(Offer::find($id));
        // An event's deal is only reached through its own event.
        $this->deleteJson($this->url($this->theirs, "/deals/{$default->id}"), [], $admin)->assertNotFound();
    }

    public function test_deleting_an_event_needs_its_slug_typed(): void
    {
        $admin = $this->asAdmin();

        $this->deleteJson($this->url($this->theirs), ['confirm' => 'wrong'], $admin)->assertUnprocessable();
        $this->deleteJson($this->url($this->theirs), ['confirm' => 'wordcamp-theirs'], $admin)->assertOk();
        $this->assertNull(Event::find($this->theirs->id));
    }

    public function test_notifications_list_every_push_for_both_roles(): void
    {
        $rows = $this->getJson($this->url($this->mine, '/notifications'), $this->asManager())->assertOk()->json('data');
        $this->assertContains('reminders', collect($rows)->pluck('key')->all());
    }

    public function test_the_admin_dashboard_errors_and_feedback_pages(): void
    {
        $admin = $this->asAdmin();
        EventFeedback::create(['event_id' => $this->mine->id, 'device_hash' => 'd1', 'rating' => 5, 'comment' => 'Loved it']);
        EventFeedback::create(['event_id' => $this->mine->id, 'device_hash' => 'd2', 'rating' => 3]);

        $this->getJson('/api/v1/app/organizer/admin/dashboard', $admin)->assertOk()
            ->assertJsonPath('data.events_total', 2)->assertJsonPath('data.upcoming_count', 1)->assertJsonStructure(['data' => ['errors' => ['total'], 'next_events', 'drafts']]);
        $this->getJson('/api/v1/app/organizer/admin/errors?period=1', $admin)->assertOk()->assertJsonPath('data.filters.period', '1')->assertJsonStructure(['data' => ['system', 'fetch', 'periods', 'jobs'], 'meta']);

        $feedback = $this->getJson("/api/v1/app/organizer/admin/feedback?event={$this->mine->id}", $admin)->assertOk();
        $this->assertSame(2, $feedback->json('data.summary.0.answers'));
        $this->assertEquals(4, $feedback->json('data.summary.0.average'));
        $this->assertSame([1, 0, 1, 0, 0], $feedback->json('data.summary.0.stars'));
        $this->assertCount(2, $feedback->json('data.items'));
    }

    public function test_analytics_says_what_is_missing_when_ga_is_not_set_up(): void
    {
        config(['analytics.property_id' => null]);

        $this->getJson('/api/v1/app/organizer/admin/analytics?period=7&hide_bots=0', $this->asAdmin())->assertOk()
            ->assertJsonPath('data.error', 'GA_PROPERTY_ID is not set in .env.')
            ->assertJsonPath('data.filters.period', '7')
            ->assertJsonPath('data.filters.bots', false)
            ->assertJsonStructure(['data' => ['periods', 'streams', 'reports', 'events']]);
        $this->getJson('/api/v1/app/organizer/admin/analytics', $this->asManager())->assertForbidden();
    }

    public function test_an_admin_manages_event_managers_and_a_switched_off_one_is_signed_out(): void
    {
        $admin = $this->asAdmin();

        $this->postJson('/api/v1/app/organizer/admin/managers', ['name' => 'New One', 'email' => 'NEW@Example.com', 'phone' => '+91 98765 43210', 'password' => 'short', 'events' => [$this->mine->id]], $admin)->assertUnprocessable();
        $this->postJson('/api/v1/app/organizer/admin/managers', ['name' => 'New One', 'email' => 'NEW@Example.com', 'phone' => '+91 98765 43210', 'password' => 'long-enough-1', 'events' => []], $admin)->assertUnprocessable();
        $id = $this->postJson('/api/v1/app/organizer/admin/managers', ['name' => 'New One', 'email' => 'NEW@Example.com', 'phone' => '+91 98765 43210', 'password' => 'long-enough-1', 'events' => [$this->mine->id]], $admin)
            ->assertCreated()->assertJsonPath('data.email', 'new@example.com')->json('data.id');

        $managerHeaders = $this->asManager();
        $this->getJson('/api/v1/app/organizer/me', $managerHeaders)->assertOk();
        $this->patchJson("/api/v1/app/organizer/admin/managers/{$this->manager->id}", ['name' => $this->manager->name, 'email' => 'mgr@example.com', 'phone' => '+91 90000 00000', 'is_active' => false, 'events' => [$this->mine->id, $this->theirs->id]], $admin)
            ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonCount(2, 'data.events');
        $this->getJson('/api/v1/app/organizer/me', $managerHeaders)->assertUnauthorized();

        $this->assertCount(2, $this->getJson('/api/v1/app/organizer/admin/managers', $admin)->json('data'));
        $this->assertSame(['New One'], collect($this->getJson('/api/v1/app/organizer/admin/managers?q=new', $admin)->json('data'))->pluck('name')->all());
        $this->deleteJson("/api/v1/app/organizer/admin/managers/{$id}", [], $admin)->assertOk();
        $this->assertNull(EventManager::find($id));
    }

    public function test_activity_lists_manager_changes_with_filters(): void
    {
        $this->postJson($this->url($this->mine, '/quests'), ['title' => 'Logged'], $this->asManager())->assertCreated();
        $admin = $this->asAdmin();

        $all = $this->getJson('/api/v1/app/organizer/admin/activity', $admin)->assertOk();
        $this->assertSame('quests', $all->json('data.0.section'));
        $this->assertSame([$this->mine->id], collect($all->json('events'))->pluck('id')->all());
        $this->assertSame([], $this->getJson("/api/v1/app/organizer/admin/activity?event={$this->theirs->id}", $admin)->json('data'));
        $this->assertCount(1, $this->getJson($this->url($this->mine, '/activity'), $admin)->json('data'));
    }

    public function test_an_admin_creates_an_event_and_queues_discovery(): void
    {
        $admin = $this->asAdmin();

        $this->postJson('/api/v1/app/organizer/admin/events', ['slug' => 'wordcamp-new', 'display_name' => 'WordCamp New', 'source_site_url' => 'https://new.wordcamp.org/2027', 'status' => 'draft'], $admin)
            ->assertCreated()->assertJsonPath('data.status', 'draft');
        $this->postJson('/api/v1/app/organizer/admin/events', ['slug' => 'wordcamp-new', 'display_name' => 'Again', 'source_site_url' => 'https://new.wordcamp.org/2027', 'status' => 'draft'], $admin)->assertUnprocessable();

        $this->postJson('/api/v1/app/organizer/admin/events/discover', [], $admin)->assertOk();
        Queue::assertPushed(DiscoverWordCampsJob::class);
        $this->assertCount(3, $this->getJson('/api/v1/app/organizer/admin/event-options', $admin)->json('data'));
    }
}
