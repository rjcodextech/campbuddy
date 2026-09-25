<?php

namespace Tests\Feature;

use App\Jobs\ParseAttendeeRosterJob;
use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Support\DataVersion;
use App\Support\EventData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Re-fetching merges into what's stored — added, updated, removed item by
 * item — and never clears first; a sudden big drop waits one run for
 * confirmation. And open apps learn about changes through the data version.
 */
class SafeSyncTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::withoutEvents(fn () => Event::create([
            'slug' => 'wc-test',
            'display_name' => 'WordCamp Test 2026',
            'source_site_url' => 'https://test.wordcamp.org/2026',
            'status' => 'active',
            'is_visible' => true,
        ]));
    }

    private function items(array $ids, string $suffix = ''): array
    {
        return array_map(fn ($id) => ['id' => $id, 'title' => "Talk {$id}{$suffix}"], $ids);
    }

    public function test_adds_updates_and_removes_item_by_item(): void
    {
        $event = $this->event();
        EventData::put($event->id, 'sessions', $this->items([1, 2, 3]));

        $result = EventData::sync($event->id, 'sessions', [
            ['id' => 1, 'title' => 'Talk 1'],
            ['id' => 2, 'title' => 'Talk 2 (moved to Room B)'],
            ['id' => 4, 'title' => 'Talk 4'],
        ]);

        $this->assertSame(['added' => 1, 'updated' => 1, 'removed' => 1, 'held' => 0, 'total' => 3], $result);
        $this->assertSame([1, 2, 4], array_column(EventData::get($event->id, 'sessions'), 'id'));
        $this->assertSame('Talk 2 (moved to Room B)', EventData::get($event->id, 'sessions')[1]['title']);
    }

    public function test_an_empty_fetch_never_clears_the_list(): void
    {
        $event = $this->event();
        EventData::put($event->id, 'sponsors', $this->items([1, 2]));

        foreach ([1, 2, 3] as $run) {
            $result = EventData::sync($event->id, 'sponsors', []);
            $this->assertSame(2, $result['held']);
        }

        $this->assertCount(2, EventData::get($event->id, 'sponsors'));
    }

    public function test_a_big_drop_is_held_once_then_applied_if_confirmed(): void
    {
        $event = $this->event();
        EventData::put($event->id, 'sessions', $this->items(range(1, 10)));

        // The site suddenly lists only 3 of 10: probably half-loaded.
        $first = EventData::sync($event->id, 'sessions', $this->items([1, 2, 3], ' v2'));
        $this->assertSame(7, $first['held']);
        $this->assertSame(0, $first['removed']);
        $this->assertCount(10, EventData::get($event->id, 'sessions'));
        $this->assertSame('Talk 1 v2', EventData::get($event->id, 'sessions')[0]['title'], 'what did come back is still updated');

        // Still only 3 next time: now it's real.
        $second = EventData::sync($event->id, 'sessions', $this->items([1, 2, 3], ' v2'));
        $this->assertSame(7, $second['removed']);
        $this->assertCount(3, EventData::get($event->id, 'sessions'));
    }

    public function test_a_big_drop_that_recovers_loses_nothing(): void
    {
        $event = $this->event();
        EventData::put($event->id, 'sessions', $this->items(range(1, 10)));

        EventData::sync($event->id, 'sessions', $this->items([1]));
        EventData::sync($event->id, 'sessions', $this->items(range(1, 10)));

        $this->assertCount(10, EventData::get($event->id, 'sessions'));
    }

    public function test_a_half_loaded_attendees_page_does_not_empty_the_roster(): void
    {
        $event = $this->event();
        $page = fn (array $names) => '<ul class="tix-attendee-list">'.implode('', array_map(
            fn ($n) => '<li><span class="tix-attendee-name"><span class="tix-first">'.explode(' ', $n)[0].'</span> <span class="tix-last">'.explode(' ', $n)[1].'</span></span></li>', $names
        )).'</ul>';
        $all = array_map(fn ($i) => "Person {$i}", range(1, 10));

        Http::fake(['*' => Http::sequence()
            ->push($page($all))
            ->push($page(['Person 1', 'Person 2']))
            ->push($page(['Person 1', 'Person 2']))]);

        ParseAttendeeRosterJob::dispatchSync($event);
        $this->assertSame(10, AttendeeRoster::where('event_id', $event->id)->count());

        ParseAttendeeRosterJob::dispatchSync($event);
        $this->assertSame(10, AttendeeRoster::where('event_id', $event->id)->count(), 'held for one run');

        ParseAttendeeRosterJob::dispatchSync($event);
        $this->assertSame(2, AttendeeRoster::where('event_id', $event->id)->count(), 'confirmed, so removed');
    }

    public function test_the_data_version_changes_only_when_the_data_does(): void
    {
        $event = $this->event();
        EventData::put($event->id, 'sessions', $this->items([1, 2]));
        $v1 = DataVersion::for($event);

        EventData::sync($event->id, 'sessions', $this->items([1, 2]));
        $this->assertSame($v1, DataVersion::for($event), 'a refresh that found nothing new');

        EventData::sync($event->id, 'sessions', $this->items([1, 2, 3]));
        $v2 = DataVersion::for($event);
        $this->assertNotSame($v1, $v2);

        $this->getJson(route('api.events.data-version', $event))
            ->assertOk()
            ->assertExactJson(['version' => $v2])
            // Revalidated on every ask (an ETag — see ApiCachingTest); a CDN may hold it for seconds only.
            ->assertHeader('ETag', 'W/"'.$v2.'"')
            ->assertHeader('Cache-Control', 'max-age=0, public, s-maxage=20, stale-while-revalidate=40');

        $this->withoutVite()->get(route('event.home', $event))
            ->assertSee('<meta name="campbuddy-data-version" content="'.$v2.'">', false);
    }
}
