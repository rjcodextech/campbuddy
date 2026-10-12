<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * The dashboard shows only what needs a look today: the next live
 * WordCamps with a countdown, and drafts waiting for approval.
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Bus::fake();
        $this->travelTo(now()->setDate(2026, 10, 12)->startOfDay()->addHours(10));
    }

    private function event(string $slug, string $status, ?string $starts, ?string $ends = null): Event
    {
        return Event::create([
            'slug' => $slug,
            'display_name' => 'WordCamp '.ucfirst($slug),
            'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => $status,
            'is_visible' => true,
            'starts_on' => $starts,
            'ends_on' => $ends,
        ]);
    }

    public function test_the_next_live_wordcamp_leads_with_a_countdown(): void
    {
        $this->event('soon', 'active', '2026-10-24', '2026-10-25');
        $this->event('later', 'approved', '2026-11-20');
        $this->event('over', 'active', '2026-09-01', '2026-09-02');
        $this->event('hidden', 'draft', '2026-10-15');

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Next WordCamp', 'WordCamp Soon', '12', 'days to go', 'WordCamp Later'])
            ->assertDontSee('WordCamp Over')
            ->assertSee('Upcoming WordCamps');
    }

    public function test_an_event_on_today_reads_as_live(): void
    {
        $this->event('today', 'active', '2026-10-11', '2026-10-12');

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['WordCamp Today', 'Live', 'now']);
    }

    public function test_drafts_are_counted_and_listed_for_approval(): void
    {
        $this->event('draft-one', 'draft', '2026-12-01');

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Needs approval')
            ->assertSee('WordCamp Draft-one')
            ->assertSee('No upcoming WordCamps');
    }

    public function test_nothing_waiting_says_so(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Nothing waiting.');
    }
}
