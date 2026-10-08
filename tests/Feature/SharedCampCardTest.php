<?php

namespace Tests\Feature;

use App\Models\AttendeeRoster;
use App\Models\DiscoveryProfile;
use App\Models\Event;
use App\Models\SharedCampCard;
use App\Models\User;
use App\Support\EventTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * "Show my Camp Card on the attendee list": opt-in, only the card's own
 * fields, one card per name, owner token for every change, and it leaves
 * with the name.
 */
class SharedCampCardTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create(['slug' => 'wc-share', 'display_name' => 'WordCamp Share', 'source_site_url' => 'https://share.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);
    }

    private function entry(string $name): AttendeeRoster
    {
        return AttendeeRoster::create(['event_id' => $this->event->id, 'name' => $name, 'links' => [], 'content_hash' => hash('sha256', $name), 'is_suppressed' => false]);
    }

    private function share(array $body, ?string $token = null, string $method = 'POST', string $path = '')
    {
        return $this->withHeaders($token ? ['Authorization' => "Bearer {$token}"] : [])
            ->json($method, "/api/v1/events/wc-share/camp-card-share{$path}", $body);
    }

    private function card(): array
    {
        return [
            'role' => '  WordPress   Engineer ',
            'interests' => ['Blocks', 'Blocks', ''],
            'askMeAbout' => 'Multisite',
            'qr' => ['type' => 'linkedin', 'url' => 'https://linkedin.com/in/ada'],
            'secret' => 'never stored',
        ];
    }

    public function test_sharing_stores_only_the_card_fields_and_returns_the_token_once(): void
    {
        $ada = $this->entry('Ada Lovelace');

        $reply = $this->share(['attendee_roster_id' => $ada->id, 'card' => $this->card()])->assertCreated();

        $this->assertNotEmpty($reply->json('owner_token'));
        $stored = SharedCampCard::first();
        $this->assertSame($ada->id, $stored->attendee_roster_id);
        $this->assertSame([
            'role' => 'WordPress Engineer',
            'interests' => ['Blocks'],
            'askMeAbout' => 'Multisite',
            'qr' => ['type' => 'linkedin', 'url' => 'https://linkedin.com/in/ada'],
        ], $stored->fields);
        $this->assertNotSame($reply->json('owner_token'), $stored->owner_token_hash);
        $this->assertEquals(EventTime::retentionEnd($this->event), $stored->expires_at);
    }

    public function test_the_roster_shows_the_card_next_to_the_name(): void
    {
        $ada = $this->entry('Ada Lovelace');
        $this->entry('Alan Turing');
        $this->share(['attendee_roster_id' => $ada->id, 'card' => $this->card()])->assertCreated();
        Cache::flush();

        $rows = collect($this->getJson('/api/v1/events/wc-share/roster')->assertOk()->json('data'))->keyBy('name');

        $this->assertSame('WordPress Engineer', $rows['Ada Lovelace']['camp_card']['role']);
        $this->assertNull($rows['Alan Turing']['camp_card']);
    }

    public function test_a_link_must_be_a_web_address(): void
    {
        $ada = $this->entry('Ada Lovelace');

        $this->share(['attendee_roster_id' => $ada->id, 'card' => ['qr' => ['type' => 'website', 'url' => 'javascript:alert(1)']]])->assertUnprocessable();
        $this->assertSame(0, SharedCampCard::count());
    }

    public function test_one_card_per_name_and_a_discovery_name_only_from_its_own_phone(): void
    {
        $ada = $this->entry('Ada Lovelace');
        $this->share(['attendee_roster_id' => $ada->id, 'card' => $this->card()])->assertCreated();
        $this->share(['attendee_roster_id' => $ada->id, 'card' => $this->card()])->assertUnprocessable();

        $alan = $this->entry('Alan Turing');
        $credentials = DiscoveryProfile::generateCredentials();
        DiscoveryProfile::create([
            'discovery_id' => $credentials['discovery_id'], 'owner_token_hash' => $credentials['owner_token_hash'],
            'event_id' => $this->event->id, 'attendee_roster_id' => $alan->id, 'fields' => ['tags' => ['developer']],
        ]);

        $this->share(['attendee_roster_id' => $alan->id, 'card' => $this->card()])->assertUnprocessable();
        $this->share(['attendee_roster_id' => $alan->id, 'card' => $this->card(), 'discovery_id' => $credentials['discovery_id'], 'discovery_token' => 'wrong'])->assertUnprocessable();
        $this->share(['attendee_roster_id' => $alan->id, 'card' => $this->card(), 'discovery_id' => $credentials['discovery_id'], 'discovery_token' => $credentials['owner_token']])->assertCreated();
    }

    public function test_only_the_owner_token_changes_or_removes_it(): void
    {
        $ada = $this->entry('Ada Lovelace');
        $reply = $this->share(['attendee_roster_id' => $ada->id, 'card' => $this->card()])->assertCreated();
        $id = $reply->json('share_id');
        $token = $reply->json('owner_token');

        $this->share(['attendee_roster_id' => $ada->id, 'card' => ['city' => 'Jaipur']], 'wrong', 'PUT', "/{$id}")->assertForbidden();
        $this->share(['attendee_roster_id' => $ada->id, 'card' => ['city' => 'Jaipur']], $token, 'PUT', "/{$id}")->assertOk();
        $this->assertSame(['city' => 'Jaipur'], SharedCampCard::first()->fields);

        $this->share([], 'wrong', 'DELETE', "/{$id}")->assertForbidden();
        $this->share([], $token, 'DELETE', "/{$id}")->assertNoContent();
        $this->assertSame(0, SharedCampCard::count());
    }

    public function test_it_leaves_with_the_name_and_an_admin_can_take_it_off(): void
    {
        $ada = $this->entry('Ada Lovelace');
        $this->share(['attendee_roster_id' => $ada->id, 'card' => $this->card()])->assertCreated();
        $ada->delete();
        $this->assertSame(0, SharedCampCard::count(), 'gone with the list entry (the source page opt-out wins)');

        $alan = $this->entry('Alan Turing');
        $this->share(['attendee_roster_id' => $alan->id, 'card' => $this->card()])->assertCreated();
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.events.roster.index', $this->event))->assertOk()->assertSee('Remove card');
        $this->post(route('admin.events.roster.remove-card', [$this->event, $alan]))->assertRedirect();
        $this->assertSame(0, SharedCampCard::count());
    }

    public function test_a_hidden_name_cannot_be_used(): void
    {
        $ada = $this->entry('Ada Lovelace');
        $ada->update(['is_suppressed' => true]);

        $this->share(['attendee_roster_id' => $ada->id, 'card' => $this->card()])->assertUnprocessable();
    }
}
