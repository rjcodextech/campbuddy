<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventManager;
use App\Models\EventManagerChange;
use App\Models\User;
use App\Support\EventData;
use App\Support\ScheduleDelay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Running late": a delay moves the right sessions (whole event or one
 * track, from a time to the end of that day), every page and the reminders
 * use the moved times, and nothing changes without one.
 */
class ScheduleDelayTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        // 3 Oct 2026, 10:00 at the venue (Asia/Kolkata, UTC+5:30).
        $this->travelTo(CarbonImmutable::parse('2026-10-03 04:30:00', 'UTC'));
        $this->event = Event::create([
            'slug' => 'wc-late', 'display_name' => 'WordCamp Late', 'source_site_url' => 'https://late.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true, 'timezone' => 'Asia/Kolkata',
            'starts_on' => '2026-10-03', 'ends_on' => '2026-10-04',
        ]);
        EventData::put($this->event->id, 'sessions', [
            ['id' => 1, 'title' => 'Early', 'starts_at' => '2026-10-03T04:00:00+00:00', 'track_names' => ['Track 1']],
            ['id' => 2, 'title' => 'Track 1 later', 'starts_at' => '2026-10-03T06:00:00+00:00', 'track_names' => ['Track 1']],
            ['id' => 3, 'title' => 'Track 2 later', 'starts_at' => '2026-10-03T06:00:00+00:00', 'track_names' => ['Track 2']],
            ['id' => 4, 'title' => 'Next day', 'starts_at' => '2026-10-04T04:00:00+00:00', 'track_names' => ['Track 1']],
            ['id' => 5, 'title' => 'No time', 'starts_at' => null, 'track_names' => []],
        ]);
    }

    private function moved(): array
    {
        return collect(ScheduleDelay::apply($this->event->fresh(), EventData::get($this->event->id, 'sessions')))
            ->mapWithKeys(fn ($s) => [$s['id'] => $s['delay_minutes'] ?? 0])->all();
    }

    public function test_no_delay_changes_nothing(): void
    {
        $sessions = EventData::get($this->event->id, 'sessions');
        $this->assertSame($sessions, ScheduleDelay::apply($this->event, $sessions));
    }

    public function test_a_track_delay_moves_that_tracks_later_sessions_that_day_only(): void
    {
        ScheduleDelay::set($this->event, 'Track 1', 20, CarbonImmutable::parse('2026-10-03 10:00', 'Asia/Kolkata'), 'Keynote ran over', 'admin: A');

        $this->assertSame([1 => 0, 2 => 20, 3 => 0, 4 => 0, 5 => 0], $this->moved());

        $two = collect(ScheduleDelay::apply($this->event->fresh(), EventData::get($this->event->id, 'sessions')))->firstWhere('id', 2);
        $this->assertSame('2026-10-03T06:20:00+00:00', $two['starts_at']);
        $this->assertSame('2026-10-03T06:00:00+00:00', $two['original_starts_at']);
    }

    public function test_the_whole_event_and_a_track_take_the_larger_and_zero_clears(): void
    {
        $from = CarbonImmutable::parse('2026-10-03 10:00', 'Asia/Kolkata');
        ScheduleDelay::set($this->event, null, 10, $from, null, 'admin: A');
        ScheduleDelay::set($this->event->fresh(), 'Track 2', 30, $from, null, 'admin: A');
        $this->assertSame([1 => 0, 2 => 10, 3 => 30, 4 => 0, 5 => 0], $this->moved());

        ScheduleDelay::set($this->event->fresh(), 'Track 2', 0, $from, null, 'admin: A');
        $this->assertSame([1 => 0, 2 => 10, 3 => 10, 4 => 0, 5 => 0], $this->moved());
    }

    public function test_a_delay_ends_with_its_day(): void
    {
        ScheduleDelay::set($this->event, null, 15, CarbonImmutable::parse('2026-10-03 10:00', 'Asia/Kolkata'), null, 'admin: A');
        $this->assertCount(1, ScheduleDelay::active($this->event->fresh()));

        $this->travelTo(CarbonImmutable::parse('2026-10-04 09:00', 'Asia/Kolkata'));
        $this->assertSame([], ScheduleDelay::active($this->event->fresh()));
    }

    public function test_pages_show_the_moved_times_and_the_banner(): void
    {
        ScheduleDelay::set($this->event, 'Track 1', 20, CarbonImmutable::parse('2026-10-03 10:00', 'Asia/Kolkata'), 'Keynote ran over', 'admin: A');

        $page = $this->get('/event/wc-late/my-day')->assertOk()->assertSee('Running late')->assertSee('Keynote ran over');
        $data = json_decode(Str::between($page->getContent(), 'id="my-day-data">', '</script>'), true);
        $this->assertSame(20, collect($data['sessions'])->firstWhere('id', 2)['delay_minutes']);

        $this->get('/event/wc-late')->assertOk()->assertSee('20 min late');
    }

    public function test_admin_and_manager_set_it_and_the_manager_is_logged(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.events.delay', $this->event))->assertOk()->assertSee('Set a delay')->assertSee('Track 2');
        $this->post(route('admin.events.delay.store', $this->event), ['minutes' => 25, 'track' => '', 'from' => '2026-10-03T09:00', 'note' => ''])->assertRedirect();
        $this->assertSame(25, ScheduleDelay::active($this->event->fresh())[0]['minutes']);
        $this->post(route('admin.events.delay.store', $this->event), ['minutes' => 25, 'track' => 'Track 9', 'from' => '2026-10-03T09:00'])->assertSessionHasErrors('track');
        $this->post(route('admin.events.delay.clear', $this->event))->assertRedirect();
        $this->assertSame([], ScheduleDelay::active($this->event->fresh()));

        Auth::forgetGuards();
        $manager = EventManager::factory()->create(['is_active' => true]);
        $manager->events()->attach($this->event->id);
        Auth::guard('manager')->setUser($manager);
        $this->post(route('manager.events.delay.store', $this->event->id), ['minutes' => 15, 'track' => 'Track 1', 'from' => '2026-10-03T10:30'])->assertRedirect();
        $this->assertStringContainsString('15 min late · Track 1', EventManagerChange::latest('id')->first()->summary);
    }
}
