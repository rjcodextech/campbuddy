<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Support\EventListing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The admin Events list: ordered by event day (soonest first), filterable,
 * with the nearest upcoming events highlighted.
 */
class AdminEventListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Carbon::setTestNow('2026-09-26 10:00:00');
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function event(string $slug, array $overrides = []): Event
    {
        return Event::create($overrides + [
            'slug' => $slug,
            'display_name' => 'WordCamp '.ucfirst($slug),
            'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'draft',
            'is_visible' => true,
        ]);
    }

    /** @return array<int, string> slugs in the order the list shows them */
    private function listed(string $query = ''): array
    {
        return $this->get(route('admin.events.index').$query)
            ->assertOk()
            ->viewData('events')
            ->pluck('slug')
            ->all();
    }

    public function test_events_are_listed_by_event_day_soonest_first(): void
    {
        $this->event('later', ['starts_on' => '2026-10-03', 'ends_on' => '2026-10-04']);
        $this->event('soonest', ['starts_on' => '2026-10-01']);
        $this->event('ongoing', ['starts_on' => '2026-09-25', 'ends_on' => '2026-09-27']);
        $this->event('recent-past', ['starts_on' => '2026-09-01', 'ends_on' => '2026-09-02']);
        $this->event('old-past', ['starts_on' => '2026-08-01']);
        $this->event('no-date');
        $this->event('today-only', ['starts_on' => '2026-09-26']);

        // Ongoing/upcoming first (earliest start on top), then what's over
        // (most recent first), then events with no date at all.
        $this->assertSame(
            ['ongoing', 'today-only', 'soonest', 'later', 'recent-past', 'old-past', 'no-date'],
            $this->listed()
        );
    }

    public function test_an_event_with_no_end_date_counts_as_over_after_its_start_day(): void
    {
        $this->event('yesterday', ['starts_on' => '2026-09-25']);
        $this->event('tomorrow', ['starts_on' => '2026-09-27']);

        $this->assertSame(['tomorrow', 'yesterday'], $this->listed());
    }

    public function test_the_list_can_be_filtered_by_status_visibility_and_when(): void
    {
        $this->event('a-active', ['status' => 'active', 'starts_on' => '2026-10-01']);
        $this->event('b-draft', ['status' => 'draft', 'starts_on' => '2026-10-02']);
        $this->event('c-hidden', ['status' => 'active', 'is_visible' => false, 'starts_on' => '2026-10-03']);
        $this->event('d-archived', ['status' => 'archived', 'starts_on' => '2026-05-01']);
        $this->event('e-undated');

        $this->assertSame(['a-active', 'c-hidden'], $this->listed('?status=active'));
        $this->assertSame(['b-draft', 'e-undated'], $this->listed('?status=draft'));
        $this->assertSame(['c-hidden'], $this->listed('?visibility=hidden'));
        $this->assertSame(['a-active', 'b-draft', 'd-archived', 'e-undated'], $this->listed('?visibility=visible'));
        $this->assertSame(['a-active', 'b-draft', 'c-hidden'], $this->listed('?when=upcoming'));
        $this->assertSame(['d-archived'], $this->listed('?when=past'));
        $this->assertSame(['e-undated'], $this->listed('?when=undated'));
        // Filters combine.
        $this->assertSame(['a-active'], $this->listed('?status=active&visibility=visible&when=upcoming'));
    }

    public function test_the_list_can_be_searched_by_name_slug_or_short_name(): void
    {
        $this->event('rajasthan', ['display_name' => 'WordCamp Rajasthan 2026', 'short_name' => '#WCRJ']);
        $this->event('sylhet', ['display_name' => 'WordCamp Sylhet 2026']);

        $this->assertSame(['rajasthan'], $this->listed('?q=Rajas'));
        $this->assertSame(['sylhet'], $this->listed('?q=sylhet'));
        $this->assertSame(['rajasthan'], $this->listed('?q=wcrj'));
        $this->assertSame([], $this->listed('?q=nothing-like-this'));
    }

    public function test_an_unknown_filter_value_is_ignored_not_trusted(): void
    {
        $this->event('one', ['starts_on' => '2026-10-01']);
        $this->event('two', ['status' => 'active', 'starts_on' => '2026-10-02']);

        $this->assertSame(['one', 'two'], $this->listed('?status=nonsense&when=whenever&visibility=maybe'));
    }

    public function test_the_filters_survive_paging_and_show_a_reset_link(): void
    {
        foreach (range(1, 25) as $i) {
            $this->event('draft-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), ['starts_on' => '2026-11-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $response = $this->get(route('admin.events.index', ['status' => 'draft']))->assertOk();

        // Page 2's link keeps the filter.
        $response->assertSee('status=draft&amp;page=2', false)->assertSee('Reset');

        $this->assertCount(5, $this->get(route('admin.events.index', ['status' => 'draft', 'page' => 2]))->viewData('events'));
    }

    public function test_the_ten_nearest_upcoming_events_are_highlighted(): void
    {
        foreach (range(1, 12) as $i) {
            $this->event('soon-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), ['starts_on' => '2026-10-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }
        $this->event('over', ['starts_on' => '2026-08-01']);
        $this->event('undated');

        $html = $this->get(route('admin.events.index'))->assertOk()->getContent();

        $this->assertSame(10, substr_count($html, 'class="cb-row-soon"'));
        $this->assertSame(1, substr_count($html, 'Next up'));
        $this->assertStringContainsString('Highlighted: the next 10 by date', $html);
    }

    public function test_an_event_that_is_on_now_is_marked_and_the_next_one_gets_the_next_up_badge(): void
    {
        $this->event('on-now', ['starts_on' => '2026-09-25', 'ends_on' => '2026-09-27']);
        $this->event('next', ['starts_on' => '2026-10-01']);

        $this->get(route('admin.events.index'))
            ->assertSeeInOrder(['Happening now', 'Next up'])
            ->assertSee('In 5 days');
    }

    public function test_the_same_ten_stand_out_under_a_filter(): void
    {
        $this->event('active-one', ['status' => 'active', 'starts_on' => '2026-10-01']);
        $this->event('draft-one', ['status' => 'draft', 'starts_on' => '2026-10-02']);

        $html = $this->get(route('admin.events.index', ['status' => 'draft']))->getContent();

        // Only the draft is listed, and it is still one of the nearest events.
        $this->assertSame(1, substr_count($html, 'class="cb-row-soon"'));
        // "Next up" belongs to the nearest event overall, which the filter hides.
        $this->assertStringNotContainsString('Next up', $html);
    }

    public function test_the_empty_states_say_which_case_it_is(): void
    {
        $this->get(route('admin.events.index'))->assertSee('No events yet');

        $this->event('one');
        $this->get(route('admin.events.index', ['status' => 'archived']))->assertSee('No events match these filters');
    }

    public function test_timing_says_where_an_event_is_in_time(): void
    {
        $at = fn (array $dates) => EventListing::timing(new Event($dates), '2026-09-26');

        $this->assertNull($at([]));
        $this->assertSame(['state' => 'upcoming', 'label' => 'Tomorrow'], $at(['starts_on' => '2026-09-27']));
        $this->assertSame(['state' => 'upcoming', 'label' => 'In 10 days'], $at(['starts_on' => '2026-10-06']));
        $this->assertSame(['state' => 'ongoing', 'label' => 'Happening now'], $at(['starts_on' => '2026-09-26']));
        $this->assertSame(['state' => 'ongoing', 'label' => 'Happening now'], $at(['starts_on' => '2026-09-20', 'ends_on' => '2026-09-26']));
        $this->assertSame(['state' => 'past', 'label' => 'Ended yesterday'], $at(['starts_on' => '2026-09-25']));
        $this->assertSame(['state' => 'past', 'label' => 'Ended 3 days ago'], $at(['starts_on' => '2026-09-20', 'ends_on' => '2026-09-23']));
    }
}
