<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Offer;
use App\Models\OfferLead;
use App\Models\User;
use App\Support\DataVersion;
use App\Support\DealForm;
use App\Support\DefaultDeals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Default deals (shown at every event in their countries, hideable per
 * event), the richer deal card, and the contact form set per deal.
 */
class DefaultDealsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake();
    }

    private function event(string $slug, string $country): Event
    {
        return Event::create([
            'slug' => $slug, 'display_name' => ucfirst($slug), 'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'active', 'is_visible' => true, 'country_code' => $country,
        ]);
    }

    private function knitPay(array $overrides = []): Offer
    {
        return Offer::create($overrides + [
            'event_id' => null, 'countries' => ['IN'], 'brand' => 'Knit Pay', 'website' => 'knitpay.org',
            'title' => '100 free transactions a month', 'highlight' => '100 FREE', 'description' => 'Payments for WordPress.',
            'url' => 'https://www.knitpay.org/', 'is_active' => true, 'capture_leads' => true, 'opens_in_app' => false,
            'lead_form' => DealForm::normalize([
                'fields' => [
                    'name' => ['mode' => 'optional'],
                    'company' => ['mode' => 'optional', 'label' => 'Company name'],
                    'email' => ['label' => 'Registered email at RapidAPI'],
                    'mobile' => ['mode' => 'optional', 'label' => 'Phone number', 'hint' => 'Recommended'],
                ],
                'choices' => ['mode' => 'required', 'label' => 'Need a special plan for', 'multiple' => true, 'options' => ['Knit Pay - Pro', 'Knit Pay - UPI']],
            ]),
        ]);
    }

    // ---- Where a default deal shows ---------------------------------------

    public function test_a_default_deal_shows_at_every_event_in_its_country_after_the_events_own(): void
    {
        $jaipur = $this->event('jaipur', 'IN');
        $delhi = $this->event('delhi', 'IN');
        $sylhet = $this->event('sylhet', 'BD');
        $jaipur->offers()->create(['title' => 'Local sponsor', 'description' => 'd', 'url' => 'https://local.example']);
        $this->knitPay();

        $this->assertSame(['Local sponsor', 'Knit Pay'], Offer::shownAt($jaipur)->map->displayName()->all());
        $this->assertSame(['Knit Pay'], Offer::shownAt($delhi)->map->displayName()->all());
        $this->assertSame([], Offer::shownAt($sylhet)->all());

        // An event found later in the same country gets it too.
        $this->assertSame(['Knit Pay'], Offer::shownAt($this->event('pune', 'IN'))->map->displayName()->all());

        $this->get(route('event.explore', $jaipur))->assertOk()
            ->assertSeeInOrder(['Local sponsor', 'Knit Pay', 'knitpay.org', '100 FREE', '100 free transactions a month', 'Short form first']);
        $this->get(route('event.explore', $sylhet))->assertOk()->assertDontSee('Knit Pay');
    }

    public function test_no_countries_means_every_country_and_a_switched_off_deal_shows_nowhere(): void
    {
        $sylhet = $this->event('sylhet', 'BD');
        $everywhere = $this->knitPay(['countries' => null]);

        $this->assertCount(1, Offer::shownAt($sylhet));

        $everywhere->update(['is_active' => false]);
        $this->assertCount(0, Offer::shownAt($sylhet));
    }

    public function test_an_event_can_hide_a_default_deal_and_show_it_again(): void
    {
        $jaipur = $this->event('jaipur', 'IN');
        $delhi = $this->event('delhi', 'IN');
        $deal = $this->knitPay();
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.events.offers.index', $jaipur))->assertOk()
            ->assertSee('Knit Pay')->assertSee('Hide here');

        $this->actingAs($admin)->post(route('admin.events.offers.visibility', [$jaipur, $deal]), ['hidden' => 1])
            ->assertRedirect(route('admin.events.offers.index', $jaipur));

        $this->assertCount(0, Offer::shownAt($jaipur));
        $this->assertCount(1, Offer::shownAt($delhi), 'hidden at one event only');
        $this->postJson(route('api.offers.leads.store', [$jaipur, $deal]), ['email' => 'a@example.com', 'choices' => ['Knit Pay - UPI']])->assertNotFound();

        $this->actingAs($admin)->post(route('admin.events.offers.visibility', [$jaipur, $deal]), ['hidden' => 0]);
        $this->assertCount(1, Offer::shownAt($jaipur));
    }

    public function test_the_data_version_changes_when_a_default_deal_is_added_or_hidden(): void
    {
        $jaipur = $this->event('jaipur', 'IN');
        $before = DataVersion::for($jaipur);

        $deal = $this->knitPay();
        DataVersion::forget($jaipur->id);
        $added = DataVersion::for($jaipur);
        $this->assertNotSame($before, $added);

        $deal->hiddenAtEvents()->attach($jaipur->id);
        DataVersion::forget($jaipur->id);
        $this->assertNotSame($added, DataVersion::for($jaipur));
    }

    // ---- The contact form set per deal ------------------------------------

    public function test_a_lead_follows_the_deals_own_form(): void
    {
        $jaipur = $this->event('jaipur', 'IN');
        $deal = $this->knitPay();
        $url = route('api.offers.leads.store', [$jaipur, $deal]);

        // Email and a product are required; the name is optional here.
        $this->postJson($url, ['choices' => ['Knit Pay - Pro']])->assertJsonValidationErrors('email');
        $this->postJson($url, ['email' => 'dev@example.com'])->assertJsonValidationErrors('choices');
        $this->postJson($url, ['email' => 'dev@example.com', 'choices' => ['Something else']])->assertJsonValidationErrors('choices.0');

        $this->postJson($url, [
            'email' => 'Dev@Example.com', 'company' => ' Ariham ', 'mobile' => '+91 98765 43210',
            'choices' => ['Knit Pay - Pro', 'Knit Pay - UPI'],
        ])->assertCreated();

        $lead = OfferLead::sole();
        $this->assertNull($lead->name);
        $this->assertSame('Ariham', $lead->company);
        $this->assertSame('dev@example.com', $lead->email);
        $this->assertSame(['Knit Pay - Pro', 'Knit Pay - UPI'], $lead->choices);
        $this->assertSame($jaipur->id, $lead->event_id, 'a default deal\'s lead remembers its event');

        // The same person at another event is a lead for that event.
        $delhi = $this->event('delhi', 'IN');
        $this->postJson(route('api.offers.leads.store', [$delhi, $deal]), ['email' => 'dev@example.com', 'choices' => ['Knit Pay - UPI']])->assertCreated();
        $this->assertSame(2, OfferLead::count());

        // Not shown in Bangladesh, so no lead from there.
        $sylhet = $this->event('sylhet', 'BD');
        $this->postJson(route('api.offers.leads.store', [$sylhet, $deal]), ['email' => 'dev@example.com', 'choices' => ['Knit Pay - UPI']])->assertNotFound();
    }

    public function test_a_field_switched_off_cannot_be_sent_and_one_choice_means_one(): void
    {
        $jaipur = $this->event('jaipur', 'IN');
        $deal = $this->knitPay(['lead_form' => DealForm::normalize([
            'fields' => ['company' => ['mode' => 'off'], 'mobile' => ['mode' => 'required']],
            'choices' => ['mode' => 'optional', 'multiple' => false, 'options' => ['A', 'B']],
        ])]);
        $url = route('api.offers.leads.store', [$jaipur, $deal]);

        $this->postJson($url, ['name' => 'Ada', 'email' => 'a@example.com', 'mobile' => '1', 'company' => 'X'])->assertJsonValidationErrors('company');
        $this->postJson($url, ['name' => 'Ada', 'email' => 'a@example.com'])->assertJsonValidationErrors('mobile');
        $this->postJson($url, ['name' => 'Ada', 'email' => 'a@example.com', 'mobile' => '1', 'choices' => ['A', 'B']])->assertJsonValidationErrors('choices');
        $this->postJson($url, ['name' => 'Ada', 'email' => 'a@example.com', 'mobile' => '1'])->assertCreated();
    }

    public function test_an_older_deal_keeps_its_old_form(): void
    {
        $jaipur = $this->event('jaipur', 'IN');
        $deal = $jaipur->offers()->create(['title' => 'Deal', 'description' => 'd', 'url' => 'https://example.com', 'capture_leads' => true]);
        $url = route('api.offers.leads.store', [$jaipur, $deal]);

        $this->postJson($url, ['email' => 'a@example.com'])->assertJsonValidationErrors('name');
        $this->postJson($url, ['name' => 'Ada', 'email' => 'a@example.com'])->assertCreated();

        $form = $deal->leadForm();
        $this->assertSame(['required', 'off', 'required', 'optional'], array_column($form['fields'], 'mode'));
        $this->assertSame('off', $form['choices']['mode']);
    }

    public function test_the_card_shows_what_the_deal_says_and_how_it_opens(): void
    {
        $jaipur = $this->event('jaipur', 'IN');
        Offer::create([
            'event_id' => null, 'countries' => ['IN'], 'brand' => 'Hostinger', 'website' => 'hostinger.com',
            'title' => '20% off your first plan', 'highlight' => '20% OFF', 'description' => 'Hosting and domains.',
            'terms' => 'First plan only.', 'coupon_code' => 'WCRAJ', 'url' => 'https://www.hostinger.com/in?REFERRALCODE=X',
            'cta_label' => 'Claim 20% off', 'opens_in_app' => false, 'is_active' => true,
        ]);
        $jaipur->offers()->create(['title' => 'Old style', 'description' => 'Plain', 'url' => 'https://old.example']);

        $html = $this->get(route('event.explore', $jaipur))->assertOk()->getContent();

        $this->assertStringContainsString('href="https://www.hostinger.com/in?REFERRALCODE=X" target="_blank" rel="noopener sponsored"', $html);
        $this->assertStringContainsString('Claim 20% off', $html);
        $this->assertStringContainsString('First plan only.', $html);
        $this->assertStringContainsString('data-copy-code="WCRAJ"', $html);
        // A deal from before keeps opening inside the app.
        $this->assertStringContainsString('data-inapp-url="https://old.example"', $html);
        $this->assertStringContainsString('old.example', $html, 'website falls back to the link\'s domain');
    }

    // ---- Admin ------------------------------------------------------------

    public function test_an_admin_manages_default_deals_and_their_form(): void
    {
        $admin = User::factory()->create();
        $this->event('jaipur', 'IN');

        $this->actingAs($admin)->get(route('admin.deals.create'))->assertOk()->assertSee('Contact form');

        $this->actingAs($admin)->post(route('admin.deals.store'), [
            'brand' => 'Knit Pay', 'title' => '100 free', 'description' => 'd', 'url' => 'https://www.knitpay.org/',
            'countries' => 'in, bd', 'opens_in_app' => '0', 'capture_leads' => '1', 'is_active' => '1',
            'lead_form' => [
                'fields' => ['name' => ['mode' => 'optional', 'label' => 'Name'], 'email' => ['mode' => 'off', 'label' => 'Registered email at RapidAPI']],
                'choices' => ['mode' => 'required', 'label' => 'Plan', 'multiple' => '1', 'options' => "Knit Pay - Pro\r\nKnit Pay - UPI\r\n\r\nKnit Pay - Pro"],
            ],
        ])->assertRedirect(route('admin.deals.index'))->assertSessionHasNoErrors();

        $deal = Offer::defaults()->sole();
        $this->assertSame(['IN', 'BD'], $deal->countries);
        $this->assertFalse($deal->opens_in_app);
        $form = $deal->leadForm();
        $this->assertSame('required', $form['fields']['email']['mode'], 'email can never be switched off');
        $this->assertSame('Registered email at RapidAPI', $form['fields']['email']['label']);
        $this->assertSame(['Knit Pay - Pro', 'Knit Pay - UPI'], $form['choices']['options']);

        $this->actingAs($admin)->get(route('admin.deals.index'))->assertOk()->assertSee('Knit Pay')->assertSee('IN, BD')->assertSee('1 upcoming event');
        $this->actingAs($admin)->get(route('admin.deals.edit', $deal))->assertOk()->assertSee('Knit Pay - UPI');
        $this->actingAs($admin)->get(route('admin.deals.leads', $deal))->assertOk();
    }

    public function test_a_deal_is_edited_only_from_where_it_belongs(): void
    {
        $admin = User::factory()->create();
        $jaipur = $this->event('jaipur', 'IN');
        $delhi = $this->event('delhi', 'IN');
        $own = $jaipur->offers()->create(['title' => 'Jaipur deal', 'description' => 'd', 'url' => 'https://a.example']);
        $default = $this->knitPay();

        $this->actingAs($admin)->get(route('admin.events.offers.edit', [$delhi, $own]))->assertNotFound();
        $this->actingAs($admin)->put(route('admin.events.offers.update', [$delhi, $own]), ['title' => 'X', 'description' => 'd', 'url' => 'https://a.example'])->assertNotFound();
        $this->actingAs($admin)->delete(route('admin.events.offers.destroy', [$jaipur, $default]))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.deals.edit', $own))->assertNotFound();
        $this->assertSame('Jaipur deal', $own->fresh()->title);
        $this->assertNotNull($default->fresh());

        $this->actingAs($admin)->get(route('admin.events.offers.edit', [$jaipur, $own]))->assertOk()->assertSee('Jaipur deal');
    }

    public function test_the_lead_export_carries_the_company_and_ticked_products(): void
    {
        $admin = User::factory()->create();
        $jaipur = $this->event('jaipur', 'IN');
        $deal = $this->knitPay();
        OfferLead::create(['event_id' => $jaipur->id, 'offer_id' => $deal->id, 'email' => 'dev@example.com', 'company' => 'Ariham', 'choices' => ['Knit Pay - Pro', 'Knit Pay - UPI']]);

        foreach ([route('admin.events.deal-leads.export', $jaipur), route('admin.deals.leads.export', $deal)] as $url) {
            $csv = $this->actingAs($admin)->get($url)->assertOk()->streamedContent();
            $this->assertStringContainsString('Deal,Event,Name,Company,Email,Mobile,Options', $csv);
            $this->assertStringContainsString('"Knit Pay",Jaipur,,Ariham,dev@example.com,,"Knit Pay - Pro; Knit Pay - UPI"', $csv);
        }

        $this->actingAs($admin)->get(route('admin.events.deal-leads.index', $jaipur))->assertOk()->assertSee('Knit Pay - Pro, Knit Pay - UPI');
    }

    // ---- The deals CampBuddy ships with ----------------------------------

    public function test_the_shipped_deals_install_once_and_retire_an_older_copy(): void
    {
        Storage::fake('public');
        $jaipur = $this->event('jaipur', 'IN');
        $sylhet = $this->event('sylhet', 'BD');
        $oldCopy = $jaipur->offers()->create(['title' => 'Hostinger', 'description' => '20% off hosting', 'url' => 'https://hostinger.com']);
        $withLeads = $jaipur->offers()->create(['title' => 'Knit Pay (ours)', 'description' => 'd', 'url' => 'https://knitpay.org/x', 'capture_leads' => true]);
        OfferLead::create(['event_id' => $jaipur->id, 'offer_id' => $withLeads->id, 'name' => 'A', 'email' => 'a@example.com']);

        $deals = (require database_path('migrations/2026_10_04_090100_install_default_india_deals.php'))->deals();
        $this->assertSame(4, DefaultDeals::install($deals));
        $this->assertSame(0, DefaultDeals::install($deals), 'never added twice');

        $this->assertSame(['Knit Pay (ours)', 'Ariham Technologies', 'Hostinger', 'Automattic', 'Knit Pay Pro'], Offer::shownAt($jaipur)->map->displayName()->all());
        $this->assertFalse($oldCopy->fresh()->is_active, 'the plain Hostinger copy is switched off, not deleted');
        $this->assertTrue($withLeads->fresh()->is_active, 'a deal with leads is left alone');
        $this->assertSame([], Offer::shownAt($sylhet)->all());

        $knitPay = Offer::defaults()->where('brand', 'Knit Pay Pro')->sole();
        $this->assertTrue($knitPay->capture_leads);
        $this->assertSame(['Knit Pay - Pro', 'Knit Pay - UPI'], $knitPay->leadForm()['choices']['options']);
        $this->assertSame('Registered email at RapidAPI', $knitPay->leadForm()['fields']['email']['label']);
        Storage::disk('public')->assertExists('media-library/deals/knit-pay.png');
        $this->assertNotNull($knitPay->mediaAsset);
    }
}
