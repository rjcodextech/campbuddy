<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Offer;
use App\Models\OfferLead;
use App\Models\SessionBookmark;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public, anonymous write endpoints can't be used to flood the database,
 * and the admin's own sign-in helpers can't be hammered.
 */
class AbuseLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::withoutEvents(fn () => Event::create([
            'slug' => 'wc-test',
            'display_name' => 'WordCamp Test 2026',
            'source_site_url' => 'https://test.wordcamp.org/2026',
            'status' => 'active',
            'is_visible' => true,
        ]));
    }

    public function test_bookmark_writes_are_rate_limited(): void
    {
        $event = $this->event();

        for ($i = 1; $i <= 30; $i++) {
            $this->postJson(route('api.bookmarks.store', $event), ['device_id' => 'd1', 'session_id' => $i])->assertCreated();
        }

        $this->postJson(route('api.bookmarks.store', $event), ['device_id' => 'd1', 'session_id' => 31])->assertStatus(429);
    }

    public function test_one_device_cannot_hold_unbounded_reminders(): void
    {
        $event = $this->event();
        foreach (range(1, 150) as $i) {
            SessionBookmark::create(['event_id' => $event->id, 'device_id' => 'd1', 'session_id' => $i, 'reminder_enabled' => true]);
        }

        $this->postJson(route('api.bookmarks.store', $event), ['device_id' => 'd1', 'session_id' => 999])->assertStatus(422);
        // Updating one it already holds is still fine.
        $this->postJson(route('api.bookmarks.store', $event), ['device_id' => 'd1', 'session_id' => 5, 'reminder_enabled' => false])->assertCreated();
    }

    public function test_un_saving_a_session_removes_its_reminder(): void
    {
        $event = $this->event();
        SessionBookmark::create(['event_id' => $event->id, 'device_id' => 'd1', 'session_id' => 7, 'reminder_enabled' => true]);

        $this->deleteJson(route('api.bookmarks.destroy', $event), ['device_id' => 'd1', 'session_id' => 7])->assertNoContent();

        $this->assertSame(0, SessionBookmark::count());
    }

    public function test_a_switched_off_deal_takes_no_leads(): void
    {
        $event = $this->event();
        $offer = Offer::create(['event_id' => $event->id, 'title' => 'Deal', 'description' => 'd', 'url' => 'https://example.com', 'is_active' => false, 'capture_leads' => true]);

        $this->postJson(route('api.offers.leads.store', [$event, $offer]), ['name' => 'Ada', 'email' => 'ada@example.com'])->assertNotFound();
        $this->assertSame(0, OfferLead::count());
    }

    public function test_the_same_person_opening_a_deal_twice_is_one_lead(): void
    {
        $event = $this->event();
        $offer = Offer::create(['event_id' => $event->id, 'title' => 'Deal', 'description' => 'd', 'url' => 'https://example.com', 'is_active' => true, 'capture_leads' => true]);

        $this->postJson(route('api.offers.leads.store', [$event, $offer]), ['name' => 'Ada', 'email' => 'Ada@Example.com'])->assertCreated();
        $this->postJson(route('api.offers.leads.store', [$event, $offer]), ['name' => 'Ada L', 'email' => 'ada@example.com '])->assertCreated();

        $this->assertSame(1, OfferLead::count());
        $this->assertSame('Ada L', OfferLead::first()->name);
    }

    public function test_password_reset_requests_are_rate_limited(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => "nobody{$i}@example.com"]);
        }

        $this->post(route('password.email'), ['email' => 'admin@example.com'])->assertStatus(429);
    }
}
