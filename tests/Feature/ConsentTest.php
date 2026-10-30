<?php

namespace Tests\Feature;

use App\Models\AttendeeRoster;
use App\Models\ConsentLog;
use App\Models\Event;
use App\Models\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DPDP Act 2023: nothing is shared before the person ticks "I agree … and I'm 18 or older, or my parent
 * or guardian agrees" (s.6, s.9), and the proof is kept (consent_logs) — while app versions and cached
 * pages already on phones, which never had the box, keep working.
 */
class ConsentTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::withoutEvents(fn () => Event::create([
            'slug' => 'wc-consent', 'display_name' => 'WordCamp Consent', 'source_site_url' => 'https://consent.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true,
        ]));
    }

    private function newClient(): array
    {
        return ['X-CampBuddy-Client' => 'pwa', 'X-CampBuddy-Device' => (string) Str::uuid(), 'X-CampBuddy-Timestamp' => (string) time()];
    }

    private function join(array $body, array $headers = [])
    {
        return $this->postJson('/api/v1/events/wc-consent/discovery', $body + ['tags' => ['developer']], $headers);
    }

    public function test_a_new_client_cannot_join_discovery_without_the_tick(): void
    {
        $this->join([], $this->newClient())->assertStatus(422)->assertJsonValidationErrors(['consent']);
        $this->join(['consent' => false], $this->newClient())->assertStatus(422);
        $this->assertSame(0, \App\Models\DiscoveryProfile::count(), 'nothing is kept without consent');
    }

    public function test_the_tick_is_logged_with_the_notice_it_agreed_to(): void
    {
        $joined = $this->join(['consent' => true], $this->newClient())->assertCreated();

        $log = ConsentLog::sole();
        $this->assertSame('discovery_profile', $log->subject_type);
        $this->assertSame($joined->json('discovery_id'), $log->subject_identifier);
        $this->assertSame(config('campbuddy.legal.notice_version'), $log->notice_version);
        $this->assertNotNull($log->consented_at);
    }

    public function test_an_older_app_without_the_box_still_joins_and_is_logged_as_legacy(): void
    {
        $this->join([])->assertCreated();
        $this->assertSame('legacy', ConsentLog::sole()->notice_version);
    }

    public function test_editing_a_profile_needs_no_new_tick(): void
    {
        $joined = $this->join(['consent' => true], $this->newClient())->assertCreated();

        $this->withHeaders($this->newClient() + ['Authorization' => 'Bearer '.$joined->json('owner_token')])
            ->patchJson("/api/v1/events/wc-consent/discovery/{$joined->json('discovery_id')}", ['tags' => ['designer']])
            ->assertOk();
    }

    public function test_sharing_a_camp_card_needs_the_tick_but_updating_it_does_not(): void
    {
        $ada = AttendeeRoster::create(['event_id' => $this->event->id, 'name' => 'Ada Lovelace', 'links' => [], 'content_hash' => 'h', 'is_suppressed' => false]);
        $body = ['attendee_roster_id' => $ada->id, 'card' => ['role' => 'Engineer']];

        $this->postJson('/api/v1/events/wc-consent/camp-card-share', $body, $this->newClient())->assertStatus(422)->assertJsonValidationErrors(['consent']);
        $shared = $this->postJson('/api/v1/events/wc-consent/camp-card-share', $body + ['consent' => true], $this->newClient())->assertCreated();
        $this->assertSame(config('campbuddy.legal.notice_version'), ConsentLog::sole()->notice_version);

        $this->withHeaders($this->newClient() + ['Authorization' => 'Bearer '.$shared->json('owner_token')])
            ->putJson("/api/v1/events/wc-consent/camp-card-share/{$shared->json('share_id')}", $body)
            ->assertOk();
    }

    public function test_a_missing_consent_table_never_breaks_joining(): void
    {
        Schema::drop('consent_logs');

        $this->join(['consent' => true], $this->newClient())->assertCreated();
    }

    public function test_a_deal_form_continue_is_logged_with_the_notice(): void
    {
        $offer = Offer::create(['event_id' => $this->event->id, 'brand' => 'Acme', 'title' => 'Deal', 'description' => 'd', 'url' => 'https://acme.example', 'is_active' => true, 'capture_leads' => true]);

        $this->postJson("/api/v1/events/wc-consent/offers/{$offer->id}/leads", ['name' => 'Asha', 'email' => 'asha@example.com'], $this->newClient())->assertCreated();

        $log = ConsentLog::sole();
        $this->assertSame('offer_lead', $log->subject_type);
        $this->assertSame(config('campbuddy.legal.notice_version'), $log->notice_version);
        $this->assertNotSame('asha@example.com', $log->subject_identifier, 'the email itself is not in the log');
        $this->assertLessThanOrEqual(255, mb_strlen((string) $log->user_agent));
    }
}
