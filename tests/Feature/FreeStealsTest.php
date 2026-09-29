<?php

namespace Tests\Feature;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\FreeSteal;
use App\Models\FreeStealSuggestion;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\DataVersion;
use App\Support\FreeSteals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Explore → Free Steals: a hand-picked list of free WordPress tools, the
 * same at every event, managed under Admin → Free Steals.
 */
class FreeStealsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake();
    }

    private function event(string $slug = 'jaipur'): Event
    {
        return Event::create([
            'slug' => $slug, 'display_name' => ucfirst($slug), 'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'active', 'is_visible' => true, 'country_code' => 'IN',
        ]);
    }

    private function steal(array $overrides = []): FreeSteal
    {
        static $n = 0;
        $n++;

        return FreeSteal::create($overrides + [
            'name' => "Tool {$n}", 'description' => 'Does a useful thing.', 'maker' => 'Lubus',
            'category' => 'Developer Tools', 'url' => "https://github.com/example/tool-{$n}",
            'is_active' => true, 'sort_order' => $n * 10,
        ]);
    }

    public function test_explore_shows_the_tab_and_the_switched_on_steals_at_every_event(): void
    {
        $this->steal(['name' => 'Visual Blueprint Builder', 'is_featured' => true]);
        $this->steal(['name' => 'Hidden Tool', 'is_active' => false]);

        foreach (['jaipur', 'sylhet'] as $slug) {
            $html = $this->get(route('event.explore', $this->event($slug)))->assertOk()->getContent();

            $this->assertStringContainsString('data-explore-tab="free-steals"', $html);
            $this->assertStringContainsString('Visual Blueprint Builder', $html);
            $this->assertStringContainsString('Featured', $html);
            $this->assertStringNotContainsString('Hidden Tool', $html);
        }
    }

    public function test_the_existing_tabs_are_still_there_in_their_order(): void
    {
        $html = $this->get(route('event.explore', $this->event()))->assertOk()->getContent();

        preg_match_all('/data-explore-tab="([a-z-]+)"/', $html, $tabs);
        $this->assertSame(['people', 'sponsors', 'deals', 'free-steals', 'info'], $tabs[1]);
        $this->assertStringContainsString('Nothing here yet.', $html);
    }

    public function test_only_the_first_twelve_by_order_show(): void
    {
        foreach (range(1, 14) as $i) {
            $this->steal(['name' => sprintf('Steal %02d', $i), 'sort_order' => $i]);
        }

        $html = $this->get(route('event.explore', $this->event()))->assertOk()->getContent();

        $this->assertStringContainsString('Steal 12', $html);
        $this->assertStringNotContainsString('Steal 13', $html);
        $this->assertCount(FreeSteal::SHOWN, FreeSteal::shown());
    }

    public function test_the_link_opens_in_a_new_tab_with_the_default_button(): void
    {
        $this->steal(['name' => 'The Off Switch', 'url' => 'https://wordpress.org/plugins/wp-avoid-slow/']);

        $this->get(route('event.explore', $this->event()))->assertOk()
            ->assertSee('href="https://wordpress.org/plugins/wp-avoid-slow/" target="_blank" rel="noopener"', false)
            ->assertSee('Get it free <svg class="line-icon"', false)->assertSee('#li-external', false);
    }

    public function test_the_icon_comes_from_the_first_known_category_word(): void
    {
        $this->assertSame('sparkles', $this->steal(['category' => 'AI / Developer Tools'])->icon());
        $this->assertSame('columns', $this->steal(['category' => 'Gutenberg / Blocks'])->icon());
        $this->assertSame('zap', $this->steal(['category' => 'Performance / Utilities'])->icon());
        $this->assertSame('sparkles', $this->steal(['category' => 'Something new'])->icon());
    }

    public function test_a_steal_with_a_logo_shows_it_and_one_without_keeps_its_icon(): void
    {
        $logo = MediaAsset::create(['disk' => 'public', 'path' => 'media-library/2026/09/godam.png', 'filename' => 'godam.png', 'mime_type' => 'image/png', 'size' => 1234]);
        $this->steal(['name' => 'GoDAM', 'media_asset_id' => $logo->id]);
        $this->steal(['name' => 'No Logo Tool', 'category' => 'Performance']);

        $html = $this->get(route('event.explore', $this->event()))->assertOk()->getContent();

        $this->assertStringContainsString('<img src="'.$logo->url().'"', $html);
        $this->assertSame(1, substr_count($html, 'media-library/2026/09/godam.png'));
        $this->assertStringContainsString('No Logo Tool', $html);
    }

    public function test_admin_picks_a_logo_and_the_category_field_suggests(): void
    {
        $admin = User::factory()->create();
        $logo = MediaAsset::create(['disk' => 'public', 'path' => 'media-library/2026/09/lubus.png', 'filename' => 'lubus.png', 'mime_type' => 'image/png', 'size' => 1234]);
        $this->steal(['category' => 'Gutenberg / Blocks']);

        $this->actingAs($admin)->get(route('admin.free-steals.create'))->assertOk()
            ->assertSee('list="free-steal-categories"', false)
            ->assertSee('<option value="Gutenberg / Blocks">', false)
            ->assertSee('<option value="WooCommerce">', false)
            ->assertSee('lubus.png');

        $this->actingAs($admin)->post(route('admin.free-steals.store'), [
            'name' => 'Blueprint', 'description' => 'Builds blueprints.', 'maker' => 'Lubus',
            'category' => 'Gutenberg', 'url' => 'https://github.com/lubusIN/blueprint', 'media_asset_id' => $logo->id,
            'is_active' => '1', 'is_featured' => '0',
        ])->assertRedirect(route('admin.free-steals.index'));
        $this->assertSame($logo->id, FreeSteal::where('name', 'Blueprint')->value('media_asset_id'));

        $this->actingAs($admin)->post(route('admin.free-steals.store'), [
            'name' => 'Bad', 'description' => 'x', 'maker' => 'x', 'category' => 'x',
            'url' => 'https://example.com', 'media_asset_id' => 999,
        ])->assertSessionHasErrors('media_asset_id');
    }

    /** @return array<string, string> The shipped logos, link => file. */
    private function shippedLogos(): array
    {
        return (require database_path('migrations/2026_10_05_090500_add_logo_to_free_steals.php'))->logos();
    }

    public function test_every_shipped_steal_but_wordpress_skills_has_a_square_logo_file(): void
    {
        $logos = $this->shippedLogos();

        foreach ($this->shipped() as $steal) {
            if ($steal['name'] === 'WordPress Skills') {
                $this->assertArrayNotHasKey($steal['url'], $logos);

                continue;
            }
            $this->assertArrayHasKey($steal['url'], $logos, $steal['name']);
            $size = getimagesize(public_path('media/free-steals/'.$logos[$steal['url']]));
            $this->assertSame([256, 256, IMAGETYPE_PNG], [$size[0], $size[1], $size[2]], $steal['name']);
        }
        $this->assertCount(16, $logos); // 15 shipped + AcrossAI Pro, added on the live site
        $this->assertFileExists(public_path('media/free-steals/'.$logos['https://r.freemius.com/34763/10087717/']));
    }

    public function test_shipped_logos_go_on_steals_without_one_and_an_admin_pick_stays(): void
    {
        Storage::fake('public');
        FreeSteals::install($this->shipped());
        $own = MediaAsset::create(['disk' => 'public', 'path' => 'media-library/2026/09/mine.png', 'filename' => 'mine.png', 'mime_type' => 'image/png', 'size' => 1234]);
        FreeSteal::where('name', 'GoDAM')->update(['media_asset_id' => $own->id]);

        $this->assertSame(14, FreeSteals::logos($this->shippedLogos()));
        $this->assertSame(0, FreeSteals::logos($this->shippedLogos()));

        $this->assertSame($own->id, FreeSteal::where('name', 'GoDAM')->value('media_asset_id'));
        $this->assertNull(FreeSteal::where('name', 'WordPress Skills')->value('media_asset_id'));
        $studio = FreeSteal::where('name', 'WordPress Studio')->sole()->mediaAsset;
        $this->assertSame('media-library/free-steals/wordpress-studio.png', $studio->path);
        Storage::disk('public')->assertExists($studio->path);
        $this->assertSame(15, MediaAsset::count());
    }

    public function test_category_suggestions_have_no_repeats(): void
    {
        $this->steal(['category' => 'AI']);
        $this->steal(['category' => 'AI']);
        $this->steal(['category' => 'security']);

        $suggestions = FreeSteal::categorySuggestions();

        $this->assertSame(1, count(array_filter($suggestions, fn ($c) => mb_strtolower($c) === 'ai')));
        $this->assertSame(1, count(array_filter($suggestions, fn ($c) => mb_strtolower($c) === 'security')));
        $this->assertContains('Developer Tools', $suggestions);
    }

    /** @return list<array<string, mixed>> The steals written out in the install migration. */
    private function shipped(): array
    {
        return (require database_path('migrations/2026_10_05_090100_install_free_steals.php'))->steals();
    }

    public function test_install_adds_the_shipped_steals_once(): void
    {
        $this->assertSame(16, FreeSteals::install($this->shipped()));
        $this->assertSame(0, FreeSteals::install($this->shipped()));

        $this->assertSame(12, FreeSteal::where('is_active', true)->count());
        $this->assertSame(4, FreeSteal::where('is_featured', true)->count());
        $this->assertSame(
            ['WordPress Skills', 'The Off Switch', 'Thank You Page for WooCommerce', 'Blocks Export Import'],
            FreeSteal::shown()->take(4)->pluck('name')->all()
        );
    }

    public function test_install_leaves_an_edited_steal_alone(): void
    {
        FreeSteals::install($this->shipped());
        FreeSteal::where('name', 'GoDAM')->update(['description' => 'Edited by the admin.']);
        FreeSteal::where('name', 'WP Super Cache')->delete();

        $this->assertSame(1, FreeSteals::install($this->shipped()));
        $this->assertSame('Edited by the admin.', FreeSteal::where('name', 'GoDAM')->value('description'));
    }

    public function test_admin_can_add_edit_and_remove_one(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.free-steals.index'))->assertOk()->assertSee('No Free Steals yet');
        $this->actingAs($admin)->get(route('admin.free-steals.create'))->assertOk();

        $this->actingAs($admin)->post(route('admin.free-steals.store'), [
            'name' => 'GoDAM', 'description' => 'Folders for the Media Library.', 'maker' => 'rtCamp',
            'category' => 'Media / Utilities', 'url' => 'https://github.com/rtCamp/godam',
            'is_active' => '1', 'is_featured' => '0',
        ])->assertRedirect(route('admin.free-steals.index'));

        $steal = FreeSteal::sole();
        $this->assertTrue($steal->is_active);
        $this->assertSame(10, $steal->sort_order);

        $this->actingAs($admin)->get(route('admin.free-steals.index'))->assertOk()->assertSee('GoDAM')->assertSee('Showing');
        $this->actingAs($admin)->get(route('admin.free-steals.edit', $steal))->assertOk()->assertSee('rtCamp');

        $this->actingAs($admin)->put(route('admin.free-steals.update', $steal), [
            'name' => 'GoDAM', 'description' => 'Folders for the Media Library.', 'maker' => 'rtCamp',
            'category' => 'Media', 'url' => 'https://github.com/rtCamp/godam', 'cta_label' => 'Check it out',
            'is_active' => '0', 'is_featured' => '1',
        ])->assertRedirect(route('admin.free-steals.index'));

        $steal->refresh();
        $this->assertFalse($steal->is_active);
        $this->assertTrue($steal->is_featured);
        $this->assertSame(10, $steal->sort_order);
        $this->assertSame('Check it out', $steal->ctaLabel());

        $this->actingAs($admin)->delete(route('admin.free-steals.destroy', $steal))->assertRedirect(route('admin.free-steals.index'));
        $this->assertSame(0, FreeSteal::count());
    }

    public function test_admin_rejects_a_non_web_link(): void
    {
        $this->actingAs(User::factory()->create())->post(route('admin.free-steals.store'), [
            'name' => 'Bad', 'description' => 'x', 'maker' => 'x', 'category' => 'x', 'url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('url');

        $this->assertSame(0, FreeSteal::count());
    }

    public function test_guests_cannot_reach_the_admin_page(): void
    {
        $this->get(route('admin.free-steals.index'))->assertRedirect(route('login'));
        $this->post(route('admin.free-steals.store'), [])->assertRedirect(route('login'));
    }

    // ---- Tabs ---------------------------------------------------------------

    public function test_the_tabs_are_five_icon_cells_and_people_starts_selected(): void
    {
        $html = $this->get(route('event.explore', $this->event()))->assertOk()->getContent();

        $this->assertStringContainsString('class="tab-strip tab-strip--icons"', $html);
        $this->assertMatchesRegularExpression('/class="btn btn--compact" data-explore-tab="people" role="tab" aria-selected="true"/', $html);
        foreach (['sponsors', 'deals', 'free-steals', 'info'] as $tab) {
            $this->assertMatchesRegularExpression('/class="btn btn--compact btn--outline" data-explore-tab="'.$tab.'" role="tab" aria-selected="false"/', $html);
        }
        $this->assertStringContainsString('<span>Info</span>', $html);
    }

    // ---- Made by someone at this WordCamp ------------------------------------

    private function attendee(Event $event, array $links, bool $suppressed = false): AttendeeRoster
    {
        static $i = 0;

        return AttendeeRoster::create([
            'event_id' => $event->id, 'name' => 'Person '.++$i, 'content_hash' => 'h'.$i, 'is_suppressed' => $suppressed,
            'links' => array_map(fn ($url) => ['url' => $url, 'type' => 'website'], $links),
        ]);
    }

    public function test_a_maker_on_the_attendees_page_gets_the_badge_and_goes_first_at_that_event_only(): void
    {
        $jaipur = $this->event('jaipur');
        $delhi = $this->event('delhi');
        $this->steal(['name' => 'Company Tool', 'maker' => 'Automattic']);
        $this->steal(['name' => 'Off Switch', 'maker' => 'Abhishek', 'maker_links' => "https://x.com/Abhishek\nhttps://profiles.wordpress.org/abhishek/"]);
        $this->attendee($jaipur, ['http://twitter.com/abhishek']);

        $here = FreeSteal::forEvent($jaipur);
        $this->assertSame(['Off Switch', 'Company Tool'], $here->pluck('name')->all());
        $this->assertTrue($here->first()->made_here);

        $this->get(route('event.explore', $jaipur))->assertOk()->assertSee('Made by someone at this WordCamp');
        $this->get(route('event.explore', $delhi))->assertOk()->assertDontSee('Made by someone at this WordCamp');
        $this->assertSame(['Company Tool', 'Off Switch'], FreeSteal::forEvent($delhi)->pluck('name')->all());
    }

    public function test_a_removed_attendee_does_not_count(): void
    {
        $event = $this->event();
        $this->steal(['maker_links' => 'profiles.wordpress.org/abhishek']);
        $this->attendee($event, ['https://profiles.wordpress.org/abhishek/'], suppressed: true);

        $this->assertFalse(FreeSteal::forEvent($event)->first()->made_here);
    }

    public function test_links_are_matched_on_the_address_not_a_part_of_it(): void
    {
        $this->assertSame('twitter.com/gaurav', FreeSteal::normalizeLink('https://www.X.com/Gaurav/'));
        $this->assertSame('gauravtiwari.org', FreeSteal::normalizeLink('gauravtiwari.org'));
        $this->assertSame('github.com/wpgaurav', FreeSteal::normalizeLink('http://github.com/wpgaurav?tab=repos'));
        $this->assertNull(FreeSteal::normalizeLink('not a link'));

        $event = $this->event();
        $this->steal(['maker_links' => 'https://github.com/wpgaurav']);
        $this->attendee($event, ['https://github.com/wpgaurav-fan', 'https://github.com']);

        $this->assertFalse(FreeSteal::forEvent($event)->first()->made_here);
    }

    public function test_the_card_reports_its_open_without_the_full_address(): void
    {
        $this->steal(['name' => 'GoDAM', 'url' => 'https://github.com/rtCamp/godam']);

        $this->get(route('event.explore', $this->event()))->assertOk()
            ->assertSee('data-track="free_steal_open" data-track-offer-title="GoDAM" data-track-link-domain="github.com"', false)
            ->assertSee('data-steal-id="', false)->assertSee('data-steal-name="GoDAM" data-steal-maker="Lubus" data-steal-category="Developer Tools" data-position="1"', false);
    }

    // ---- Suggest a Free Steal ------------------------------------------------

    private function suggest(Event $event, array $data)
    {
        return $this->postJson(route('api.free-steal-suggestions.store', $event), $data);
    }

    public function test_a_suggestion_is_stored_for_review_and_not_shown(): void
    {
        $event = $this->event();

        $this->get(route('event.explore', $event))->assertOk()->assertSee('Suggest a Free Steal')->assertSee('tpl-free-steal-suggest', false);

        $this->suggest($event, ['name' => 'Cool Tool', 'url' => 'https://github.com/me/cool', 'maker' => 'Me', 'why' => 'It is cool.', 'email' => 'Me@Example.com'])
            ->assertCreated();
        // The same link again from the same event is one suggestion.
        $this->suggest($event, ['name' => 'Cool Tool!', 'url' => 'https://github.com/me/cool'])->assertCreated();

        $suggestion = FreeStealSuggestion::sole();
        $this->assertSame($event->id, $suggestion->event_id);
        $this->assertSame('me@example.com', $suggestion->email);
        $this->assertSame(0, FreeSteal::count());
        $this->get(route('event.explore', $event))->assertDontSee('Cool Tool');
    }

    public function test_a_suggestion_needs_a_name_and_a_web_link(): void
    {
        $event = $this->event();

        $this->suggest($event, ['url' => 'https://a.example'])->assertJsonValidationErrors('name');
        $this->suggest($event, ['name' => 'X', 'url' => 'javascript:alert(1)'])->assertJsonValidationErrors('url');
        $this->suggest($event, ['name' => 'X', 'url' => 'https://a.example', 'email' => 'nope'])->assertJsonValidationErrors('email');
        $this->assertSame(0, FreeStealSuggestion::count());
    }

    public function test_a_bot_and_a_flood_are_answered_but_not_stored(): void
    {
        $event = $this->event();

        $this->suggest($event, ['name' => 'Spam', 'url' => 'https://spam.example', 'website' => 'https://spam.example'])->assertCreated();
        $this->assertSame(0, FreeStealSuggestion::count());

        foreach (range(1, FreeStealSuggestion::MAX_WAITING) as $i) {
            FreeStealSuggestion::create(['event_id' => $event->id, 'name' => "S{$i}", 'url' => "https://s{$i}.example"]);
        }
        $this->suggest($event, ['name' => 'One more', 'url' => 'https://more.example'])->assertCreated();
        $this->assertSame(FreeStealSuggestion::MAX_WAITING, FreeStealSuggestion::count());
    }

    public function test_admin_adds_a_suggestion_as_a_free_steal_or_dismisses_it(): void
    {
        $admin = User::factory()->create();
        $event = $this->event();
        $keep = FreeStealSuggestion::create(['event_id' => $event->id, 'name' => 'Cool Tool', 'url' => 'https://github.com/me/cool', 'maker' => 'Me', 'why' => 'Handy.', 'email' => 'me@example.com']);
        $drop = FreeStealSuggestion::create(['event_id' => $event->id, 'name' => 'Meh Tool', 'url' => 'https://meh.example']);

        $this->actingAs($admin)->get(route('admin.free-steals.index'))->assertOk()
            ->assertSee('Suggestions from the app (2)')->assertSee('Cool Tool')->assertSee('me@example.com');
        $this->actingAs($admin)->get(route('admin.free-steals.create', ['suggestion' => $keep->id]))->assertOk()
            ->assertSee('value="Cool Tool"', false)->assertSee('name="suggestion_id" value="'.$keep->id.'"', false);

        $this->actingAs($admin)->post(route('admin.free-steals.store'), [
            'name' => 'Cool Tool', 'description' => 'Does a cool thing.', 'maker' => 'Me', 'maker_links' => 'https://profiles.wordpress.org/me',
            'category' => 'Utilities', 'url' => 'https://github.com/me/cool', 'is_active' => '1', 'is_featured' => '0', 'suggestion_id' => $keep->id,
        ])->assertRedirect(route('admin.free-steals.index'));

        $this->assertSame('https://profiles.wordpress.org/me', FreeSteal::sole()->maker_links);
        $this->assertModelMissing($keep);

        $this->actingAs($admin)->delete(route('admin.free-steals.suggestions.dismiss', $drop))->assertRedirect(route('admin.free-steals.index'));
        $this->assertSame(0, FreeStealSuggestion::count());
    }

    // ---- Open apps notice an edit --------------------------------------------

    public function test_an_admin_edit_changes_every_live_events_data_version(): void
    {
        $event = $this->event();
        $steal = $this->steal();
        $before = DataVersion::for($event);

        $this->travel(2)->seconds();
        $this->actingAs(User::factory()->create())->put(route('admin.free-steals.update', $steal), [
            'name' => 'Renamed', 'description' => 'd', 'maker' => 'm', 'category' => 'c', 'url' => $steal->url, 'is_active' => '1', 'is_featured' => '0',
        ])->assertRedirect();

        $this->assertNotSame($before, DataVersion::for($event));
    }

    // ---- The shipped data ----------------------------------------------------

    public function test_every_shipped_steal_is_complete_and_fits_the_admin_form(): void
    {
        $rules = (new \App\Http\Requests\StoreFreeStealRequest)->rules();

        foreach ($this->shipped() as $steal) {
            $validator = \Illuminate\Support\Facades\Validator::make($steal, $rules);
            $this->assertFalse($validator->fails(), $steal['name'].': '.$validator->errors()->first());
            $this->assertNotSame([], (new FreeSteal($steal))->makerLinks(), $steal['name'].' has maker links');
        }

        $urls = array_column($this->shipped(), 'url');
        $this->assertSame($urls, array_unique($urls), 'no link twice');
        $this->assertNotContains('https://github.com/Codeinwp/otter-blocks', $urls, 'Blocks Export Import links to its own plugin page');
    }

    public function test_an_install_from_before_the_data_was_finished_is_completed_once_and_admin_edits_are_kept(): void
    {
        // As 090100 left it on 29 Sep: the Otter repo link, no maker links.
        foreach ($this->shipped() as $steal) {
            FreeSteal::create(['maker_links' => null, 'url' => $steal['name'] === 'Blocks Export Import' ? 'https://github.com/Codeinwp/otter-blocks' : $steal['url']] + $steal);
        }
        FreeSteal::where('name', 'GoDAM')->update(['maker_links' => 'https://example.org/admin-typed']);

        $complete = fn () => (require database_path('migrations/2026_10_05_090400_complete_installed_free_steals.php'))->up();
        $complete();
        $complete();

        $this->assertSame('https://wordpress.org/plugins/blocks-export-import/', FreeSteal::where('name', 'Blocks Export Import')->value('url'));
        $this->assertStringContainsString('https://github.com/HardeepAsrani', FreeSteal::where('name', 'Blocks Export Import')->value('maker_links'));
        $this->assertStringContainsString('https://profiles.wordpress.org/gauravtiwari/', FreeSteal::where('name', 'WordPress Skills')->value('maker_links'));
        $this->assertSame('https://example.org/admin-typed', FreeSteal::where('name', 'GoDAM')->value('maker_links'));
        $this->assertSame(16, FreeSteal::count());
    }

    public function test_a_shipped_maker_on_the_attendees_page_gets_the_badge(): void
    {
        FreeSteals::install($this->shipped());
        $event = $this->event();
        // Attendee lists write X as twitter.com and often drop the trailing slash.
        $this->attendee($event, ['http://twitter.com/fitehal']);

        $first = FreeSteal::forEvent($event)->first();
        $this->assertSame('The Off Switch', $first->name);
        $this->assertTrue($first->made_here);
    }
}
