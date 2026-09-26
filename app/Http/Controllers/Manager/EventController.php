<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\UpdateManagedEventInfoRequest;
use App\Http\Requests\Manager\UpdateManagedEventRequest;
use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Support\EventEdits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * An event manager's two event pages: "Event details" and "Event
 * information". Every method starts from the manager's own events, so an
 * event that isn't theirs is a 404 whatever id is asked for — and the lifecycle
 * status is not part of any form here, so it can't be changed.
 */
class EventController extends Controller
{
    use ResolvesManagedEvent;

    public function details(Request $request, string $eventId): View
    {
        return view('manager.events.details', ['event' => $this->managedEvent($request, $eventId)]);
    }

    public function updateDetails(UpdateManagedEventRequest $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $zoneBefore = $event->timezone;

        $event->update(EventEdits::details($request->validated(), $event));

        // Session times depend on the zone: re-read them in the new one (as the admin's save does).
        if ($event->timezone !== $zoneBefore && $event->status === 'active') {
            try {
                FetchSpeakersSponsorsSessionsJob::dispatch($event);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('manager.events.details', $event)->with('status', 'Event details saved.');
    }

    public function information(Request $request, string $eventId): View
    {
        return view('manager.events.information', ['event' => $this->managedEvent($request, $eventId)]);
    }

    public function updateInformation(UpdateManagedEventInfoRequest $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);

        $event->update(['info' => EventEdits::info($request->validated())]);

        return redirect()->route('manager.events.information', $event)->with('status', 'Event information saved.');
    }
}
