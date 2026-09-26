<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Offer;
use App\Models\Quest;
use App\Models\User;
use App\Support\DataVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Open apps reload when an event's data version changes. It is built from
 * "last updated" times, which cannot see a removal (taking one of ten away
 * leaves the newest time as it was), so it also counts.
 */
class DataVersionRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    private function event(): Event
    {
        return Event::create([
            'slug' => 'wc-version', 'display_name' => 'WordCamp Version', 'source_site_url' => 'https://wc-version.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true,
        ]);
    }

    private function version(Event $event): string
    {
        return DataVersion::for($event->fresh());
    }

    public function test_removing_a_checklist_item_changes_the_version(): void
    {
        $event = $this->event();
        $before = $this->version($event);

        // Not the newest one: the newest "updated at" stays exactly as it was.
        $event->quests()->orderBy('sort_order')->firstOrFail()->delete();
        DataVersion::forget($event->id);

        $this->assertNotSame($before, $this->version($event));
    }

    public function test_removing_a_deal_changes_the_version(): void
    {
        $event = $this->event();
        $first = Offer::create(['event_id' => $event->id, 'title' => 'First', 'description' => 'x', 'url' => 'https://a.example', 'is_active' => true]);
        Offer::create(['event_id' => $event->id, 'title' => 'Second', 'description' => 'x', 'url' => 'https://b.example', 'is_active' => true]);
        DataVersion::forget($event->id);
        $before = $this->version($event);

        $first->delete();
        DataVersion::forget($event->id);

        $this->assertNotSame($before, $this->version($event));
    }

    public function test_adding_and_editing_still_change_it_and_nothing_else_does(): void
    {
        $event = $this->event();
        $before = $this->version($event);

        $this->assertSame($before, $this->version($event), 'Asked twice, the same answer.');

        $this->travel(30)->seconds();
        $quest = Quest::create(['event_id' => $event->id, 'source' => 'event', 'title' => 'New', 'sort_order' => 999]);
        DataVersion::forget($event->id);
        $afterAdd = $this->version($event);
        $this->assertNotSame($before, $afterAdd);

        $this->travel(30)->seconds();
        $quest->update(['title' => 'Renamed']);
        DataVersion::forget($event->id);
        $this->assertNotSame($afterAdd, $this->version($event));
    }

    public function test_an_admin_removal_reaches_open_apps_when_the_two_minute_cache_ends(): void
    {
        $this->actingAs(User::factory()->create());
        $event = $this->event();
        $before = $this->version($event);
        $quest = $event->quests()->orderBy('sort_order')->firstOrFail();

        // The admin's own page removes it (it does not clear the version itself)…
        $this->delete(route('admin.events.quests.destroy', [$event, $quest]))->assertRedirect();

        // …so it is picked up by the version's short cache running out, and now it does change.
        $this->travel(3)->minutes();
        $this->assertNotSame($before, $this->version($event));
    }
}
