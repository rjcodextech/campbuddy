<?php

namespace Tests\Feature;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\EventManagerChange;
use App\Models\RosterMark;
use App\Models\User;
use App\Support\EventData;
use App\Support\RosterRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Roles set by hand (admin Roster, manager Attendees): only the difference
 * from the automatic answer is kept, by Gravatar hash or name, so it outlives
 * a re-imported list row; managers only for their own events, logged.
 */
class RosterMarksTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create(['slug' => 'wc-marks', 'display_name' => 'WordCamp Marks', 'source_site_url' => 'https://marks.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);
    }

    private function entry(string $name, ?string $hash = null): AttendeeRoster
    {
        return AttendeeRoster::create([
            'event_id' => $this->event->id, 'name' => $name, 'links' => [],
            'gravatar_url' => $hash ? "https://secure.gravatar.com/avatar/{$hash}?s=96" : null,
            'content_hash' => hash('sha256', $name.$hash.microtime()), 'is_suppressed' => false,
        ]);
    }

    public function test_an_admin_adds_a_role_and_takes_a_wrong_automatic_one_off(): void
    {
        $rahul = $this->entry('Rahul', 'bbb222bbb222bbb222bbb222bbb222bb');
        EventData::put($this->event->id, 'speakers', [['id' => 7, 'name' => 'Rahul', 'avatar_url' => 'https://secure.gravatar.com/avatar/bbb222bbb222bbb222bbb222bbb222bb']]);
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.events.roster.index', $this->event))->assertOk()->assertSee('Roles of Rahul');

        $this->post(route('admin.events.roster.roles', [$this->event, $rahul]), ['roles' => ['media_partner', 'sponsor']])->assertRedirect();

        $this->assertSame(['sponsor', 'media_partner'], RosterRoles::forEvent($this->event)[$rahul->id]['roles']);
        $this->assertSame(
            ['media_partner' => 'add', 'speaker' => 'remove', 'sponsor' => 'add'],
            RosterMark::orderBy('role')->pluck('action', 'role')->all(),
            'only the difference from what was found automatically is stored'
        );

        // Ticking the automatic answer again leaves no mark for it.
        $this->post(route('admin.events.roster.roles', [$this->event, $rahul]), ['roles' => ['speaker']])->assertRedirect();
        $this->assertSame(0, RosterMark::count());
        $this->assertSame(['speaker'], RosterRoles::forEvent($this->event)[$rahul->id]['roles']);
    }

    public function test_a_mark_outlives_the_list_row_being_replaced(): void
    {
        $asha = $this->entry('Asha Rao', 'aaa111aaa111aaa111aaa111aaa111aa');
        $plain = $this->entry('Vikram Seth');
        $this->actingAs(User::factory()->create());
        $this->post(route('admin.events.roster.roles', [$this->event, $asha]), ['roles' => ['table_lead']]);
        $this->post(route('admin.events.roster.roles', [$this->event, $plain]), ['roles' => ['volunteer']]);

        // The nightly import replaces both rows (new links, new hash).
        $asha->delete();
        $plain->delete();
        $newAsha = $this->entry('Asha Rao', 'aaa111aaa111aaa111aaa111aaa111aa');
        $newVikram = $this->entry('vikram  SETH');
        Cache::flush();

        $roles = RosterRoles::forEvent($this->event);
        $this->assertSame(['table_lead'], $roles[$newAsha->id]['roles'], 'kept by the Gravatar hash');
        $this->assertSame(['volunteer'], $roles[$newVikram->id]['roles'], 'no picture: kept by the name');
    }

    public function test_the_roster_api_shows_hand_marks_at_once(): void
    {
        $asha = $this->entry('Asha Rao');
        $this->getJson('/api/v1/events/wc-marks/roster')->assertOk();

        $this->actingAs(User::factory()->create());
        $this->post(route('admin.events.roster.roles', [$this->event, $asha]), ['roles' => ['sponsor']]);

        $row = collect($this->getJson('/api/v1/events/wc-marks/roster')->json('data'))->firstWhere('name', 'Asha Rao');
        $this->assertSame(['sponsor'], $row['roles']);
    }

    public function test_a_manager_marks_only_their_own_events_and_it_is_logged(): void
    {
        $asha = $this->entry('Asha Rao');
        $other = Event::create(['slug' => 'wc-other', 'display_name' => 'Other', 'source_site_url' => 'https://o.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);
        $stranger = AttendeeRoster::create(['event_id' => $other->id, 'name' => 'Zed', 'links' => [], 'content_hash' => 'z', 'is_suppressed' => false]);
        $manager = EventManager::factory()->create(['is_active' => true]);
        $manager->events()->attach($this->event->id);
        Auth::guard('manager')->setUser($manager);

        $this->get(route('manager.events.attendees', $this->event->id))->assertOk()->assertSee('Asha Rao');
        $this->post(route('manager.events.attendees.roles', [$this->event->id, $asha->id]), ['roles' => ['media_partner']])->assertRedirect();
        $this->assertSame(['media_partner'], RosterRoles::forEvent($this->event)[$asha->id]['roles']);
        $this->assertStringContainsString('Media Partner', EventManagerChange::latest('id')->first()->summary);

        $this->get(route('manager.events.attendees', $other->id))->assertNotFound();
        $this->post(route('manager.events.attendees.roles', [$this->event->id, $stranger->id]), ['roles' => ['sponsor']])->assertNotFound();
        $this->post(route('manager.events.attendees.roles', [$this->event->id, $asha->id]), ['roles' => ['king']])->assertSessionHasErrors();
    }
}
