<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Support\DefaultDeals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Knit Pay default deal's new wording (migration 2026_10_05_090300 and
 * the DefaultDeals catalogue): updated only while it still has the first
 * wording, and fits the deal form's limits.
 */
class KnitPayDealUpdateTest extends TestCase
{
    use RefreshDatabase;

    private const FIRST = 'Take payments on your WordPress site through Indian payment gateways and UPI with Knit Pay. Fill in the short form and the Knit Pay team sets up your special plan.';

    private function runMigration(): void
    {
        (require database_path('migrations/2026_10_05_090300_update_knit_pay_default_deal.php'))->up();
    }

    private function deal(array $overrides = []): Offer
    {
        return Offer::create($overrides + [
            'event_id' => null, 'countries' => ['IN'], 'brand' => 'Knit Pay', 'title' => '100 free transactions a month for 6 months',
            'highlight' => '100 FREE / MONTH', 'description' => self::FIRST, 'terms' => 'Old terms.',
            'url' => 'https://www.knitpay.org/', 'is_active' => true, 'capture_leads' => true,
        ]);
    }

    public function test_an_untouched_deal_gets_the_new_wording_and_keeps_the_rest(): void
    {
        $deal = $this->deal();

        $this->runMigration();
        $deal->refresh();

        $this->assertSame('Knit Pay Pro', $deal->brand);
        $this->assertSame('100 free transactions every month for 6 months', $deal->title);
        $this->assertStringContainsString("special plan.\n\n1. Unlimited free transactions", $deal->description);
        $this->assertStringEndsWith('3. 200 free UPI payment requests every month in the Knit Pay UPI plugin for 6 months.', $deal->description);
        $this->assertSame('Use the email of your RapidAPI account.', $deal->terms);
        $this->assertSame('100 FREE / MONTH', $deal->highlight);
        $this->assertTrue($deal->capture_leads);
    }

    public function test_an_admins_edit_and_an_events_own_deal_are_left_alone(): void
    {
        $edited = $this->deal(['description' => 'Written by the admin.']);
        $event = \App\Models\Event::create(['slug' => 'x', 'display_name' => 'X', 'source_site_url' => 'https://x.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);
        $own = $this->deal(['event_id' => $event->id]);

        $this->runMigration();

        $this->assertSame('Knit Pay', $edited->fresh()->brand);
        $this->assertSame('Knit Pay', $own->fresh()->brand);
    }

    public function test_the_catalogue_and_the_migration_say_the_same_and_fit_the_form(): void
    {
        $catalogue = collect(DefaultDeals::catalogue())->firstWhere('url', 'https://www.knitpay.org/');
        $deal = $this->deal();
        $this->runMigration();
        $deal->refresh();

        foreach (['brand', 'title', 'description', 'terms'] as $field) {
            $this->assertSame($catalogue[$field], $deal->{$field}, $field);
        }
        $this->assertLessThanOrEqual(500, mb_strlen($catalogue['description']));
        $this->assertLessThanOrEqual(120, mb_strlen($catalogue['title']));
    }

    public function test_the_card_shows_the_points_on_their_own_lines(): void
    {
        $event = \App\Models\Event::create(['slug' => 'jaipur', 'display_name' => 'J', 'source_site_url' => 'https://jaipur.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true, 'country_code' => 'IN']);
        $this->deal();
        $this->runMigration();

        $this->withoutVite()->get(route('event.explore', $event))->assertOk()
            ->assertSee('Knit Pay Pro')
            ->assertSee("special plan.\n\n1. Unlimited free transactions in the Knit Pay plugin for lifetime.\n2.", false);
    }
}
