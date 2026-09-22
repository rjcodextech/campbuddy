<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Requests\UploadEventBrandingRequest;
use App\Jobs\FetchBrandingAssetsJob;
use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Event::class);

        $events = Event::withCount('attendeeRoster')
            ->latest()
            ->paginate(20);

        return view('admin.events.index', compact('events'));
    }

    public function create(): View
    {
        Gate::authorize('create', Event::class);

        return view('admin.events.create', ['event' => new Event]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $event = Event::create($request->validated());

        return redirect()
            ->route('admin.events.edit', $event)
            ->with('status', "Event \"{$event->display_name}\" created.");
    }

    public function edit(Event $event): View
    {
        Gate::authorize('update', $event);

        $lastFetch = $event->fetchLogs()->where('job_type', 'sessions_speakers_sponsors')->latest('fetched_at')->first();
        $lastBrandingFetch = $event->fetchLogs()->where('job_type', 'branding')->latest('fetched_at')->first();

        return view('admin.events.edit', compact('event', 'lastFetch', 'lastBrandingFetch'));
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $event->update($request->validated());

        return redirect()
            ->route('admin.events.edit', $event)
            ->with('status', "Event \"{$event->display_name}\" updated.");
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
     * Manual "Refresh now" (§9 Ingestion status) — dispatches the
     * sessions/speakers/sponsors REST ingestion job for this event.
     */
    public function refresh(Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);

        FetchSpeakersSponsorsSessionsJob::dispatch($event);

        return redirect()
            ->route('admin.events.edit', $event)
            ->with('status', 'Refresh queued — check the ingestion status below shortly.');
    }

    /**
     * Event Information (§3.12 EI2/EI3) — every field optional, simply
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
            'code_of_conduct_url' => ['nullable', 'url', 'max:500'],
            'emergency_contact' => ['nullable', 'string', 'max:500'],
            'nearby_venue_info' => ['nullable', 'string', 'max:1000'],
        ]);

        // Blank fields are dropped entirely, not stored as empty strings —
        // EI3 says "simply omitted", so the attendee view only needs to
        // check array_key_exists, not also check for "".
        $event->update(['info' => array_filter($fields, fn ($v) => filled($v))]);

        return redirect()
            ->route('admin.events.edit', $event)
            ->with('status', 'Event information updated.');
    }

    /**
     * Manual "Re-fetch branding assets" (§9) — separate from the data
     * refresh above, since branding is a one-time/on-demand job, not part
     * of the daily ingestion cadence (§5.2).
     */
    public function refreshBranding(Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);

        FetchBrandingAssetsJob::dispatch($event);

        return redirect()
            ->route('admin.events.edit', $event)
            ->with('status', 'Branding re-fetch queued.');
    }

    /**
     * Admin upload/replace of logo or favicon (§3.2 BR5) — always
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
            $path = "branding/{$event->id}/{$field}.".$file->extension();

            Storage::disk('public')->putFileAs(
                "branding/{$event->id}",
                $file,
                "{$field}.".$file->extension()
            );

            $event->update(["{$field}_path" => $path]);
        }

        return redirect()
            ->route('admin.events.edit', $event)
            ->with('status', 'Branding updated.');
    }
}
