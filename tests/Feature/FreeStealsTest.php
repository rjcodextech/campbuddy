<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\FreeSteal;
use App\Models\User;
use App\Support\FreeSteals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
            ->assertSee('Get it free ↗');
    }

    public function test_the_icon_comes_from_the_first_known_category_word(): void
    {
        $this->assertSame('sparkles', $this->steal(['category' => 'AI / Developer Tools'])->icon());
        $this->assertSame('columns', $this->steal(['category' => 'Gutenberg / Blocks'])->icon());
        $this->assertSame('zap', $this->steal(['category' => 'Performance / Utilities'])->icon());
        $this->assertSame('sparkles', $this->steal(['category' => 'Something new'])->icon());
    }

    public function test_install_adds_the_catalogue_once(): void
    {
        $this->assertSame(16, FreeSteals::install());
        $this->assertSame(0, FreeSteals::install());

        $this->assertSame(12, FreeSteal::where('is_active', true)->count());
        $this->assertSame(4, FreeSteal::where('is_featured', true)->count());
        $this->assertSame(
            ['WordPress Skills', 'The Off Switch', 'Thank You Page for WooCommerce', 'Blocks Export Import'],
            FreeSteal::shown()->take(4)->pluck('name')->all()
        );
    }

    public function test_install_leaves_an_edited_steal_alone(): void
    {
        FreeSteals::install();
        FreeSteal::where('name', 'GoDAM')->update(['description' => 'Edited by the admin.']);
        FreeSteal::where('name', 'WP Super Cache')->delete();

        $this->assertSame(1, FreeSteals::install());
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
}
