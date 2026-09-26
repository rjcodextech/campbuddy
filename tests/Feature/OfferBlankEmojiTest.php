<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MediaAsset;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A deal may have no emoji: choosing a logo and clearing the emoji used to fail
 * with "Column 'icon' cannot be null" (the form sends an empty emoji, which the
 * framework turns into NULL, and the column was NOT NULL).
 */
class OfferBlankEmojiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake();
    }

    private function event(): Event
    {
        return Event::create([
            'slug' => 'wc-deals', 'display_name' => 'WordCamp Deals', 'source_site_url' => 'https://wc-deals.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true,
        ]);
    }

    private function logo(): MediaAsset
    {
        return MediaAsset::create(['disk' => 'public', 'path' => 'media-library/2026/09/logo.png', 'filename' => 'logo.png', 'mime_type' => 'image/png', 'size' => 1234]);
    }

    /** @return array<string, mixed> */
    private function form(array $overrides = []): array
    {
        return $overrides + ['title' => 'Hosting deal', 'description' => '20% off', 'url' => 'https://host.example', 'icon' => '🏷', 'sort_order' => 10, 'is_active' => '1', 'capture_leads' => '0'];
    }

    public function test_the_emoji_column_may_be_empty(): void
    {
        $icon = collect(Schema::getColumns('offers'))->firstWhere('name', 'icon');

        $this->assertTrue($icon['nullable']);
    }

    public function test_a_deal_with_a_logo_and_no_emoji_can_be_saved_which_used_to_fail(): void
    {
        $this->actingAs(User::factory()->create());
        $event = $this->event();
        $logo = $this->logo();
        $offer = Offer::create(['event_id' => $event->id, 'title' => 'Hosting deal', 'description' => '20% off', 'url' => 'https://host.example', 'is_active' => true]);
        $this->assertSame('🏷', $offer->fresh()->icon, 'Created without one, it still gets the default.');

        // Exactly what the admin's form sends when a logo is picked and the emoji is cleared.
        $this->put(route('admin.events.offers.update', [$event, $offer]), $this->form(['icon' => '', 'media_asset_id' => $logo->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.events.offers.index', $event));

        $offer->refresh();
        $this->assertNull($offer->icon);
        $this->assertSame($logo->id, $offer->media_asset_id);
    }

    public function test_a_new_deal_can_be_added_with_no_emoji(): void
    {
        $this->actingAs(User::factory()->create());
        $event = $this->event();

        $this->post(route('admin.events.offers.store', $event), $this->form(['icon' => '', 'media_asset_id' => $this->logo()->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertNull($event->offers()->firstOrFail()->icon);
    }

    public function test_an_emoji_that_is_typed_is_kept_and_can_be_changed_back(): void
    {
        $this->actingAs(User::factory()->create());
        $event = $this->event();
        $offer = Offer::create(['event_id' => $event->id, 'title' => 'Deal', 'description' => 'x', 'url' => 'https://a.example', 'icon' => '🎁', 'is_active' => true]);

        $this->put(route('admin.events.offers.update', [$event, $offer]), $this->form(['icon' => '']))->assertSessionHasNoErrors();
        $this->assertNull($offer->fresh()->icon);

        $this->put(route('admin.events.offers.update', [$event, $offer]), $this->form(['icon' => '🚀']))->assertSessionHasNoErrors();
        $this->assertSame('🚀', $offer->fresh()->icon);
    }

    public function test_the_admin_list_and_the_attendee_card_cope_with_no_emoji(): void
    {
        $this->actingAs(User::factory()->create());
        $event = $this->event();
        $logo = $this->logo();
        Offer::create(['event_id' => $event->id, 'title' => 'Logo only deal', 'description' => 'With a logo', 'url' => 'https://a.example', 'icon' => null, 'media_asset_id' => $logo->id, 'is_active' => true, 'sort_order' => 10]);
        Offer::create(['event_id' => $event->id, 'title' => 'Plain deal', 'description' => 'Nothing but words', 'url' => 'https://b.example', 'icon' => null, 'is_active' => true, 'sort_order' => 20]);
        Offer::create(['event_id' => $event->id, 'title' => 'Emoji deal', 'description' => 'With an emoji', 'url' => 'https://c.example', 'icon' => '🎁', 'is_active' => true, 'sort_order' => 30]);

        $this->get(route('admin.events.offers.index', $event))->assertOk()->assertSee('Logo only deal')->assertSee('Plain deal')->assertSee('Emoji deal');

        $this->app['auth']->forgetGuards();
        $page = $this->get(route('event.explore', $event))->assertOk();

        $page->assertSee('Logo only deal')->assertSee('Plain deal')->assertSee('Nothing but words')->assertSee('🎁');
        $page->assertSee($logo->url(), false);
    }

    public function test_deals_saved_before_this_change_are_untouched(): void
    {
        $event = $this->event();
        $offer = Offer::create(['event_id' => $event->id, 'title' => 'Old deal', 'description' => 'x', 'url' => 'https://a.example', 'icon' => '🏷', 'is_active' => true]);

        $this->assertSame('🏷', $offer->fresh()->icon);
        $this->assertSame(1, Offer::count());
    }
}
