<?php

namespace Tests\Feature;

use App\Models\AttendeeRoster;
use App\Models\DiscoveryProfile;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Attendee discovery with real people in it: an attendee can pick their own
 * name from the event's attendee list (or type one, or stay anonymous), and
 * matches then show who they are — name, photo, links, WordPress.org — so
 * they can actually be found in the room. One name, one profile.
 */
class DiscoveryIdentityTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = $this->makeEvent('wc-test');
    }

    private function makeEvent(string $slug): Event
    {
        return Event::withoutEvents(fn () => Event::create([
            'slug' => $slug,
            'display_name' => 'WordCamp '.$slug,
            'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'active',
            'is_visible' => true,
        ]));
    }

    private function attendee(string $name, ?Event $event = null, bool $suppressed = false): AttendeeRoster
    {
        return AttendeeRoster::create([
            'event_id' => ($event ?? $this->event)->id,
            'name' => $name,
            'gravatar_url' => 'https://secure.gravatar.com/avatar/'.md5($name),
            'links' => [['type' => 'linkedin', 'url' => 'https://www.linkedin.com/in/x'], ['type' => 'website', 'url' => 'javascript:alert(1)']],
            'content_hash' => hash('sha256', $name),
            'is_suppressed' => $suppressed,
        ]);
    }

    private function join(array $body)
    {
        return $this->postJson(route('api.discovery.store', $this->event), $body + ['tags' => ['developer']]);
    }

    private function cards(): array
    {
        return $this->getJson(route('api.discovery.index', $this->event))->assertOk()->json('data');
    }

    public function test_picking_yourself_from_the_attendee_list_shows_your_public_details(): void
    {
        $ada = $this->attendee('Ada Lovelace');

        $this->join(['attendee_roster_id' => $ada->id, 'wporg_username' => 'https://profiles.wordpress.org/AdaL/', 'profession' => 'Engineer'])
            ->assertCreated()
            ->assertJsonPath('name', 'Ada Lovelace')
            ->assertJsonStructure(['owner_token']);

        [$card] = $this->cards();

        $this->assertSame('Ada Lovelace', $card['name']);
        $this->assertTrue($card['on_attendee_list']);
        $this->assertSame($ada->id, $card['roster_id'], "the list's own id, so the app can tell the entry and the match are one person");
        $this->assertStringStartsWith('https://secure.gravatar.com/', $card['avatar_url']);
        $this->assertSame([['type' => 'linkedin', 'url' => 'https://www.linkedin.com/in/x']], $card['links'], 'only web links');
        $this->assertSame('https://profiles.wordpress.org/adal/', $card['wporg_url']);
        $this->assertSame('Engineer', $card['fields']['profession']);
        $this->assertArrayNotHasKey('owner_token', $card);
        $this->assertArrayNotHasKey('owner_token_hash', $card);
    }

    public function test_the_attendee_lists_name_wins_over_a_typed_one(): void
    {
        $ada = $this->attendee('Ada Lovelace');

        $this->join(['attendee_roster_id' => $ada->id, 'display_name' => 'Someone Else'])->assertCreated();

        $this->assertSame('Ada Lovelace', $this->cards()[0]['name']);
    }

    public function test_a_typed_name_is_shown_but_not_marked_as_on_the_list(): void
    {
        $this->join(['display_name' => "  Marco \u{200B} from   Lisbon "])->assertCreated();

        $card = $this->cards()[0];
        $this->assertSame('Marco from Lisbon', $card['name']);
        $this->assertFalse($card['on_attendee_list']);
        $this->assertNull($card['roster_id']);
        $this->assertNull($card['avatar_url']);
    }

    public function test_anonymous_profiles_still_work(): void
    {
        $this->join([])->assertCreated();

        $card = $this->cards()[0];
        $this->assertNull($card['name']);
        $this->assertFalse($card['on_attendee_list']);
        $this->assertNull($card['roster_id']);
    }

    public function test_one_name_can_be_claimed_by_one_profile_only(): void
    {
        $ada = $this->attendee('Ada Lovelace');
        $this->join(['attendee_roster_id' => $ada->id])->assertCreated();

        $this->join(['attendee_roster_id' => $ada->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attendee_roster_id' => 'already linked']);
    }

    public function test_only_this_events_visible_names_can_be_picked(): void
    {
        $elsewhere = $this->attendee('Ada Lovelace', $this->makeEvent('other'));
        $hidden = $this->attendee('Grace Hopper', suppressed: true);

        $this->join(['attendee_roster_id' => $elsewhere->id])->assertJsonValidationErrors('attendee_roster_id');
        $this->join(['attendee_roster_id' => $hidden->id])->assertJsonValidationErrors('attendee_roster_id');
    }

    public function test_a_bad_wordpress_org_username_is_explained(): void
    {
        $this->join(['wporg_username' => 'not a <username>'])
            ->assertJsonValidationErrors(['wporg_username' => 'WordPress.org username']);
    }

    public function test_an_update_keeps_its_own_claim_and_leaving_frees_the_name(): void
    {
        $ada = $this->attendee('Ada Lovelace');
        $created = $this->join(['attendee_roster_id' => $ada->id])->json();
        $headers = ['Authorization' => 'Bearer '.$created['owner_token']];

        $this->patchJson(route('api.discovery.update', [$this->event, $created['discovery_id']]), ['tags' => ['designer'], 'attendee_roster_id' => $ada->id], $headers)
            ->assertOk()
            ->assertJsonPath('name', 'Ada Lovelace')
            ->assertJsonPath('fields.tags', ['designer']);

        $this->deleteJson(route('api.discovery.destroy', [$this->event, $created['discovery_id']]), [], $headers)->assertNoContent();

        $this->join(['attendee_roster_id' => $ada->id])->assertCreated();
    }

    public function test_someone_elses_token_cannot_take_over_a_profile(): void
    {
        $mine = $this->join([])->json();
        $theirs = $this->join([])->json();

        $this->patchJson(
            route('api.discovery.update', [$this->event, $mine['discovery_id']]),
            ['tags' => ['designer']],
            ['Authorization' => 'Bearer '.$theirs['owner_token']]
        )->assertForbidden();
    }

    public function test_removing_yourself_from_the_attendee_list_also_unlinks_discovery(): void
    {
        $ada = $this->attendee('Ada Lovelace');
        $this->join(['attendee_roster_id' => $ada->id])->assertCreated();

        $this->post(route('event.roster-removal.remove', [$this->event, $ada]))->assertRedirect();

        $this->assertNull(DiscoveryProfile::first()->attendee_roster_id);
        $this->assertNull($this->cards()[0]['name']);
    }

    public function test_an_admin_can_free_a_wrongly_claimed_name(): void
    {
        $ada = $this->attendee('Ada Lovelace');
        $this->join(['attendee_roster_id' => $ada->id])->assertCreated();
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.events.roster.index', $this->event))
            ->assertOk()
            ->assertSee('In discovery');

        $this->actingAs($admin)
            ->post(route('admin.events.roster.release-claim', [$this->event, $ada]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNull(DiscoveryProfile::first()->attendee_roster_id);
        $this->assertSame(1, DiscoveryProfile::count(), 'the profile itself stays');
    }

    public function test_the_roster_marks_who_is_open_to_meet_without_a_query_per_row(): void
    {
        $ada = $this->attendee('Ada Lovelace');
        foreach (range(1, 30) as $i) {
            $this->attendee("Person {$i}");
        }
        $this->join(['attendee_roster_id' => $ada->id])->assertCreated();

        $queries = 0;
        \DB::listen(function () use (&$queries) {
            $queries++;
        });

        $rows = collect($this->getJson(route('api.events.roster', $this->event))->assertOk()->json('data'))->keyBy('name');

        $this->assertTrue($rows['Ada Lovelace']['open_to_meet']);
        $this->assertFalse($rows['Person 1']['open_to_meet']);
        $this->assertSame($ada->id, $rows['Ada Lovelace']['id']);
        $this->assertLessThan(8, $queries);
    }
}
