<?php

namespace Tests\Feature;

use App\Jobs\DiscoverWordCampsJob;
use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\Offer;
use App\Models\User;
use App\Support\DataVersion;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The admin actions no other test drove end to end: editing and removing a
 * default deal, hiding / restoring an attendee-list entry, and queueing
 * discovery.
 */
class AdminActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake();
        $this->actingAs(User::factory()->create());
    }

    private function event(): Event
    {
        return Event::create([
            'slug' => 'wc-jaipur', 'display_name' => 'WordCamp Jaipur', 'source_site_url' => 'https://jaipur.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true, 'country_code' => 'IN',
        ]);
    }

    private function deal(): Offer
    {
        return Offer::create(['event_id' => null, 'countries' => ['IN'], 'brand' => 'Knit Pay Pro', 'title' => 'Old headline', 'description' => 'Old', 'url' => 'https://www.knitpay.org/', 'is_active' => true]);
    }

    public function test_a_default_deal_is_edited_everywhere_at_once(): void
    {
        $event = $this->event();
        $deal = $this->deal();
        $before = DataVersion::for($event);
        $this->travel(2)->seconds();

        $this->put(route('admin.deals.update', $deal), [
            'brand' => 'Knit Pay Pro', 'title' => 'New headline', 'description' => "Line one\nLine two", 'url' => 'https://www.knitpay.org/',
            'countries' => 'IN, BD', 'is_active' => '1', 'opens_in_app' => '0', 'capture_leads' => '0',
        ])->assertRedirect(route('admin.deals.index'))->assertSessionHas('status', 'Default deal updated.');

        $deal->refresh();
        $this->assertSame('New headline', $deal->title);
        $this->assertSame(['IN', 'BD'], $deal->countries);
        $this->assertFalse($deal->opens_in_app);
        $this->assertNotSame($before, DataVersion::for($event), 'open apps are told');

        $this->get(route('event.explore', $event))->assertOk()->assertSee('New headline')->assertSee("Line one\nLine two", false);
    }

    public function test_a_default_deal_needs_a_headline_details_and_a_web_link(): void
    {
        $deal = $this->deal();

        $this->put(route('admin.deals.update', $deal), ['title' => '', 'description' => '', 'url' => 'javascript:alert(1)', 'countries' => 'INDIA'])
            ->assertSessionHasErrors(['title', 'description', 'url', 'countries.0']);
        $this->assertSame('Old headline', $deal->fresh()->title);
    }

    public function test_a_default_deal_is_removed_from_every_event(): void
    {
        $event = $this->event();
        $deal = $this->deal();

        $this->delete(route('admin.deals.destroy', $deal))->assertRedirect(route('admin.deals.index'));

        $this->assertModelMissing($deal);
        $this->get(route('event.explore', $event))->assertOk()->assertDontSee('Old headline');
    }

    public function test_an_events_own_deal_cannot_be_edited_or_removed_as_a_default_one(): void
    {
        $own = Offer::create(['event_id' => $this->event()->id, 'title' => 'Own', 'description' => 'd', 'url' => 'https://a.example', 'is_active' => true]);

        $this->put(route('admin.deals.update', $own), ['title' => 'X', 'description' => 'd', 'url' => 'https://a.example'])->assertNotFound();
        $this->delete(route('admin.deals.destroy', $own))->assertNotFound();
        $this->assertModelExists($own);
    }

    public function test_an_attendee_list_entry_is_hidden_and_restored(): void
    {
        $event = $this->event();
        $entry = AttendeeRoster::create(['event_id' => $event->id, 'name' => 'Asha Rao', 'links' => [], 'content_hash' => 'h', 'is_suppressed' => false]);

        $this->post(route('admin.events.roster.suppress', [$event, $entry]))->assertRedirect(route('admin.events.roster.index', $event));
        $this->assertTrue($entry->fresh()->is_suppressed);
        $this->getJson(route('api.events.roster', $event))->assertOk()->assertDontSee('Asha Rao');

        $this->post(route('admin.events.roster.unsuppress', [$event, $entry]))->assertRedirect(route('admin.events.roster.index', $event));
        $this->assertFalse($entry->fresh()->is_suppressed);
    }

    public function test_discovery_is_queued_from_the_events_page(): void
    {
        $this->post(route('admin.events.discover'))->assertRedirect(route('admin.events.index'));

        Queue::assertPushed(DiscoverWordCampsJob::class);
    }

    public function test_an_unverified_admin_can_ask_for_the_verification_email_again(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
