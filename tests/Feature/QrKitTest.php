<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventManager;
use App\Models\User;
use App\Support\QrKit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/** The organizer QR kit: one tagged link per place, on the admin's and a manager's own page. */
class QrKitTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $slug): Event
    {
        return Event::create(['slug' => $slug, 'display_name' => $slug, 'source_site_url' => "https://{$slug}.wordcamp.org/2026", 'status' => 'active', 'is_visible' => true]);
    }

    public function test_every_code_opens_the_event_tagged_with_where_it_was_scanned(): void
    {
        $event = $this->event('wordcamp-delhi-2026');
        $codes = collect(QrKit::codes($event))->keyBy('key');

        $this->assertSame(['badge', 'standee', 'slide', 'social'], $codes->keys()->all());
        $this->assertSame(
            route('event.home', $event).'?utm_source=id-card&utm_medium=print&utm_campaign=wordcamp-delhi-2026',
            $codes['badge']['url'],
            'the badge keeps the source name GA already reports (id-card / print)'
        );

        $sources = $codes->map(fn ($c) => parse_url($c['url'], PHP_URL_QUERY))->unique();
        $this->assertCount(4, $sources, 'each place has its own tag');
    }

    public function test_the_admin_page_shows_the_four_codes(): void
    {
        $event = $this->event('wc-jaipur');
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.events.qr', $event))
            ->assertOk()
            ->assertSee('Badge / ID card')
            ->assertSee('utm_source=standee', false)
            ->assertSee('data-qr-kit', false);
    }

    public function test_a_manager_sees_the_kit_for_their_own_event_only(): void
    {
        $mine = $this->event('wc-mine');
        $other = $this->event('wc-other');
        $manager = EventManager::factory()->create(['is_active' => true]);
        $manager->events()->attach($mine->id);
        Auth::guard('manager')->setUser($manager);

        $this->get(route('manager.events.qr', $mine->id))->assertOk()->assertSee('Slide on screen');
        $this->get(route('manager.events.qr', $other->id))->assertNotFound();
    }
}
