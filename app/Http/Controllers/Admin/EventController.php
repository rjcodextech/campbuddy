<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Requests\UploadEventBrandingRequest;
use App\Jobs\DiscoverWordCampsJob;
use App\Jobs\FetchBrandingAssetsJob;
use App\Jobs\FetchEventInfoJob;
use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Models\Event;
use App\Models\FetchLog;
use App\Support\EventData;
use App\Support\EventListing;
use App\Support\EventTime;
use App\Support\SvgGuard;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Event::class);

        // By event day — the one that starts soonest first — narrowed by the
        // filters in the query string (EventListing).
        $filters = EventListing::filters($request);
        $today = EventListing::today();

        // Cards by default; ?view=table is the compact table. Same filters and order in both.
        $view = $request->query('view') === 'table' ? 'table' : 'cards';

        $events = EventListing::ordered(
            EventListing::filtered(Event::withCount('attendeeRoster'), $filters, $today),
            $today
        )->paginate($view === 'table' ? 20 : 24)->withQueryString();

        // The nearest upcoming events stand out on every page and under any filter.
        $highlighted = EventListing::highlighted($today);
        $highlightedIds = $highlighted->pluck('id')->all();
        $nextUpId = $highlighted->first(fn (Event $e) => $e->starts_on->toDateString() > $today)?->id;

        // Each event's latest schedule fetch, for the "Data" column — one query.
        $lastFetches = FetchLog::whereIn('id', FetchLog::selectRaw('max(id)')
            ->whereIn('event_id', $events->pluck('id'))
            ->where('job_type', 'sessions_speakers_sponsors')
            ->groupBy('event_id'))
            ->get()
            ->keyBy('event_id');

        return view('admin.events.index', compact('events', 'lastFetches', 'filters', 'today', 'highlightedIds', 'nextUpId', 'view'));
    }

    /**
     * Manual "Discover WordCamps" — queued so a large seed-list
     * scan doesn't block the request; new finds land as drafts.
     */
    public function discover(): RedirectResponse
    {
        Gate::authorize('create', Event::class);

        try {
            DiscoverWordCampsJob::dispatch();
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.events.index')
                ->with('error', 'Discovery couldn\'t be queued — the job queue isn\'t reachable. Check the queue connection, then try again.');
        }

        return redirect()
            ->route('admin.events.index')
            ->with('status', 'Discovery queued — new WordCamps will appear here as drafts shortly.');
    }

    public function create(): View
    {
        Gate::authorize('create', Event::class);

        return view('admin.events.create', ['event' => new Event]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $event = Event::create($this->withTimezone($request->validated(), null));

        return redirect()
            ->route('admin.events.edit', $event)
            ->with('status', "Event \"{$event->display_name}\" created.");
    }

    public function edit(Event $event): View
    {
        Gate::authorize('update', $event);

        $lastFetch = $event->fetchLogs()->where('job_type', 'sessions_speakers_sponsors')->latest('fetched_at')->first();
        $lastBrandingFetch = $event->fetchLogs()->where('job_type', 'branding')->latest('fetched_at')->first();

        $lastInfoFetch = $event->fetchLogs()->where('job_type', 'event_info')->latest('fetched_at')->latest('id')->first();

        // What attendees see right now: the cached lists (null = never fetched)
        // and the visible attendee list.
        $cachedCount = function (string $key) use ($event): ?int {
            return EventData::count($event->id, $key);
        };

        $dataCounts = [
            'Sessions' => $cachedCount('sessions'),
            'Speakers' => $cachedCount('speakers'),
            'Sponsors' => $cachedCount('sponsors'),
            'Organizers' => $cachedCount('organizers'),
            'Attendees' => $event->attendeeRoster()->where('is_suppressed', false)->count(),
        ];

        $recentFetches = $event->fetchLogs()->latest('fetched_at')->latest('id')->limit(10)->get();

        $jobLabels = [
            'sessions_speakers_sponsors' => 'Schedule',
            'roster' => 'Attendee list',
            'event_info' => 'Event information',
            'branding' => 'Branding',
        ];

        return view('admin.events.edit', compact('event', 'lastFetch', 'lastBrandingFetch', 'lastInfoFetch', 'dataCounts', 'recentFetches', 'jobLabels'));
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $zoneBefore = $event->timezone;
        $event->update($this->withTimezone($request->validated(), $event));

        // Session times depend on the zone: re-read them in the new one.
        if ($event->timezone !== $zoneBefore && $event->status === 'active') {
            try {
                FetchSpeakersSponsorsSessionsJob::dispatch($event);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return redirect()
            ->route('admin.events.edit', $event)
            ->with('status', "Event \"{$event->display_name}\" updated.");
    }

    /**
     * A time zone typed by an admin is theirs to keep (timezone_locked): the
     * WordCamp site's own setting never overwrites it. Left blank, the zone
     * is read from the site again on the next fetch.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withTimezone(array $data, ?Event $event): array
    {
        if (! array_key_exists('timezone', $data)) {
            return $data;
        }

        $zone = EventTime::normalize($data['timezone']);

        if ($zone === null) {
            // Unlocking keeps the last known zone until the next fetch reads the site's.
            $data['timezone'] = $event?->timezone_locked ? null : $event?->timezone;
            $data['timezone_locked'] = false;
        } else {
            $data['timezone'] = $zone;
            $data['timezone_locked'] = true;
        }

        return $data;
    }

    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('delete', $event);

        $event->delete();

        return redirect()
            ->route('admin.events.index')
            ->with('status', "Event \"{$event->display_name}\" deleted.");
    }

    /**
     * Manual "Refresh now" — runs the sessions/speakers/sponsors REST
     * ingestion for this event right now, so the admin sees what it found on
     * the redirect instead of "queued" and hoping a worker picks it up.
     */
    public function refresh(Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);

        return $this->runNow(
            $event,
            fn () => FetchSpeakersSponsorsSessionsJob::dispatchSync($event),
            'sessions_speakers_sponsors',
            'Refreshed',
            'Couldn\'t refresh sessions, speakers and sponsors'
        );
    }

    /**
     * Event Information — every field optional, simply
     * omitted from the attendee-facing page when blank.
     */
    public function updateInfo(Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);

        $fields = request()->validate([
            'venue' => ['nullable', 'string', 'max:255'],
            'important_links' => ['nullable', 'string', 'max:2000'],
            'wifi' => ['nullable', 'string', 'max:500'],
            'social_event_info' => ['nullable', 'string', 'max:1000'],
            'registration_info' => ['nullable', 'string', 'max:1000'],
            'contributor_day_location' => ['nullable', 'string', 'max:255'],
            'code_of_conduct_url' => ['nullable', 'url:http,https', 'max:500'],
            'emergency_contact' => ['nullable', 'string', 'max:500'],
            'nearby_venue_info' => ['nullable', 'string', 'max:1000'],
        ]);

        // Blank fields are dropped entirely, not stored as empty strings —
        // EI3 says "simply omitted", so the attendee view only needs to
        // check array_key_exists, not also check for "". Line endings are
        // normalised so an untouched textarea (browsers submit CRLF) still
        // equals what the auto-fetch stored, and isn't mistaken for an edit.
        $event->update(['info' => array_filter(
            array_map(fn ($v) => is_string($v) ? trim(str_replace("\r\n", "\n", $v)) : $v, $fields),
            fn ($v) => filled($v)
        )]);

        return redirect()
            ->route('admin.events.edit', $event)
            ->with('status', 'Event information updated.');
    }

    /**
     * Manual "Fetch latest" for Event Information — runs the fetch right
     * now (a handful of short requests) so the admin sees what it found on
     * the redirect, instead of waiting on a queue worker. Anything an admin
     * typed by hand is kept (see FetchEventInfoJob).
     */
    public function fetchInfo(Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);

        return $this->runNow(
            $event,
            fn () => FetchEventInfoJob::dispatchSync($event),
            'event_info',
            'Event information updated',
            'Couldn\'t fetch event information'
        );
    }

    /**
     * Manual "Re-fetch branding assets" — separate from the data
     * refresh above, since branding is a one-time/on-demand job, not part
     * of the daily ingestion cadence.
     */
    public function refreshBranding(Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);

        return $this->runNow(
            $event,
            fn () => FetchBrandingAssetsJob::dispatchSync($event),
            'branding',
            'Branding re-fetched',
            'Couldn\'t re-fetch branding'
        );
    }

    /**
     * Admin upload/replace of logo or favicon — always
     * overrides whatever auto-fetch found, or fills the gap if it found
     * nothing.
     */
    public function uploadBranding(UploadEventBrandingRequest $request, Event $event): RedirectResponse
    {
        foreach (['logo', 'favicon'] as $field) {
            if (! $request->hasFile($field)) {
                continue;
            }

            $file = $request->file($field);
            $extension = strtolower($file->extension());
            $contents = (string) file_get_contents($file->getRealPath());

            if ($extension === 'svg' && ! SvgGuard::isSafe($contents)) {
                return back()->withErrors([
                    $field => 'That SVG contains scripts or embedded content, which CampBuddy won\'t host. Export it as a plain SVG, or use a PNG.',
                ]);
            }

            $event->storeBranding($field, $extension, $contents);
        }

        return redirect()
            ->route('admin.events.edit', $event)
            ->with('status', 'Branding updated.');
    }

    /**
     * Manual buttons (Refresh now, Re-fetch branding) run the job right now,
     * in the request — a handful of short HTTP calls — so the redirect can say
     * what happened, and so they work whether or not a queue worker is running.
     * The job records its own outcome in the fetch log; a failure is shown,
     * never thrown at the admin as an error page.
     */
    private function runNow(Event $event, Closure $run, string $jobType, string $success, string $failure): RedirectResponse
    {
        @set_time_limit(120);

        $startedAt = now()->subSecond();

        try {
            $run();
        } catch (Throwable) {
            // Already written to the fetch log (and the app log) by the job itself.
        }

        $log = $event->fetchLogs()
            ->where('job_type', $jobType)
            ->where('fetched_at', '>=', $startedAt)
            ->latest('fetched_at')
            ->latest('id')
            ->first();

        [$level, $message] = match ($log?->status) {
            'ok' => ['status', "{$success} — {$log->message}"],
            'partial' => ['warning', "{$success}, with problems — {$log->message}"],
            null => ['error', "{$failure} — nothing was recorded. Check the application log (storage/logs) for details."],
            default => ['error', "{$failure} — {$log->message}"],
        };

        return redirect()->route('admin.events.edit', $event)->with($level, $message);
    }
}
