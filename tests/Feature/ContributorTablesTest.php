<?php

namespace Tests\Feature;

use App\Models\AttendeeRoster;
use App\Models\ContributorTable;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\EventManagerChange;
use App\Models\User;
use App\Support\RosterRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Contributor Day tables: entered by an admin or the event's managers, shown
 * on the Contribute tab, and their leads marked Table Lead on the list.
 */
class ContributorTablesTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create(['slug' => 'wc-tables', 'display_name' => 'WordCamp Tables', 'source_site_url' => 'https://tables.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);
    }

    private function entry(string $name): AttendeeRoster
    {
        return AttendeeRoster::create(['event_id' => $this->event->id, 'name' => $name, 'links' => [], 'content_hash' => hash('sha256', $name.microtime()), 'is_suppressed' => false]);
    }

    public function test_the_team_list_matches_the_attendee_apps(): void
    {
        $js = file_get_contents(resource_path('js/attendee/contrib-teams.js'));
        preg_match_all("/^\s+id: '([a-z-]+)',/m", $js, $ids);

        $this->assertSame($ids[1], array_values(array_diff(array_keys(config('contributor_teams')), ['other'])));
    }

    public function test_an_admin_adds_a_table_and_its_leads_become_table_leads(): void
    {
        $asha = $this->entry('Asha Rao');
        $this->entry('Same Name');
        $this->entry('Same Name');
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.events.tables.index', $this->event))->assertOk()->assertSee('Add a table');
        $this->post(route('admin.events.tables.store', $this->event), [
            'team' => 'polyglots', 'track' => 'Hall B', 'floor' => '2', 'table_no' => '5',
            'leads' => 'asha  rao, Same Name, Guest Lead', 'note' => 'Bring a laptop',
        ])->assertRedirect();

        $table = ContributorTable::first();
        $this->assertSame([
            ['name' => 'Asha Rao', 'roster_id' => $asha->id],
            ['name' => 'Same Name'],
            ['name' => 'Guest Lead'],
        ], $table->leads, 'a name on the list once keeps its entry; twice or not at all, just the name');
        $this->assertSame('Floor 2 · Hall B · Table 5', $table->place());
        $this->assertSame(['table_lead'], RosterRoles::forEvent($this->event)[$asha->id]['roles']);

        $page = $this->get('/event/wc-tables/contribute')->assertOk();
        $data = json_decode(str($page->getContent())->between('id="contribute-data">', '</script>')->toString(), true);
        $this->assertSame([['team' => 'polyglots', 'name' => 'Polyglots', 'place' => 'Floor 2 · Hall B · Table 5', 'leads' => ['Asha Rao', 'Same Name', 'Guest Lead'], 'note' => 'Bring a laptop']], $data['tables']);

        $this->put(route('admin.events.tables.update', [$this->event, $table]), ['team' => 'polyglots', 'leads' => ''])->assertRedirect();
        $this->assertArrayNotHasKey($asha->id, RosterRoles::forEvent($this->event), 'no longer a lead, no badge');

        $this->delete(route('admin.events.tables.destroy', [$this->event, $table]))->assertRedirect();
        $this->assertSame(0, ContributorTable::count());
    }

    public function test_an_other_table_needs_a_title(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.events.tables.store', $this->event), ['team' => 'other'])->assertSessionHasErrors('title');
        $this->post(route('admin.events.tables.store', $this->event), ['team' => 'other', 'title' => 'Hindi Polyglots'])->assertRedirect();
        $this->assertSame('Hindi Polyglots', ContributorTable::first()->teamName());
    }

    public function test_a_manager_edits_only_their_own_events_tables_and_it_is_logged(): void
    {
        $other = Event::create(['slug' => 'wc-other', 'display_name' => 'Other', 'source_site_url' => 'https://o.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);
        $theirs = ContributorTable::create(['event_id' => $other->id, 'team' => 'core']);
        $manager = EventManager::factory()->create(['is_active' => true]);
        $manager->events()->attach($this->event->id);
        Auth::guard('manager')->setUser($manager);

        $this->post(route('manager.events.tables.store', $this->event->id), ['team' => 'design', 'floor' => '1'])->assertRedirect();
        $mine = ContributorTable::where('event_id', $this->event->id)->first();
        $this->assertStringContainsString('Design', EventManagerChange::latest('id')->first()->summary);

        $this->get(route('manager.events.tables', $other->id))->assertNotFound();
        $this->put(route('manager.events.tables.update', [$this->event->id, $theirs->id]), ['team' => 'core'])->assertNotFound();
        $this->delete(route('manager.events.tables.destroy', [$this->event->id, $mine->id]))->assertRedirect();
        $this->assertSame(1, ContributorTable::count());
    }
}
