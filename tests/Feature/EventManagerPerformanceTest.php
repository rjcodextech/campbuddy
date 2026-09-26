<?php

namespace Tests\Feature;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\EventManagerChange;
use App\Models\FetchLog;
use App\Models\Offer;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The admin pages and the manager area must cost the same number of queries
 * whether there are three events or three hundred (no N+1), and stay quick with
 * a realistic pile of data. Counting queries is exact and can't flake; the few
 * timing checks use generous limits and only catch a page that has gone wrong
 * by an order of magnitude.
 */
class EventManagerPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private int $serial = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake();
    }

    /** @return array<int, Event> */
    private function events(int $count, array $overrides = []): array
    {
        $events = [];

        Event::withoutEvents(function () use ($count, $overrides, &$events) {
            for ($i = 0; $i < $count; $i++) {
                $n = ++$this->serial;
                $events[] = Event::create($overrides + [
                    'slug' => "wc-perf-{$n}",
                    'display_name' => "WordCamp Perf {$n}",
                    'source_site_url' => "https://perf{$n}.wordcamp.org/2026",
                    'status' => ['active', 'draft', 'approved', 'archived'][$n % 4],
                    'is_visible' => $n % 5 !== 0,
                    'starts_on' => now()->addDays($n * 3 - 30)->toDateString(),
                    'ends_on' => now()->addDays($n * 3 - 29)->toDateString(),
                ]);
            }
        });

        return $events;
    }

    /** Queries a request costs (the sign-in lookup included, the same in every run). */
    private function queries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function asManager(EventManager $manager): void
    {
        Auth::guard('manager')->setUser($manager);
    }

    /** Asserts a page costs the same with a lot more data behind it, and not much to begin with. */
    private function assertFlat(callable $small, callable $large, int $budget, string $what): void
    {
        $few = $this->queries($small);
        $many = $this->queries($large);

        $this->assertSame($few, $many, "{$what}: {$few} queries with little data, {$many} with a lot — a query per row has crept in.");
        $this->assertLessThanOrEqual($budget, $many, "{$what} costs {$many} queries, more than the {$budget} it should.");
    }

    // ---- Admin pages ------------------------------------------------------

    public function test_the_events_list_costs_the_same_however_many_events_there_are(): void
    {
        $this->admin();
        $first = $this->events(5);
        foreach ($first as $event) {
            FetchLog::create(['event_id' => $event->id, 'source' => 'wordcamp', 'job_type' => 'sessions_speakers_sponsors', 'status' => 'ok', 'message' => 'x', 'fetched_at' => now()]);
        }

        foreach (['cards' => [], 'table' => ['view' => 'table']] as $view => $query) {
            $url = route('admin.events.index', $query);
            $few = $this->queries(fn () => $this->get($url)->assertOk());

            $more = $this->events(40);
            foreach ($more as $event) {
                AttendeeRoster::create(['event_id' => $event->id, 'name' => 'Person '.$event->id, 'links' => [], 'content_hash' => 'h'.$event->id, 'is_suppressed' => false]);
                FetchLog::create(['event_id' => $event->id, 'source' => 'wordcamp', 'job_type' => 'sessions_speakers_sponsors', 'status' => 'error', 'message' => 'x', 'fetched_at' => now()]);
            }
            $many = $this->queries(fn () => $this->get($url)->assertOk());

            $this->assertSame($few, $many, "Events list ({$view}): {$few} → {$many} queries as events were added.");
            $this->assertLessThanOrEqual(12, $many, "Events list ({$view}) costs {$many} queries.");
        }
    }

    public function test_the_errors_page_costs_the_same_however_many_problems_there_are(): void
    {
        $this->admin();
        $events = $this->events(3);
        $log = fn (int $n) => FetchLog::create(['event_id' => $events[$n % 3]->id, 'source' => 'wordcamp', 'job_type' => 'sessions_speakers_sponsors', 'status' => 'error', 'message' => "Problem {$n}", 'fetched_at' => now()->subMinutes($n)]);

        $log(1);
        $few = $this->queries(fn () => $this->get(route('admin.errors.index'))->assertOk());

        foreach (range(2, 90) as $n) {
            $log($n);
        }
        $many = $this->queries(fn () => $this->get(route('admin.errors.index'))->assertOk());

        $this->assertSame($few, $many);
        $this->assertLessThanOrEqual(16, $many);
    }

    public function test_the_admin_manager_pages_cost_the_same_however_many_managers_and_events_there_are(): void
    {
        $this->admin();
        $events = $this->events(3);
        $one = EventManager::factory()->create();
        $one->events()->attach(collect($events)->map->id->all());
        EventManagerChange::create(['event_manager_id' => $one->id, 'manager_name' => $one->name, 'event_id' => $events[0]->id, 'section' => 'quests', 'action' => 'added', 'summary' => 'The first change']);

        $index = fn () => $this->get(route('admin.event-managers.index'))->assertOk();
        $create = fn () => $this->get(route('admin.event-managers.create'))->assertOk();
        $edit = fn () => $this->get(route('admin.event-managers.edit', $one))->assertOk();
        $activity = fn () => $this->get(route('admin.event-managers.activity'))->assertOk();

        $before = [$this->queries($index), $this->queries($create), $this->queries($edit), $this->queries($activity)];

        $more = $this->events(57);
        foreach (range(1, 27) as $i) {
            EventManager::factory()->create()->events()->attach(array_map(fn ($e) => $e->id, array_slice($more, $i, 3)));
        }
        foreach (range(1, 80) as $i) {
            EventManagerChange::create(['event_manager_id' => $one->id, 'manager_name' => $one->name, 'event_id' => $more[$i % 50]->id, 'section' => 'quests', 'action' => 'added', 'summary' => "Change {$i}"]);
        }

        $after = [$this->queries($index), $this->queries($create), $this->queries($edit), $this->queries($activity)];

        $this->assertSame($before, $after, 'A page grew a query per manager, event or change.');
        $this->assertLessThanOrEqual(10, max($after));
    }

    public function test_every_event_page_costs_the_same_however_much_the_event_holds(): void
    {
        $this->admin();
        $event = $this->events(1, ['status' => 'active', 'is_visible' => true])[0];
        $pages = [
            route('admin.events.edit', $event), route('admin.events.quests.index', $event), route('admin.events.offers.index', $event),
            route('admin.events.roster.index', $event), route('admin.events.deal-leads.index', $event),
        ];

        // A little of everything to begin with (a list with nothing in it skips its rows query, which is not what is being counted).
        $first = Offer::create(['event_id' => $event->id, 'title' => 'First deal', 'description' => 'x', 'url' => 'https://a.example', 'icon' => '🏷', 'is_active' => true]);
        AttendeeRoster::create(['event_id' => $event->id, 'name' => 'First person', 'links' => [], 'content_hash' => 'first', 'is_suppressed' => false]);
        \App\Models\OfferLead::create(['event_id' => $event->id, 'offer_id' => $first->id, 'name' => 'First lead', 'email' => 'first@example.com']);

        $before = array_map(fn ($url) => $this->queries(fn () => $this->get($url)->assertOk()), $pages);

        Quest::withoutEvents(fn () => collect(range(1, 150))->each(fn ($i) => Quest::create(['event_id' => $event->id, 'source' => 'event', 'title' => "Extra {$i}", 'sort_order' => $i])));
        collect(range(1, 40))->each(fn ($i) => Offer::create(['event_id' => $event->id, 'title' => "Deal {$i}", 'description' => 'x', 'url' => 'https://a.example', 'icon' => '🏷', 'is_active' => true]));
        DB::table('attendee_roster')->insert(collect(range(1, 300))->map(fn ($i) => ['event_id' => $event->id, 'name' => "Person {$i}", 'links' => '[]', 'content_hash' => "h{$i}", 'is_suppressed' => 0, 'created_at' => now(), 'updated_at' => now()])->all());

        DB::table('offer_leads')->insert(collect(range(1, 120))->map(fn ($i) => ['event_id' => $event->id, 'offer_id' => $first->id, 'name' => "Lead {$i}", 'email' => "lead{$i}@example.com", 'created_at' => now(), 'updated_at' => now()])->all());

        $after = array_map(fn ($url) => $this->queries(fn () => $this->get($url)->assertOk()), $pages);

        $this->assertSame($before, $after, 'An event page costs more queries the more the event holds.');
        $this->assertLessThanOrEqual(14, max($after));
    }

    // ---- The manager's area ------------------------------------------------

    public function test_my_events_costs_the_same_however_many_events_the_manager_has(): void
    {
        $few = EventManager::factory()->create();
        $few->events()->attach(array_map(fn ($e) => $e->id, $this->events(3)));
        $many = EventManager::factory()->create();
        $many->events()->attach(array_map(fn ($e) => $e->id, $this->events(40)));

        $this->asManager($few);
        $small = $this->queries(fn () => $this->get(route('manager.dashboard'))->assertOk());
        $this->app['auth']->forgetGuards();
        $this->asManager($many);
        $large = $this->queries(fn () => $this->get(route('manager.dashboard'))->assertOk());

        $this->assertSame($small, $large);
        $this->assertLessThanOrEqual(4, $large);
    }

    public function test_the_manager_pages_cost_a_handful_of_queries_whatever_the_event_holds(): void
    {
        $event = $this->events(1, ['status' => 'active', 'is_visible' => true])[0];
        $manager = EventManager::factory()->create();
        $manager->events()->attach([$event->id, $this->events(1)[0]->id]);
        $this->asManager($manager);

        $pages = [route('manager.events.details', $event), route('manager.events.information', $event), route('manager.events.quests', $event)];
        $before = array_map(fn ($url) => $this->queries(fn () => $this->get($url)->assertOk()), $pages);

        Quest::withoutEvents(fn () => collect(range(1, 200))->each(fn ($i) => Quest::create(['event_id' => $event->id, 'source' => 'event', 'title' => "Extra {$i}", 'sort_order' => $i])));
        $event->update(['info' => array_fill_keys(['venue', 'wifi', 'registration_info'], str_repeat('x', 400))]);

        $after = array_map(fn ($url) => $this->queries(fn () => $this->get($url)->assertOk()), $pages);

        $this->assertSame($before, $after);
        $this->assertLessThanOrEqual(5, max($after));
    }

    public function test_saving_costs_a_handful_of_queries(): void
    {
        $event = $this->events(1, ['status' => 'active', 'is_visible' => true, 'display_name' => 'Before'])[0];
        $manager = EventManager::factory()->create();
        $manager->events()->attach([$event->id, $this->events(1)[0]->id]);
        $this->asManager($manager);

        $details = ['display_name' => 'After', 'slug' => $event->slug, 'source_site_url' => $event->source_site_url, 'is_visible' => '1'];

        $saveDetails = $this->queries(fn () => $this->put(route('manager.events.details.update', $event), $details)->assertSessionHasNoErrors());
        $saveInfo = $this->queries(fn () => $this->put(route('manager.events.information.update', $event), ['venue' => 'Hall'])->assertSessionHasNoErrors());
        $addQuest = $this->queries(fn () => $this->post(route('manager.events.quests.store', $event), ['title' => 'New'])->assertSessionHasNoErrors());

        $this->assertLessThanOrEqual(16, $saveDetails, 'Saving the details costs too many queries.');
        $this->assertLessThanOrEqual(12, $saveInfo);
        $this->assertLessThanOrEqual(12, $addQuest);
    }

    public function test_signing_in_costs_a_handful_of_queries(): void
    {
        $manager = EventManager::factory()->create(['password' => 'a-strong-password']);

        $cost = $this->queries(fn () => $this->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'a-strong-password'])->assertRedirect());

        $this->assertLessThanOrEqual(6, $cost);
    }

    // ---- With a realistic pile of data ------------------------------------

    public function test_the_admin_lists_stay_quick_with_hundreds_of_events_and_thousands_of_log_rows(): void
    {
        $this->admin();
        $events = $this->events(300);

        $rows = [];
        foreach (range(1, 6000) as $i) {
            $rows[] = [
                'event_id' => $events[$i % 300]->id, 'source' => 'wordcamp', 'job_type' => ['sessions_speakers_sponsors', 'roster', 'event_info'][$i % 3],
                'status' => ['ok', 'error', 'partial'][$i % 3], 'message' => 'Message '.($i % 40), 'fetched_at' => now()->subMinutes($i)->toDateTimeString(),
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('fetch_log')->insert($chunk);
        }

        foreach ([
            'events (cards)' => route('admin.events.index'),
            'events (table)' => route('admin.events.index', ['view' => 'table']),
            'events (filtered)' => route('admin.events.index', ['status' => 'active', 'when' => 'upcoming', 'q' => 'perf']),
            'errors' => route('admin.errors.index'),
            'errors (30 days)' => route('admin.errors.index', ['period' => 30]),
            'dashboard' => route('dashboard'),
        ] as $label => $url) {
            $started = microtime(true);
            $this->get($url)->assertOk();
            $took = microtime(true) - $started;

            $this->assertLessThan(3.0, $took, "{$label} took ".round($took, 2).' s with 300 events and 6,000 log rows.');
        }
    }

    public function test_the_manager_pages_stay_quick_for_a_manager_with_hundreds_of_events(): void
    {
        $manager = EventManager::factory()->create();
        $events = $this->events(300);
        $manager->events()->attach(array_map(fn ($e) => $e->id, $events));
        $this->asManager($manager);

        $started = microtime(true);
        $this->get(route('manager.dashboard'))->assertOk()->assertSee('WordCamp Perf');
        $this->assertLessThan(3.0, microtime(true) - $started);

        $started = microtime(true);
        $this->get(route('manager.events.quests', $events[7]))->assertOk();
        $this->assertLessThan(3.0, microtime(true) - $started);
    }

    public function test_the_activity_log_keeps_itself_short(): void
    {
        $event = $this->events(1)[0];
        $manager = EventManager::factory()->create();

        DB::table('event_manager_changes')->insert(array_map(fn ($i) => [
            'event_manager_id' => $manager->id, 'manager_name' => 'Old', 'event_id' => $event->id, 'section' => 'quests', 'action' => 'added',
            'summary' => "Old {$i}", 'created_at' => now()->subDays(120)->toDateTimeString(),
        ], range(1, 20)));

        // Housekeeping runs on one write in fifty; a couple of hundred writes are enough to see it happen.
        foreach (range(1, 400) as $i) {
            \App\Support\ManagerActivity::record($manager, $event, 'quests', 'added', "New {$i}");
        }

        $this->assertSame(0, EventManagerChange::where('summary', 'like', 'Old %')->count(), 'Entries older than 90 days are removed as new ones arrive.');
        $this->assertSame(400, EventManagerChange::where('summary', 'like', 'New %')->count());
    }

    // ---- The rest of the app pays nothing for any of it -------------------

    public function test_the_attendee_pages_cost_no_more_and_use_no_session(): void
    {
        $event = $this->events(1, ['status' => 'active', 'is_visible' => true])[0];

        foreach ([route('event.home', $event), route('event.explore', $event), route('event.quest', $event)] as $url) {
            $this->get($url)->assertOk(); // warm what a first visit computes
            $cost = $this->queries(fn () => $this->get($url)->assertOk());

            $this->assertLessThanOrEqual(8, $cost, "{$url} costs {$cost} queries.");
            $this->assertSame([], $this->get($url)->headers->getCookies());
        }
    }
}
