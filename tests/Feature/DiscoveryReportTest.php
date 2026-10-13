<?php

namespace Tests\Feature;

use App\Models\DiscoveryProfile;
use App\Models\DiscoveryReport;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Report and block on discovery (App Store 1.2 / Play UGC): an attendee
 * reports a profile; the organizers review it in Admin → Event → Reports.
 */
class DiscoveryReportTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private DiscoveryProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Bus::fake();
        $this->event = Event::withoutEvents(fn () => Event::create([
            'slug' => 'wordcamp-test',
            'display_name' => 'WordCamp Test',
            'source_site_url' => 'https://wordcamp-test.wordcamp.org/2026',
            'status' => 'active',
            'is_visible' => true,
            'starts_on' => now()->addDays(2)->toDateString(),
            'ends_on' => now()->addDays(3)->toDateString(),
            'timezone' => 'Asia/Kolkata',
        ]));
        $credentials = DiscoveryProfile::generateCredentials();
        $this->profile = DiscoveryProfile::create([
            'discovery_id' => $credentials['discovery_id'],
            'owner_token_hash' => $credentials['owner_token_hash'],
            'event_id' => $this->event->id,
            'fields' => ['tags' => ['seo'], 'profession' => 'Marketer', 'display_name' => 'Spammy Sam', 'who_to_meet' => 'Buy my plugin'],
            'expires_at' => now()->addWeek(),
        ]);
    }

    private function report(array $over = [], ?string $discoveryId = null)
    {
        return $this->postJson(
            "/api/v1/events/{$this->event->slug}/discovery/".($discoveryId ?? $this->profile->discovery_id).'/report',
            $over + ['device_id' => 'dev-1', 'reason' => 'spam', 'note' => 'Sells things']
        );
    }

    public function test_a_report_is_stored_with_a_copy_of_the_profile_text(): void
    {
        $this->report()->assertCreated();

        $report = DiscoveryReport::first();
        $this->assertSame('spam', $report->reason);
        $this->assertSame('open', $report->status);
        $this->assertSame('Spammy Sam', $report->snapshot['name']);
        $this->assertSame('Buy my plugin', $report->snapshot['who_to_meet']);
    }

    public function test_one_phone_reporting_twice_keeps_one_report(): void
    {
        $this->report()->assertCreated();
        $this->report(['reason' => 'abusive'])->assertCreated();
        $this->report(['device_id' => 'dev-2'])->assertCreated();

        $this->assertSame(2, DiscoveryReport::count());
        $this->assertSame('abusive', DiscoveryReport::where('reporter_device_id', 'dev-1')->first()->reason);
    }

    public function test_bad_reports_are_refused(): void
    {
        $this->report(['reason' => 'because'])->assertUnprocessable();
        $this->report(['device_id' => ''])->assertUnprocessable();
        $this->report(['note' => str_repeat('x', 301)])->assertUnprocessable();
        $this->report([], str_repeat('a', 64))->assertNotFound();
        $this->assertSame(0, DiscoveryReport::count());
    }

    public function test_the_admin_sees_open_reports_and_can_remove_the_profile(): void
    {
        $this->report()->assertCreated();
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.events.reports', $this->event))
            ->assertOk()
            ->assertSee('Spammy Sam')
            ->assertSee('Spam or selling')
            ->assertSee('Remove profile');

        $this->actingAs($admin)->post(route('admin.events.reports.remove', [$this->event, $this->profile->discovery_id]))->assertRedirect();

        $this->assertNull(DiscoveryProfile::find($this->profile->id));
        $this->assertSame('removed', DiscoveryReport::first()->status);
        $this->assertNotNull(DiscoveryReport::first()->resolved_at);

        $this->actingAs($admin)->get(route('admin.events.reports', $this->event))->assertOk()->assertSee('No open reports.');
    }

    public function test_dismissing_keeps_the_profile(): void
    {
        $this->report()->assertCreated();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.events.reports.dismiss', [$this->event, $this->profile->discovery_id]))
            ->assertRedirect();

        $this->assertNotNull(DiscoveryProfile::find($this->profile->id));
        $this->assertSame('dismissed', DiscoveryReport::first()->status);
    }

    public function test_the_reports_page_is_for_signed_in_admins_only(): void
    {
        $this->get(route('admin.events.reports', $this->event))->assertRedirect();
        $this->post(route('admin.events.reports.remove', [$this->event, $this->profile->discovery_id]))->assertRedirect();
        $this->assertNotNull(DiscoveryProfile::find($this->profile->id));
    }

    public function test_reports_go_when_the_event_goes(): void
    {
        $this->report()->assertCreated();

        $this->event->delete();

        $this->assertSame(0, DiscoveryReport::count());
    }
}
